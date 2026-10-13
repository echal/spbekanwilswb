<?php

namespace Tests\Feature\Spbe;

use App\Models\RencanaTik;
use App\Models\RencanaTikItem;
use App\Services\SpbeQuarterlyPerformanceService;

class SpbeTriwulanTest extends SpbeTestCase
{
    protected SpbeQuarterlyPerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SpbeQuarterlyPerformanceService::class);
    }

    /**
     * Rencana 2026 sesuai tabel acuan Indikator 186 (target tahunan per komponen).
     */
    protected function rencanaAcuan2026(): RencanaTik
    {
        return $this->buatRencana(2026, 'disetujui', [
            [$this->unitA, 'jaringan', 12],
            [$this->unitA, 'aplikasi', 230],
            [$this->unitA, 'server', 3],
            [$this->unitA, 'perangkat', 230],
        ]);
    }

    protected function item(RencanaTik $rencana, string $kategori): RencanaTikItem
    {
        return $rencana->items->firstWhere('kategori', $kategori);
    }

    protected function persenKomponen(int $tahun, int $tw, string $kategori): ?float
    {
        return $this->service->getQuarterProgress($tahun, $tw)['komponen'][$kategori]['persentase'];
    }

    // 1
    public function test_tanggal_realisasi_maret_diverifikasi_april_masuk_tw1(): void
    {
        $rencana = $this->rencanaAcuan2026();
        $realisasi = $this->buatRealisasi($this->item($rencana, 'jaringan'), 3, 'menunggu', '2026-03-28');
        $realisasi->update(['status_verifikasi' => 'disetujui', 'diverifikasi_at' => '2026-04-03 09:00:00']);

        $this->assertSame(3, $this->service->getQuarterProgress(2026, 1)['komponen']['jaringan']['realisasi_kumulatif']);
    }

    // 2
    public function test_realisasi_belum_disetujui_tidak_dihitung(): void
    {
        $rencana = $this->rencanaAcuan2026();
        $this->buatRealisasi($this->item($rencana, 'jaringan'), 2, 'menunggu', '2026-02-01');
        $this->buatRealisasi($this->item($rencana, 'jaringan'), 5, 'ditolak', '2026-02-01');
        $this->buatRealisasi($this->item($rencana, 'jaringan'), 1, 'disetujui', '2026-02-01');

        $this->assertSame(1, $this->service->getAnnualProgress(2026)['komponen']['jaringan']['realisasi_kumulatif']);
    }

    // 3 & 4
    public function test_kumulatif_sejak_1_januari_dengan_batas_akhir_triwulan_inklusif(): void
    {
        $item = $this->item($this->rencanaAcuan2026(), 'jaringan');
        $this->buatRealisasi($item, 1, 'disetujui', '2026-01-01');
        $this->buatRealisasi($item, 2, 'disetujui', '2026-03-31');
        $this->buatRealisasi($item, 3, 'disetujui', '2026-04-01');
        $this->buatRealisasi($item, 4, 'disetujui', '2026-09-30');
        $this->buatRealisasi($item, 1, 'disetujui', '2026-12-31');

        $kumulatif = fn (int $tw) => $this->service->getQuarterProgress(2026, $tw)['komponen']['jaringan']['realisasi_kumulatif'];

        $this->assertSame(3, $kumulatif(1));   // 1 Jan + 31 Mar; 1 Apr belum masuk
        $this->assertSame(6, $kumulatif(2));   // + 1 Apr
        $this->assertSame(10, $kumulatif(3));  // + 30 Sep
        $this->assertSame(11, $kumulatif(4));  // + 31 Des
        $this->assertSame('2026-01-01', $this->service->getQuarterProgress(2026, 2)['periode_awal']);
        $this->assertSame('2026-06-30', $this->service->getQuarterProgress(2026, 2)['periode_akhir']);
    }

    // 5
    public function test_realisasi_tahun_2027_tidak_mengubah_capaian_2026(): void
    {
        $item = $this->item($this->rencanaAcuan2026(), 'jaringan');
        $this->buatRealisasi($item, 6, 'disetujui', '2026-11-15');
        $sebelum = $this->service->getAnnualProgress(2026);

        $this->buatRealisasi($item, 6, 'disetujui', '2027-01-05');
        $sesudah = $this->service->getAnnualProgress(2026);

        $this->assertSame(6, $sesudah['komponen']['jaringan']['realisasi_kumulatif']);
        $this->assertSame($sebelum['komponen'], $sesudah['komponen']);
    }

    // 6
    public function test_entri_bertahap_dijumlahkan_tanpa_duplikasi(): void
    {
        // Dua item perangkat di unit berbeda dalam rencana yang sama
        $rencana = $this->buatRencana(2026, 'disetujui', [
            [$this->unitA, 'perangkat', 100],
            [$this->unitB, 'perangkat', 130],
        ]);
        [$itemA, $itemB] = $rencana->items->sortBy('id')->values()->all();

        $this->buatRealisasi($itemA, 100, 'disetujui', '2026-02-10');
        $this->buatRealisasi($itemB, 39, 'disetujui', '2026-03-05');
        $this->buatRealisasi($itemB, 33, 'disetujui', '2026-05-20');

        $komponen = fn (int $tw) => $this->service->getQuarterProgress(2026, $tw)['komponen']['perangkat'];

        $this->assertSame(230, $komponen(1)['target_tahunan']);
        $this->assertSame(139, $komponen(1)['realisasi_kumulatif']);
        $this->assertSame(172, $komponen(2)['realisasi_kumulatif']);
    }

    // 7 + simulasi tabel acuan + realisasi melebihi target
    public function test_simulasi_tabel_acuan_memakai_rata_rata_komponen_bukan_rasio_total(): void
    {
        $r = $this->rencanaAcuan2026();

        // Entri inkremental yang menghasilkan posisi kumulatif sesuai tabel
        $this->buatRealisasi($this->item($r, 'jaringan'), 4, 'disetujui', '2026-02-15');
        $this->buatRealisasi($this->item($r, 'jaringan'), 8, 'disetujui', '2026-08-15');
        $this->buatRealisasi($this->item($r, 'server'), 1, 'disetujui', '2026-01-20');

        // Aplikasi: pegawai unik dengan akses aktif (43 s.d. TW I, 53 s.d. TW III).
        // Sebagian pegawai memegang 2 aplikasi agar terbukti tidak dihitung ganda.
        $appA = $this->buatAplikasi('Aplikasi A');
        $appB = $this->buatAplikasi('Aplikasi B');
        $pegawai = $this->buatPegawai(53);
        $pegawai->take(43)->each(fn ($p) => $this->beriAkses($p, $appA, '2026-03-15'));
        $pegawai->take(20)->each(fn ($p) => $this->beriAkses($p, $appB, '2026-02-10'));
        $pegawai->slice(43)->each(fn ($p) => $this->beriAkses($p, $appB, '2026-08-15'));
        // Realisasi manual Aplikasi tetap tersimpan tetapi tidak menambah pembilang
        $this->buatRealisasi($this->item($r, 'aplikasi'), 43, 'disetujui', '2026-03-15');
        $this->buatRealisasi($this->item($r, 'perangkat'), 139, 'disetujui', '2026-03-20');
        $this->buatRealisasi($this->item($r, 'perangkat'), 33, 'disetujui', '2026-05-20');
        $this->buatRealisasi($this->item($r, 'perangkat'), 75, 'disetujui', '2026-09-20');

        // Per komponen (realisasi kumulatif / target tahunan)
        $this->assertSame(33.33, $this->persenKomponen(2026, 1, 'jaringan'));
        $this->assertSame(18.7, $this->persenKomponen(2026, 2, 'aplikasi'));
        $this->assertSame(100.0, $this->persenKomponen(2026, 3, 'jaringan'));
        $this->assertSame(23.04, $this->persenKomponen(2026, 3, 'aplikasi'));
        // Melebihi target: tidak dibatasi 100%
        $this->assertSame(107.39, $this->persenKomponen(2026, 3, 'perangkat'));

        // Rata-rata sederhana 4 komponen (rasio total akan memberi 39,37% / 46,32% / 65,89%)
        $this->assertSame(36.45, $this->service->getQuarterProgress(2026, 1)['persentase_rata_rata']);
        $this->assertSame(40.04, $this->service->getQuarterProgress(2026, 2)['persentase_rata_rata']);
        $this->assertSame(65.94, $this->service->getQuarterProgress(2026, 3)['persentase_rata_rata']);
        $this->assertSame(53, $this->service->getQuarterProgress(2026, 3)['komponen']['aplikasi']['realisasi_kumulatif']);
        $this->assertArrayNotHasKey('total_realisasi_kumulatif', $this->service->getQuarterProgress(2026, 3));
    }

    // 8
    public function test_komponen_tanpa_target_tidak_dibagi_nol_dan_tidak_dianggap_nol_persen(): void
    {
        $rencana = $this->buatRencana(2026, 'disetujui', [
            [$this->unitA, 'jaringan', 10],
            [$this->unitA, 'perangkat', 10],
        ]);
        $this->buatRealisasi($this->item($rencana, 'jaringan'), 5, 'disetujui', '2026-02-01');

        $hasil = $this->service->getAnnualProgress(2026);

        $this->assertSame(50.0, $hasil['komponen']['jaringan']['persentase']);
        $this->assertNull($hasil['komponen']['aplikasi']['persentase']);
        $this->assertNull($hasil['komponen']['server']['persentase']);
        $this->assertNull($hasil['persentase_rata_rata']);
        $this->assertSame(['Aplikasi', 'Cloud Server'], $hasil['komponen_tanpa_target']);

        // Tahun tanpa rencana sama sekali
        $kosong = $this->service->getAnnualProgress(2030);
        $this->assertNull($kosong['persentase_rata_rata']);
        $this->assertCount(4, $kosong['komponen_tanpa_target']);
    }

    // 9
    public function test_triwulan_dan_batas_tanggal_mengikuti_tahun_terpilih(): void
    {
        $this->travelTo('2026-05-15');

        $this->assertSame(4, $this->service->quarterForYear(2025));
        $this->assertSame(2, $this->service->quarterForYear(2026));
        $this->assertSame(1, $this->service->quarterForYear(2027));

        $tw = $this->service->getQuarterProgress(2025, 4);
        $this->assertSame('2025-01-01', $tw['periode_awal']);
        $this->assertSame('2025-12-31', $tw['periode_akhir']);
    }

    // 10
    public function test_target_kumulatif_per_tahun_dari_konfigurasi_tidak_tertukar(): void
    {
        $this->assertSame([20.0, 60.0, 75.0, 85.0], array_map(
            fn (int $q) => $this->service->targetKumulatifBaseline(2026, $q),
            [1, 2, 3, 4],
        ));
        $this->assertNull($this->service->targetKumulatifBaseline(2025, 2));

        config(['spbe.target_indikator.2027.tambahan_triwulan' => [1 => 25.0, 2 => 25.0, 3 => 20.0, 4 => 20.0]]);

        $this->assertSame(50.0, $this->service->targetKumulatifBaseline(2027, 2));
        $this->assertSame(60.0, $this->service->targetKumulatifBaseline(2026, 2));
    }

    public function test_cache_dibatalkan_saat_realisasi_disetujui(): void
    {
        $item = $this->item($this->rencanaAcuan2026(), 'server');
        $realisasi = $this->buatRealisasi($item, 3, 'menunggu', '2026-02-01');

        $this->assertSame(0, $this->service->getAnnualProgress(2026)['komponen']['server']['realisasi_kumulatif']);

        $realisasi->update(['status_verifikasi' => 'disetujui']);

        $this->assertSame(3, $this->service->getAnnualProgress(2026)['komponen']['server']['realisasi_kumulatif']);
    }
}
