<?php

namespace Tests\Feature\Spbe;

use App\Filament\Widgets\SpbeKomponenTable;
use App\Models\RencanaTik;
use App\Services\SpbeQuarterlyPerformanceService;
use Livewire\Livewire;
use ReflectionMethod;

/**
 * Pekerjaan C: tabel capaian per komponen. Semua angka adalah fixture uji.
 */
class KomponenTableTest extends SpbeTestCase
{
    protected SpbeQuarterlyPerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SpbeQuarterlyPerformanceService::class);
        $this->actingAs($this->admin);
        $this->travelTo('2026-10-12');
    }

    protected function fixture(bool $denganServer = true): RencanaTik
    {
        $items = [
            [$this->unitA, 'jaringan', 3],
            [$this->unitA, 'aplikasi', 4],
            [$this->unitB, 'perangkat', 7],
        ];
        if ($denganServer) {
            $items[] = [$this->unitA, 'server', 1];
        }

        $r = $this->buatRencana(2026, 'disetujui', $items);
        $item = fn (string $k) => $r->items->firstWhere('kategori', $k);

        $this->buatRealisasi($item('jaringan'), 1, 'disetujui', '2026-02-01');
        $this->buatRealisasi($item('jaringan'), 1, 'disetujui', '2026-05-10');
        $this->buatRealisasi($item('perangkat'), 2, 'disetujui', '2026-03-10');
        $this->buatRealisasi($item('perangkat'), 6, 'disetujui', '2026-08-01'); // s.d. TW III: 8/7 > 100%
        $this->buatRealisasi($item('perangkat'), 9, 'menunggu', '2026-08-02');  // belum disetujui: tidak dihitung
        if ($denganServer) {
            $this->buatRealisasi($item('server'), 1, 'disetujui', '2026-01-15');
        }

        // Aplikasi: posisi akhir TW I = 2 (akses 2025 + Jan 2026), akhir TW II = 3
        $app = $this->buatAplikasi('App');
        [$p1, $p2, $p3] = $this->buatPegawai(3)->all();
        $this->beriAkses($p1, $app, '2025-06-01');
        $this->beriAkses($p2, $app, '2026-01-20');
        $this->beriAkses($p3, $app, '2026-05-05');

        return $r;
    }

    protected function tabel(int $tahun, int $tw)
    {
        return Livewire::test(SpbeKomponenTable::class, ['tahun' => $tahun, 'triwulan' => $tw]);
    }

    protected function baris(int $tahun, int $tw): array
    {
        return collect($this->service->getKomponenTriwulan($tahun, $tw)['komponen'])->keyBy('kategori')->all();
    }

    // 1
    public function test_empat_komponen_tampil_dengan_label_benar(): void
    {
        $this->fixture();

        $this->assertSame(
            ['Jaringan', 'Aplikasi', 'Cloud Server', 'Perangkat Pegawai'],
            array_column($this->service->getKomponenTriwulan(2026, 2)['komponen'], 'label'),
        );

        $this->tabel(2026, 2)
            ->assertSee(['Jaringan', 'Aplikasi', 'Cloud Server', 'Perangkat Pegawai'])
            ->assertTableColumnExists('target_tahunan')
            ->assertTableColumnExists('realisasi_kumulatif')
            ->assertTableColumnExists('realisasi_periode')
            ->assertTableColumnExists('persentase')
            ->assertTableColumnDoesNotExist('baseline');
    }

    // 2
    public function test_target_dan_realisasi_mengikuti_tahun_dan_triwulan(): void
    {
        $this->fixture();

        $this->tabel(2026, 1)
            ->assertTableColumnStateSet('target_tahunan', '3', 'jaringan')
            ->assertTableColumnStateSet('realisasi_kumulatif', '1', 'jaringan')
            ->assertTableColumnStateSet('realisasi_periode', '+1', 'jaringan')
            ->assertSee('Realisasi s.d. TW I');

        $this->tabel(2026, 2)
            ->assertTableColumnStateSet('realisasi_kumulatif', '2', 'jaringan')
            ->assertTableColumnStateSet('realisasi_periode', '+1', 'jaringan')
            ->assertTableColumnStateSet('realisasi_periode', '0', 'server');

        // Tahun tanpa rencana: target tidak tersedia
        $this->tabel(2025, 4)
            ->assertTableColumnStateSet('target_tahunan', '–', 'jaringan')
            ->assertTableColumnStateSet('persentase', '–', 'jaringan');
    }

    // 3
    public function test_persentase_konsisten_dengan_service_dan_kpi(): void
    {
        $this->fixture();

        $progress = $this->service->getQuarterProgress(2026, 3);
        $tabel = $this->tabel(2026, 3);
        $mentah = [];

        foreach ($this->baris(2026, 3) as $kategori => $b) {
            $this->assertSame($progress['komponen'][$kategori]['persentase'], $b['persentase']);
            $tabel->assertTableColumnStateSet('persentase', number_format($b['persentase'], 2) . '%', $kategori);
            $mentah[] = $b['realisasi_kumulatif'] / $b['target_tahunan'] * 100;
        }

        // Rata-rata sederhana keempat capaian di tabel = capaian indikator induk pada KPI
        $this->assertEqualsWithDelta(array_sum($mentah) / 4, $progress['persentase_rata_rata_presisi'], 1e-9);
    }

    // 4
    public function test_nilai_null_tidak_menjadi_nol(): void
    {
        $this->fixture(denganServer: false);

        $b = $this->baris(2026, 2)['server'];
        $this->assertNull($b['target_tahunan']);
        $this->assertNull($b['persentase']);

        $this->tabel(2026, 2)
            ->assertTableColumnStateSet('target_tahunan', '–', 'server')
            ->assertTableColumnStateSet('persentase', '–', 'server')
            ->assertTableColumnStateNotSet('persentase', '0.00%', 'server');
    }

    // 5
    public function test_capaian_di_atas_100_persen_tidak_dibatasi(): void
    {
        $this->fixture();

        $this->assertGreaterThan(100, $this->baris(2026, 3)['perangkat']['persentase']);

        $this->tabel(2026, 3)
            ->assertTableColumnStateSet('realisasi_kumulatif', '8', 'perangkat')
            ->assertTableColumnStateSet('persentase', '114.29%', 'perangkat');
    }

    // 6
    public function test_triwulan_berjalan_ditandai_sementara(): void
    {
        $this->fixture();

        $this->tabel(2026, 4)->assertSee('Capaian per Komponen s.d. TW IV 2026 (sementara)');
        $this->tabel(2026, 3)->assertDontSee('(sementara)');
    }

    // 7
    public function test_triwulan_mendatang_tidak_menampilkan_realisasi(): void
    {
        $this->travelTo('2026-05-15');
        $this->fixture();

        foreach ($this->baris(2026, 3) as $b) {
            $this->assertNull($b['realisasi_kumulatif']);
            $this->assertNull($b['realisasi_periode']);
            $this->assertNull($b['persentase']);
            $this->assertSame('belum_berjalan', $b['status_realisasi_periode']);
        }

        $this->tabel(2026, 3)
            ->assertSee('(belum berjalan)')
            ->assertTableColumnStateSet('realisasi_kumulatif', '–', 'perangkat')
            ->assertTableColumnStateSet('realisasi_periode', 'belum berjalan', 'aplikasi')
            ->assertTableColumnStateSet('persentase', '–', 'jaringan')
            ->assertTableColumnStateSet('target_tahunan', '7', 'perangkat'); // target tetap diketahui
    }

    // 8
    public function test_peringatan_x3_dan_capaian_tidak_dihitung(): void
    {
        $this->fixture();
        $this->buatRencana(2026, 'final', [[$this->unitA, 'jaringan', 9]]);

        foreach ($this->baris(2026, 2) as $b) {
            $this->assertNull($b['target_tahunan']);
            $this->assertNull($b['persentase']);
        }

        $this->tabel(2026, 2)
            ->assertSee('2 Rencana TIK')
            ->assertTableColumnStateSet('target_tahunan', '–', 'jaringan')
            ->assertTableColumnStateSet('persentase', '–', 'jaringan');
    }

    // 9 + 10
    public function test_aplikasi_membedakan_posisi_dan_perubahan_serta_keterbatasan_historis(): void
    {
        $this->fixture();

        $tw1 = $this->baris(2026, 1)['aplikasi'];
        $this->assertSame(2, $tw1['realisasi_kumulatif']);           // posisi akhir TW I
        $this->assertNull($tw1['realisasi_periode']);                // pembanding 31 Des 2025 tidak terbukti
        $this->assertSame('belum_terverifikasi', $tw1['status_realisasi_periode']);

        $tw2 = $this->baris(2026, 2)['aplikasi'];
        $this->assertSame(3, $tw2['realisasi_kumulatif']);           // posisi akhir TW II
        $this->assertSame(1, $tw2['realisasi_periode']);             // perubahan bersih
        $this->assertSame('turunan', $tw2['status_realisasi_periode']);

        $this->tabel(2026, 1)
            ->assertTableColumnStateSet('realisasi_kumulatif', '2', 'aplikasi')
            ->assertTableColumnStateSet('realisasi_periode', 'belum dapat diverifikasi', 'aplikasi');

        $this->tabel(2026, 2)
            ->assertTableColumnStateSet('realisasi_periode', '+1 *', 'aplikasi')
            ->assertSee('bukan snapshot historis terverifikasi')
            ->assertSee('belum dapat diverifikasi');

        $this->assertStringContainsString('bukan snapshot historis terverifikasi', $tw2['sumber']);
    }

    public function test_perubahan_negatif_tidak_dipaksa_nol(): void
    {
        $widget = Livewire::test(SpbeKomponenTable::class, ['tahun' => 2026, 'triwulan' => 2])->instance();
        $format = new ReflectionMethod($widget, 'formatPeriode');

        $this->assertSame('−2', $format->invoke($widget, ['realisasi_periode' => -2, 'status_realisasi_periode' => 'tersedia']));
        $this->assertSame('−5 *', $format->invoke($widget, ['realisasi_periode' => -5, 'status_realisasi_periode' => 'turunan']));
        $this->assertSame('0', $format->invoke($widget, ['realisasi_periode' => 0, 'status_realisasi_periode' => 'tersedia']));
    }
}
