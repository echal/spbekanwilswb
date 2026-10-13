<?php

namespace Tests\Feature\Spbe;

use App\Filament\Resources\RealisasiTiks\Pages\CreateRealisasiTik;
use App\Filament\Widgets\SpbeGlobalProgressWidget;
use App\Filament\Widgets\SpbePenggunaAplikasiTable;
use App\Filament\Widgets\SpbeUnitChart;
use App\Models\RealisasiTik;
use App\Models\RencanaTik;
use App\Services\SpbeEvaluationService;
use App\Services\SpbeQuarterlyPerformanceService;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use ReflectionMethod;

/**
 * Komponen Aplikasi = pegawai unik dengan akses Aktif (pegawai_aplikasi). Semua angka adalah fixture uji.
 */
class AplikasiPenggunaTest extends SpbeTestCase
{
    protected SpbeQuarterlyPerformanceService $service;

    protected RencanaTik $rencana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SpbeQuarterlyPerformanceService::class);

        $this->rencana = $this->buatRencana(2026, 'disetujui', [
            [$this->unitA, 'jaringan', 10],
            [$this->unitA, 'aplikasi', 230],
            [$this->unitA, 'server', 2],
            [$this->unitB, 'perangkat', 20],
        ]);
    }

    protected function aplikasi(int $tahun = 2026, int $tw = 4): array
    {
        return $this->service->getQuarterProgress($tahun, $tw)['komponen']['aplikasi'];
    }

    // 1
    public function test_45_aplikasi_tanpa_pemetaan_menghasilkan_0_pegawai(): void
    {
        foreach (range(1, 45) as $i) {
            $this->buatAplikasi("Aplikasi {$i}");
        }

        $this->assertSame(0, $this->aplikasi()['realisasi_kumulatif']);
        $this->assertSame(0.0, $this->aplikasi()['persentase']);
    }

    // 2 & 3
    public function test_satu_pegawai_lima_aplikasi_dihitung_sekali_di_total_dan_sekali_per_aplikasi(): void
    {
        $pegawai = $this->buatPegawai(1)->first();
        $apps = collect(range(1, 5))->map(fn ($i) => $this->buatAplikasi("App {$i}"));
        $apps->each(fn ($app, $i) => $this->beriAkses($pegawai, $app, '2026-02-01', 'Aktif', ['Admin', 'Operator', 'User'][$i % 3]));

        $this->assertSame(1, $this->aplikasi()['realisasi_kumulatif']);

        $perAplikasi = $this->service->queryPenggunaPerAplikasi('2026-12-31')->pluck('pegawai_akses_aktif', 'nama_aplikasi');
        $this->assertCount(5, $perAplikasi);
        $this->assertSame([1, 1, 1, 1, 1], $perAplikasi->map(fn ($n) => (int) $n)->values()->all());
    }

    public function test_irisan_pengguna_dihitung_unik_bukan_dijumlahkan(): void
    {
        // Fixture: A = 100 pegawai, B = 80 pegawai, 30 pegawai memakai keduanya → unik 150
        $appA = $this->buatAplikasi('A');
        $appB = $this->buatAplikasi('B');
        $pegawai = $this->buatPegawai(150);
        $pegawai->take(100)->each(fn ($p) => $this->beriAkses($p, $appA, '2026-01-10'));
        $pegawai->slice(70)->each(fn ($p) => $this->beriAkses($p, $appB, '2026-01-10'));

        $this->assertSame(150, $this->aplikasi()['realisasi_kumulatif']);
        $this->assertSame(65.22, $this->aplikasi()['persentase']);

        $perAplikasi = $this->service->queryPenggunaPerAplikasi('2026-12-31')->pluck('pegawai_akses_aktif', 'nama_aplikasi');
        $this->assertSame(100, (int) $perAplikasi['A']);
        $this->assertSame(80, (int) $perAplikasi['B']);
    }

    // 4
    public function test_akses_nonaktif_tidak_dihitung(): void
    {
        $app = $this->buatAplikasi('App');
        [$aktif, $nonaktif] = $this->buatPegawai(2)->all();
        $this->beriAkses($aktif, $app, '2026-01-10');
        $this->beriAkses($nonaktif, $app, '2026-01-10', 'Nonaktif');

        $this->assertSame(1, $this->aplikasi()['realisasi_kumulatif']);
        $this->assertSame(1, (int) $this->service->queryPenggunaPerAplikasi('2026-12-31')->value('pegawai_akses_aktif'));
    }

    // 5, 6, 7
    public function test_batas_tanggal_akhir_periode_inklusif_tanpa_batas_awal(): void
    {
        $app = $this->buatAplikasi('App');
        [$lama, $akhirMaret, $april, $tahunDepan] = $this->buatPegawai(4)->all();
        $this->beriAkses($lama, $app, '2019-06-01');        // sebelum 2026, masih aktif
        $this->beriAkses($akhirMaret, $app, '2026-03-31');  // tepat akhir TW I
        $this->beriAkses($april, $app, '2026-04-01');       // setelah TW I
        $this->beriAkses($tahunDepan, $app, '2027-01-05');  // tahun berikutnya

        $this->assertSame(2, $this->aplikasi(2026, 1)['realisasi_kumulatif']);
        $this->assertSame(3, $this->aplikasi(2026, 2)['realisasi_kumulatif']);
        $this->assertSame(3, $this->aplikasi(2026, 4)['realisasi_kumulatif']);
        $this->assertSame(2, (int) $this->service->queryPenggunaPerAplikasi('2026-03-31')->value('pegawai_akses_aktif'));
    }

    // 8
    public function test_realisasi_manual_aplikasi_43_tidak_menambah_pembilang_dan_tetap_tersimpan(): void
    {
        $item = $this->rencana->items->firstWhere('kategori', 'aplikasi');
        $manual = $this->buatRealisasi($item, 43, 'disetujui', '2026-03-31');

        $app = $this->buatAplikasi('App');
        $this->buatPegawai(5)->each(fn ($p) => $this->beriAkses($p, $app, '2026-02-01'));

        $this->assertSame(5, $this->aplikasi()['realisasi_kumulatif']);
        $this->assertDatabaseHas('realisasi_tik', ['id' => $manual->id, 'jumlah_realisasi' => 43, 'status_verifikasi' => 'disetujui']);

        // Kategori lain tetap memakai realisasi_tik
        $this->buatRealisasi($this->rencana->items->firstWhere('kategori', 'jaringan'), 4, 'disetujui', '2026-03-31');
        $this->assertSame(4, $this->service->getAnnualProgress(2026)['komponen']['jaringan']['realisasi_kumulatif']);
    }

    // 9
    public function test_entri_realisasi_baru_kategori_aplikasi_ditolak_validasi_server(): void
    {
        $itemAplikasi = $this->rencana->items->firstWhere('kategori', 'aplikasi');
        $itemJaringan = $this->rencana->items->firstWhere('kategori', 'jaringan');

        foreach ([$this->admin, $this->operatorA] as $user) {
            $this->actingAs($user);

            Livewire::test(CreateRealisasiTik::class)
                ->fillForm([
                    'rencana_item_id' => $itemAplikasi->id,
                    'jumlah_realisasi' => 1,
                    'tanggal_realisasi' => '2026-02-01',
                ])
                ->call('create')
                ->assertHasFormErrors(['rencana_item_id']);
        }

        $this->assertSame(0, RealisasiTik::count());

        // Kategori lain tetap bisa diinput
        Livewire::test(CreateRealisasiTik::class)
            ->fillForm([
                'rencana_item_id' => $itemJaringan->id,
                'jumlah_realisasi' => 1,
                'tanggal_realisasi' => '2026-02-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, RealisasiTik::count());
    }

    // 10
    public function test_target_aplikasi_kosong_menghasilkan_null(): void
    {
        $app = $this->buatAplikasi('App');
        $this->buatPegawai(3)->each(fn ($p) => $this->beriAkses($p, $app, '2025-02-01'));

        $hasil = $this->service->getAnnualProgress(2025);

        $this->assertSame(3, $hasil['komponen']['aplikasi']['realisasi_kumulatif']);
        $this->assertSame(0, $hasil['komponen']['aplikasi']['target_tahunan']);
        $this->assertNull($hasil['komponen']['aplikasi']['persentase']);
        $this->assertContains('Aplikasi', $hasil['komponen_tanpa_target']);
    }

    // 11
    public function test_perubahan_akses_dan_penghapusan_pegawai_membatalkan_cache(): void
    {
        $app = $this->buatAplikasi('App');
        [$p1, $p2] = $this->buatPegawai(2)->all();
        $akses = $this->beriAkses($p1, $app, '2026-01-10');

        $this->assertSame(1, $this->aplikasi()['realisasi_kumulatif']);

        $this->beriAkses($p2, $app, '2026-01-10');
        $this->assertSame(2, $this->aplikasi()['realisasi_kumulatif']);

        $akses->update(['status_akses' => 'Nonaktif']);
        $this->assertSame(1, $this->aplikasi()['realisasi_kumulatif']);

        $p2->delete(); // akses ikut terhapus lewat cascade database
        $this->assertSame(0, $this->aplikasi()['realisasi_kumulatif']);
    }

    // 12
    public function test_grafik_unit_tidak_memasukkan_komponen_aplikasi(): void
    {
        // Unit A: jaringan 10 + server 2 (aplikasi 230 dikecualikan); Unit B: perangkat 20
        $this->buatRealisasi($this->rencana->items->firstWhere('kategori', 'jaringan'), 6, 'disetujui', '2026-02-01');
        $this->buatRealisasi($this->rencana->items->firstWhere('kategori', 'aplikasi'), 43, 'disetujui', '2026-02-01');
        $app = $this->buatAplikasi('App');
        $this->buatPegawai(10)->each(fn ($p) => $this->beriAkses($p, $app, '2026-02-01'));

        $perUnit = app(SpbeEvaluationService::class)->getByUnit(2026)->keyBy('unit_kerja');

        $this->assertSame(12, $perUnit['Unit A']->total_direncanakan);
        $this->assertSame(6, $perUnit['Unit A']->total_terpenuhi);
        $this->assertSame(50.0, $perUnit['Unit A']->persentase);
        $this->assertSame(20, $perUnit['Unit B']->total_direncanakan);

        $this->actingAs($this->admin);
        $grafik = Livewire::test(SpbeUnitChart::class, ['tahun' => 2026])->instance();
        $this->assertStringContainsString('Aplikasi tidak disertakan', $grafik->getDescription());
    }

    // 13
    public function test_widget_menampilkan_rincian_per_komponen_tanpa_total_lintas_satuan(): void
    {
        $app = $this->buatAplikasi('App');
        $this->buatPegawai(23)->each(fn ($p) => $this->beriAkses($p, $app, '2026-02-01'));
        $this->buatRealisasi($this->rencana->items->firstWhere('kategori', 'jaringan'), 5, 'disetujui', '2026-02-01');

        $this->actingAs($this->admin);
        $instance = Livewire::test(SpbeGlobalProgressWidget::class, ['tahun' => 2026])->instance();
        $stats = collect((new ReflectionMethod($instance, 'getStats'))->invoke($instance));

        $this->assertSame(
            ['Jaringan', 'Aplikasi', 'Cloud Server', 'Perangkat Pegawai', 'Capaian SPBE'],
            $stats->map(fn (Stat $s) => (string) $s->getLabel())->all(),
        );
        $this->assertSame('23 / 230', (string) $stats[1]->getValue());
        $this->assertStringContainsString('pegawai dengan akses aktif', (string) $stats[1]->getDescription());
        $this->assertSame('5 / 10', (string) $stats[0]->getValue());
        $this->assertFalse($stats->contains(fn (Stat $s) => str_contains((string) $s->getLabel(), 'Total')));
    }

    public function test_tabel_per_aplikasi_menampilkan_aplikasi_tanpa_pemetaan_dan_mengikuti_tahun(): void
    {
        $this->travelTo('2026-10-12');
        $dipakai = $this->buatAplikasi('Dipakai');
        $kosong = $this->buatAplikasi('Tanpa Pemetaan');
        [$p1, $p2] = $this->buatPegawai(2)->all();
        $this->beriAkses($p1, $dipakai, '2024-05-01');
        $this->beriAkses($p2, $dipakai, '2026-02-01');

        $this->actingAs($this->admin);

        Livewire::test(SpbePenggunaAplikasiTable::class, ['tahun' => 2026])
            ->assertCanSeeTableRecords([$dipakai, $kosong])
            ->assertTableColumnStateSet('pegawai_akses_aktif', 2, $dipakai)
            ->assertTableColumnStateSet('pegawai_akses_aktif', 0, $kosong)
            ->assertSee('belum ada pemetaan akses aktif');

        // Tahun 2025 (posisi s.d. 31 Des 2025): akses Februari 2026 belum dihitung
        Livewire::test(SpbePenggunaAplikasiTable::class, ['tahun' => 2025])
            ->assertTableColumnStateSet('pegawai_akses_aktif', 1, $dipakai);
    }
}
