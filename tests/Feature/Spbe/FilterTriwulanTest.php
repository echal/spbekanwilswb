<?php

namespace Tests\Feature\Spbe;

use App\Filament\Pages\DashboardSpbe;
use App\Filament\Widgets\SpbeGlobalProgressWidget;
use App\Filament\Widgets\SpbeKategoriChart;
use App\Filament\Widgets\SpbeKomponenTable;
use App\Filament\Widgets\SpbeTrenKinerjaChart;
use App\Filament\Widgets\SpbeKpiWidget;
use App\Filament\Widgets\SpbePenggunaAplikasiTable;
use App\Filament\Widgets\SpbeUnitChart;
use App\Models\Aplikasi;
use App\Services\SpbeQuarterlyPerformanceService;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Features\SupportReactiveProps\SupportReactiveProps;
use Livewire\Livewire;
use ReflectionMethod;

use function Livewire\on;

/**
 * Pekerjaan A: filter tahun & triwulan, reset triwulan, status periode, dan perlakuan triwulan mendatang.
 */
class FilterTriwulanTest extends SpbeTestCase
{
    protected SpbeQuarterlyPerformanceService $service;

    protected Aplikasi $aplikasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SpbeQuarterlyPerformanceService::class);

        $rencana = $this->buatRencana(2026, 'disetujui', [
            [$this->unitA, 'jaringan', 10],
            [$this->unitA, 'aplikasi', 20],
            [$this->unitA, 'server', 2],
            [$this->unitB, 'perangkat', 40],
        ]);
        $this->buatRealisasi($rencana->items->firstWhere('kategori', 'jaringan'), 4, 'disetujui', '2026-02-01');

        $this->aplikasi = $this->buatAplikasi('App Uji');
        [$p1, $p2] = $this->buatPegawai(2)->all();
        $this->beriAkses($p1, $this->aplikasi, '2025-06-01');
        $this->beriAkses($p2, $this->aplikasi, '2026-05-01');

        $this->actingAs($this->admin);
    }

    protected function stats(string $widget, array $params): array
    {
        $instance = Livewire::test($widget, $params)->instance();

        return collect((new ReflectionMethod($instance, 'getStats'))->invoke($instance))
            ->mapWithKeys(fn (Stat $s, $i) => [$i => ['label' => (string) $s->getLabel(), 'value' => (string) $s->getValue(), 'description' => (string) $s->getDescription()]])
            ->all();
    }

    // 1, 2
    public function test_nilai_awal_triwulan_adalah_triwulan_kalender_berjalan(): void
    {
        $this->travelTo('2026-05-15 10:00:00');

        Livewire::test(DashboardSpbe::class)
            ->assertSet('tahun', 2026)
            ->assertSet('triwulan', 2)
            ->assertSchemaStateSet(['tahun' => 2026, 'triwulan' => 2]);
    }

    // 2, 3
    public function test_nilai_awal_per_tahun(): void
    {
        $this->travelTo('2026-11-02');

        $this->assertSame(4, $this->service->triwulanAwal(2026));
        $this->assertSame(1, $this->service->triwulanAwal(2025));
        $this->assertSame(1, $this->service->triwulanAwal(2027));
    }

    // 4
    public function test_perubahan_tahun_mengatur_ulang_triwulan(): void
    {
        $this->travelTo('2026-05-15');

        Livewire::test(DashboardSpbe::class)
            ->fillForm(['triwulan' => 4])
            ->assertSet('triwulan', 4)
            ->fillForm(['tahun' => 2025])
            ->assertSet('tahun', 2025)
            ->assertSet('triwulan', 1)
            ->assertSchemaStateSet(['triwulan' => 1])
            ->fillForm(['triwulan' => 3])
            ->fillForm(['tahun' => 2026])
            ->assertSet('triwulan', 2)
            ->assertSchemaStateSet(['triwulan' => 2]);
    }

    // 5
    public function test_status_periode_selesai_berjalan_belum(): void
    {
        $this->travelTo('2026-05-15');

        $this->assertSame('selesai', $this->service->statusPeriode(2026, 1));
        $this->assertSame('berjalan', $this->service->statusPeriode(2026, 2));
        $this->assertSame('belum', $this->service->statusPeriode(2026, 3));
        $this->assertSame('belum', $this->service->statusPeriode(2026, 4));
        $this->assertSame('selesai', $this->service->statusPeriode(2025, 4));
        $this->assertSame('belum', $this->service->statusPeriode(2027, 1));
    }

    // 6
    public function test_batas_awal_dan_akhir_triwulan(): void
    {
        $this->assertSame('2026-03-31', $this->service->akhirTriwulan(2026, 1));
        $this->assertSame('2026-06-30', $this->service->akhirTriwulan(2026, 2));
        $this->assertSame('2026-09-30', $this->service->akhirTriwulan(2026, 3));
        $this->assertSame('2026-12-31', $this->service->akhirTriwulan(2026, 4));
        $this->assertSame('2024-03-31', $this->service->akhirTriwulan(2024, 1));

        $this->travelTo('2026-03-31 23:59:59');
        $this->assertSame('berjalan', $this->service->statusPeriode(2026, 1));
        $this->assertSame('belum', $this->service->statusPeriode(2026, 2));

        $this->travelTo('2026-04-01 00:00:00');
        $this->assertSame('selesai', $this->service->statusPeriode(2026, 1));
        $this->assertSame('berjalan', $this->service->statusPeriode(2026, 2));

        $this->travelTo('2027-01-01 00:00:00');
        $this->assertSame('selesai', $this->service->statusPeriode(2026, 4));
        $this->assertSame('berjalan', $this->service->statusPeriode(2027, 1));
    }

    // 7
    public function test_widget_periode_menerima_tahun_dan_triwulan_terpilih(): void
    {
        $this->travelTo('2026-10-12');
        Livewire::withoutLazyLoading();

        $halaman = Livewire::test(DashboardSpbe::class);

        $diterima = [];
        on('mount.stub', function ($tag, $id, $params, $parent, $key) use (&$diterima) {
            $diterima[$key] = $params;
        });

        $halaman->fillForm(['triwulan' => 2]);

        $this->assertCount(7, $diterima);

        $ikutPeriode = [SpbeKpiWidget::class, SpbeKomponenTable::class, SpbeTrenKinerjaChart::class, SpbePenggunaAplikasiTable::class];
        $tahunan = [SpbeGlobalProgressWidget::class, SpbeKategoriChart::class, SpbeUnitChart::class];

        foreach ($diterima as $key => $params) {
            $this->assertSame(2026, $params['tahun'], $key);

            if (collect($ikutPeriode)->contains(fn ($w) => str_contains($key, $w . '-'))) {
                $this->assertSame(2, $params['triwulan'], $key);
            } else {
                $this->assertTrue(collect($tahunan)->contains(fn ($w) => str_contains($key, $w . '-')), $key);
                $this->assertArrayNotHasKey('triwulan', $params, "{$key} adalah widget posisi tahunan");
            }
        }

        // Sisi widget: prop reaktif triwulan diterapkan
        foreach ([SpbeKpiWidget::class, SpbePenggunaAplikasiTable::class] as $widget) {
            $anak = Livewire::test($widget, ['tahun' => 2026, 'triwulan' => 4]);
            SupportReactiveProps::$pendingChildParams[$anak->id()] = ['tahun' => 2026, 'triwulan' => 2];
            $anak->call('$refresh')->assertSet('triwulan', 2);
        }

        $kpi = Livewire::test(SpbeKpiWidget::class, ['tahun' => 2026, 'triwulan' => 4]);
        SupportReactiveProps::$pendingChildParams[$kpi->id()] = ['tahun' => 2026, 'triwulan' => 2];
        $kpi->call('$refresh')->assertSee('Capaian Indikator Induk s.d. TW II 2026');
    }

    // 8
    public function test_widget_tahunan_berlabel_posisi_tahunan_dan_nilainya_tidak_bergantung_triwulan(): void
    {
        $this->travelTo('2026-10-12');

        $kategori = Livewire::test(SpbeKategoriChart::class, ['tahun' => 2026])->instance();
        $this->assertStringContainsString('Posisi tahunan', $kategori->getDescription());

        $unit = Livewire::test(SpbeUnitChart::class, ['tahun' => 2026])->instance();
        $this->assertStringContainsString('Posisi tahunan', $unit->getDescription());

        $global = Livewire::test(SpbeGlobalProgressWidget::class, ['tahun' => 2026])->instance();
        $this->assertStringContainsString('Posisi tahunan', (new ReflectionMethod($global, 'getDescription'))->invoke($global));

        // Sejak Pekerjaan B, ketiga KPI mengikuti triwulan terpilih (tidak ada lagi KPI posisi tahunan)
        $tw1 = $this->stats(SpbeKpiWidget::class, ['tahun' => 2026, 'triwulan' => 1]);
        $tw4 = $this->stats(SpbeKpiWidget::class, ['tahun' => 2026, 'triwulan' => 4]);
        foreach ([0, 1, 2] as $i) {
            $this->assertStringContainsString('TW I 2026', $tw1[$i]['label']);
            $this->assertStringNotContainsString('(sementara)', $tw1[$i]['label']);
            $this->assertStringContainsString('TW IV 2026 (sementara)', $tw4[$i]['label']);
            $this->assertStringNotContainsString('Posisi tahunan', $tw4[$i]['description']);
        }

        // Rumus komponen tidak berubah: jaringan 4/10 = 40%
        $this->assertSame(40.0, $this->service->getAnnualProgress(2026)['komponen']['jaringan']['persentase']);
    }

    // 9
    public function test_tabel_pengguna_aplikasi_memakai_akhir_triwulan_terpilih(): void
    {
        $this->travelTo('2026-10-12');

        // Akses 2025-06-01 dan 2026-05-01 → TW I 2026: 1 pegawai; TW II 2026: 2 pegawai
        Livewire::test(SpbePenggunaAplikasiTable::class, ['tahun' => 2026, 'triwulan' => 1])
            ->assertTableColumnStateSet('pegawai_akses_aktif', 1, $this->aplikasi)
            ->assertSee('31 March 2026');

        Livewire::test(SpbePenggunaAplikasiTable::class, ['tahun' => 2026, 'triwulan' => 2])
            ->assertTableColumnStateSet('pegawai_akses_aktif', 2, $this->aplikasi);

        Livewire::test(SpbePenggunaAplikasiTable::class, ['tahun' => 2026, 'triwulan' => 4])
            ->assertSee('(sementara)');
    }

    // 10
    public function test_triwulan_mendatang_tidak_menampilkan_realisasi_nol(): void
    {
        $this->travelTo('2026-05-15');

        $tw3 = $this->service->getQuarterProgress(2026, 3);
        $this->assertSame('belum', $tw3['status_periode']);
        $this->assertNull($tw3['persentase_rata_rata']);
        foreach ($tw3['komponen'] as $k) {
            $this->assertNull($k['realisasi_kumulatif']);
            $this->assertNull($k['persentase']);
        }
        // Target tetap diketahui; daftar "tanpa target" tidak terpengaruh status periode
        $this->assertSame(10, $tw3['komponen']['jaringan']['target_tahunan']);
        $this->assertSame([], $tw3['komponen_tanpa_target']);

        // Triwulan berjalan & selesai tetap dihitung seperti biasa
        $this->assertSame('berjalan', $this->service->getQuarterProgress(2026, 2)['status_periode']);
        $this->assertSame(4, $this->service->getQuarterProgress(2026, 1)['komponen']['jaringan']['realisasi_kumulatif']);

        // KPI 2
        $kpi = $this->stats(SpbeKpiWidget::class, ['tahun' => 2026, 'triwulan' => 3]);
        $this->assertSame('–', $kpi[1]['value']);
        $this->assertStringContainsString('(belum berjalan)', $kpi[1]['label']);
        $this->assertStringContainsString('belum berjalan', $kpi[1]['description']);

        // Tabel pengguna aplikasi: tidak menampilkan posisi hari ini sebagai posisi masa depan
        Livewire::test(SpbePenggunaAplikasiTable::class, ['tahun' => 2026, 'triwulan' => 3])
            ->assertCountTableRecords(0)
            ->assertSee('Triwulan belum berjalan');

        // Widget tahunan untuk tahun mendatang: '–', bukan '0'
        $global = $this->stats(SpbeGlobalProgressWidget::class, ['tahun' => 2027]);
        foreach (array_slice($global, 0, 4) as $stat) {
            $this->assertStringStartsWith('– /', $stat['value']);
            $this->assertStringStartsWith('Belum berjalan', $stat['description']);
        }
    }
}
