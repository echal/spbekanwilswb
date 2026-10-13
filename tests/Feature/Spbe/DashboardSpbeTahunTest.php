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
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;
use Livewire\Features\SupportReactiveProps\SupportReactiveProps;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;

use function Livewire\on;

class DashboardSpbeTahunTest extends SpbeTestCase
{
    protected const WIDGETS = [
        SpbeKpiWidget::class,
        SpbeGlobalProgressWidget::class,
        SpbeKategoriChart::class,
        SpbeUnitChart::class,
        SpbePenggunaAplikasiTable::class,
        SpbeKomponenTable::class,
        SpbeTrenKinerjaChart::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // 2025: keempat komponen punya target (total 13); Perangkat 3/6 = 50%, lainnya 0%
        // → rata-rata 4 komponen = (0 + 0 + 0 + 50) / 4 = 12,5%
        $rencana2025 = $this->buatRencana(2025, 'disetujui', [
            [$this->unitB, 'jaringan', 4],
            [$this->unitB, 'aplikasi', 2],
            [$this->unitB, 'server', 1],
            [$this->unitA, 'perangkat', 6],
        ]);
        $this->buatRealisasi($rencana2025->items->firstWhere('kategori', 'perangkat'), 3, 'disetujui', '2025-05-10');

        // 2026: hanya Jaringan yang punya target → rata-rata tidak dapat dihitung
        $this->buatRencana(2026, 'disetujui', [[$this->unitA, 'jaringan', 3]]);

        $this->travelTo(now()->setDate(2026, 10, 12));
    }

    public function test_properti_tahun_di_semua_widget_reaktif_dan_tidak_dimutasi_saat_mount(): void
    {
        foreach (self::WIDGETS as $widget) {
            $properti = new ReflectionProperty($widget, 'tahun');

            $this->assertNotEmpty($properti->getAttributes(Reactive::class), "{$widget}::\$tahun harus #[Reactive]");
            $this->assertFalse(
                method_exists($widget, 'mount')
                    && (new ReflectionMethod($widget, 'mount'))->getDeclaringClass()->getName() === $widget,
                "{$widget} tidak boleh mendefinisikan mount() yang memutasi \$tahun",
            );
        }
    }

    public function test_ganti_tahun_pada_halaman_memperbarui_data_widget(): void
    {
        $this->actingAs($this->admin);

        $halaman = Livewire::test(DashboardSpbe::class)
            ->assertSet('tahun', 2026);

        // setUp: travelTo 12 Okt 2026 → triwulan berjalan = IV
        $this->assertSame(['tahun' => 2026, 'triwulan' => 4], $halaman->instance()->getWidgetData());

        // Ganti tahun → triwulan diatur ulang ke TW I (tahun bukan tahun berjalan)
        $halaman->fillForm(['tahun' => 2025])
            ->assertSet('tahun', 2025);

        $this->assertSame(['tahun' => 2025, 'triwulan' => 1], $halaman->instance()->getWidgetData());
    }

    /**
     * Di browser, Livewire membundel request parent dan widget anak: parent merender stub anak
     * dengan parameter baru (hook `mount.stub`), lalu BaseReactive::hydrate() pada anak membaca
     * parameter itu. Test harness hanya memproses satu komponen per request, sehingga kedua sisi
     * diuji terpisah memakai hook Livewire yang sama.
     */
    public function test_parent_mengirim_tahun_baru_ke_semua_widget_anak(): void
    {
        $this->actingAs($this->admin);

        Livewire::withoutLazyLoading();

        $halaman = Livewire::test(DashboardSpbe::class)
            ->assertSee('Capaian Indikator Induk s.d. TW IV 2026');

        $tahunDikirim = [];
        on('mount.stub', function ($tag, $id, $params) use (&$tahunDikirim) {
            $tahunDikirim[] = $params['tahun'] ?? null;
        });

        $halaman->fillForm(['tahun' => 2025]);

        $this->assertSame(array_fill(0, count(self::WIDGETS), 2025), $tahunDikirim);
    }

    public function test_widget_anak_menerapkan_tahun_baru_dari_parent(): void
    {
        $this->actingAs($this->admin);

        foreach (self::WIDGETS as $widget) {
            $anak = Livewire::test($widget, ['tahun' => 2026]);

            // Meniru parameter yang dikirim parent saat re-render (lihat test di atas)
            SupportReactiveProps::$pendingChildParams[$anak->id()] = ['tahun' => 2025];

            $anak->call('$refresh')->assertSet('tahun', 2025);
        }

        $kpi = Livewire::test(SpbeKpiWidget::class, ['tahun' => 2026, 'triwulan' => 4])
            ->assertSee('Capaian Indikator Induk s.d. TW IV 2026');
        SupportReactiveProps::$pendingChildParams[$kpi->id()] = ['tahun' => 2025, 'triwulan' => 4];
        $kpi->call('$refresh')
            ->assertSee('Capaian Indikator Induk s.d. TW IV 2025')
            ->assertSee('12.50%');
    }

    public function test_semua_widget_memakai_tahun_yang_diberikan(): void
    {
        $this->actingAs($this->admin);

        // KPI global
        $global2025 = $this->stats(SpbeGlobalProgressWidget::class, 2025);
        $global2026 = $this->stats(SpbeGlobalProgressWidget::class, 2026);
        // Rincian per komponen (Jaringan, Aplikasi, Cloud Server, Perangkat) + Capaian SPBE; tanpa total lintas satuan
        $this->assertSame(['0 / 4', '0 / 2', '0 / 1', '3 / 6', '12.5%'], $global2025);
        $this->assertSame(['0 / 3', '0 / –', '0 / –', '0 / –', '–'], $global2026);

        // KPI 1 (capaian indikator induk s.d. TW IV)
        $this->assertSame('12.50%', $this->stats(SpbeKpiWidget::class, 2025, 4)[0]);
        $this->assertSame('–', $this->stats(SpbeKpiWidget::class, 2026, 4)[0]);

        // Grafik kategori
        $this->assertSame(['Aplikasi', 'Jaringan', 'Perangkat', 'Server'], $this->sorted($this->chart(SpbeKategoriChart::class, 2025)['labels']));
        $this->assertSame(['Jaringan'], $this->chart(SpbeKategoriChart::class, 2026)['labels']);

        // Grafik unit kerja
        $this->assertSame(['Unit A', 'Unit B'], $this->sorted($this->chart(SpbeUnitChart::class, 2025)['labels']));
        $this->assertSame(['Unit A'], $this->chart(SpbeUnitChart::class, 2026)['labels']);
    }

    protected function stats(string $widget, int $tahun, ?int $triwulan = null): array
    {
        $instance = Livewire::test($widget, array_filter(['tahun' => $tahun, 'triwulan' => $triwulan]))->instance();

        return collect((new ReflectionMethod($instance, 'getStats'))->invoke($instance))
            ->map(fn (Stat $stat) => (string) $stat->getValue())
            ->all();
    }

    protected function chart(string $widget, int $tahun): array
    {
        $instance = Livewire::test($widget, ['tahun' => $tahun])->instance();

        return (new ReflectionMethod($instance, 'getData'))->invoke($instance);
    }

    protected function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
