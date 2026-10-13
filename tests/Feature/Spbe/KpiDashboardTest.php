<?php

namespace Tests\Feature\Spbe;

use App\Filament\Pages\DashboardSpbe;
use App\Filament\Widgets\SpbeKpiWidget;
use App\Models\RencanaTik;
use App\Services\SpbeQuarterlyPerformanceService;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use ReflectionMethod;

/**
 * Pekerjaan B: KPI 1 capaian indikator induk, KPI 2 target kinerja kumulatif, KPI 3 capaian kinerja.
 * Semua angka adalah fixture uji.
 */
class KpiDashboardTest extends SpbeTestCase
{
    protected SpbeQuarterlyPerformanceService $service;

    /** Capaian induk TW I fixture (presisi penuh): (1/3 + 1/4 + 1/1 + 2/7) / 4 × 100 */
    protected float $indukTw1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SpbeQuarterlyPerformanceService::class);
        $this->indukTw1 = (1 / 3 * 100 + 1 / 4 * 100 + 100 + 2 / 7 * 100) / 4;

        $this->actingAs($this->admin);
        $this->travelTo('2026-10-12');
    }

    protected function rencanaLengkap(bool $denganServer = true): RencanaTik
    {
        $items = [
            [$this->unitA, 'jaringan', 3],
            [$this->unitA, 'aplikasi', 4],
            [$this->unitB, 'perangkat', 7],
        ];

        if ($denganServer) {
            $items[] = [$this->unitA, 'server', 1];
        }

        $rencana = $this->buatRencana(2026, 'disetujui', $items);

        $this->buatRealisasi($rencana->items->firstWhere('kategori', 'jaringan'), 1, 'disetujui', '2026-02-01');
        $this->buatRealisasi($rencana->items->firstWhere('kategori', 'perangkat'), 2, 'disetujui', '2026-03-10');
        if ($denganServer) {
            $this->buatRealisasi($rencana->items->firstWhere('kategori', 'server'), 1, 'disetujui', '2026-01-15');
        }

        $this->beriAkses($this->buatPegawai(1)->first(), $this->buatAplikasi('App'), '2026-01-20');

        return $rencana;
    }

    /**
     * @return array<int, array{label: string, value: string, description: string}>
     */
    protected function kpi(int $tahun, int $triwulan): array
    {
        $instance = Livewire::test(SpbeKpiWidget::class, ['tahun' => $tahun, 'triwulan' => $triwulan])->instance();

        return collect((new ReflectionMethod($instance, 'getStats'))->invoke($instance))
            ->map(fn (Stat $s) => [
                'label' => (string) $s->getLabel(),
                'value' => (string) $s->getValue(),
                'description' => (string) $s->getDescription(),
            ])
            ->all();
    }

    // 1
    public function test_kpi1_rata_rata_sederhana_empat_komponen(): void
    {
        $this->rencanaLengkap();

        $tw1 = $this->service->getQuarterProgress(2026, 1);

        $this->assertEqualsWithDelta($this->indukTw1, $tw1['persentase_rata_rata_presisi'], 1e-9);
        $this->assertSame(round($this->indukTw1, 2), $tw1['persentase_rata_rata']);

        $kpi = $this->kpi(2026, 1);
        $this->assertSame('Capaian Indikator Induk s.d. TW I 2026', $kpi[0]['label']);
        $this->assertSame(number_format($this->indukTw1, 2) . '%', $kpi[0]['value']); // 46.73%
    }

    // 2 + 6
    public function test_kpi1_dan_kpi3_null_bila_satu_komponen_tanpa_target(): void
    {
        $this->rencanaLengkap(denganServer: false);

        $tw1 = $this->service->getQuarterProgress(2026, 1);
        $this->assertNull($tw1['persentase_rata_rata_presisi']);
        $this->assertNull($tw1['capaian_kinerja']);

        $kpi = $this->kpi(2026, 1);
        $this->assertSame('–', $kpi[0]['value']);
        $this->assertStringContainsString('Cloud Server', $kpi[0]['description']);
        $this->assertSame('20.00%', $kpi[1]['value']); // target tetap tampil
        $this->assertSame('–', $kpi[2]['value']);
        $this->assertStringContainsString('belum tersedia', $kpi[2]['description']);
    }

    // 3
    public function test_kpi2_target_kumulatif_dari_konfigurasi(): void
    {
        $this->rencanaLengkap();

        foreach ([1 => '20.00%', 2 => '60.00%', 3 => '75.00%', 4 => '85.00%'] as $tw => $harapan) {
            $kpi = $this->kpi(2026, $tw);
            $this->assertSame($harapan, $kpi[1]['value'], "TW {$tw}");
            $this->assertStringContainsString("Target Kinerja Kumulatif TW", $kpi[1]['label']);
            $this->assertStringNotContainsStringIgnoringCase('baseline', $kpi[1]['label'] . $kpi[1]['description']);
        }

        $this->assertSame(75.0, $this->service->targetKumulatif(2026, 3));
    }

    public function test_kpi2_mengikuti_perubahan_konfigurasi_tanpa_angka_di_widget(): void
    {
        config(['spbe.target_indikator.2026.tambahan_triwulan' => [1 => 10.0, 2 => 10.0, 3 => 10.0, 4 => 10.0]]);
        $this->rencanaLengkap();

        $this->assertSame('30.00%', $this->kpi(2026, 3)[1]['value']);
    }

    // 4 + 5
    public function test_kpi3_presisi_penuh_dan_tidak_dibatasi_100_persen(): void
    {
        $this->rencanaLengkap();

        $tw1 = $this->service->getQuarterProgress(2026, 1);
        $harapan = $this->indukTw1 / 20 * 100; // 233.630952...

        $this->assertEqualsWithDelta($harapan, $tw1['capaian_kinerja'], 1e-9);
        $this->assertGreaterThan(100, $tw1['capaian_kinerja']);

        $kpi = $this->kpi(2026, 1);
        // Dari presisi penuh: 233.63%. Bila dihitung dari induk yang sudah dibulatkan (46.73) hasilnya 233.65%.
        $this->assertSame('233.63%', $kpi[2]['value']);
        $this->assertNotSame(number_format(round($this->indukTw1, 2) / 20 * 100, 2) . '%', $kpi[2]['value']);
        $this->assertStringContainsString('bukan persentase realisasi', $kpi[2]['description']);
    }

    // 7
    public function test_kpi3_null_bila_target_kumulatif_tidak_tersedia_atau_nol(): void
    {
        $this->assertNull($this->service->capaianKinerja(50.0, null));
        $this->assertNull($this->service->capaianKinerja(50.0, 0.0));
        $this->assertNull($this->service->capaianKinerja(null, 20.0));
        $this->assertSame(250.0, $this->service->capaianKinerja(50.0, 20.0));

        config(['spbe.target_indikator.2026' => null]);
        $this->rencanaLengkap();

        $kpi = $this->kpi(2026, 1);
        $this->assertNotSame('–', $kpi[0]['value']); // induk tetap ada
        $this->assertSame('Belum ditetapkan', $kpi[1]['value']);
        $this->assertSame('–', $kpi[2]['value']);
        $this->assertStringContainsString('belum ditetapkan', $kpi[2]['description']);
    }

    // 8
    public function test_triwulan_berjalan_bertanda_sementara(): void
    {
        $this->rencanaLengkap();

        foreach ($this->kpi(2026, 4) as $stat) {
            $this->assertStringEndsWith('TW IV 2026 (sementara)', $stat['label']);
        }
        foreach ($this->kpi(2026, 3) as $stat) {
            $this->assertStringNotContainsString('(sementara)', $stat['label']);
        }
    }

    // 9
    public function test_triwulan_mendatang_tampil_strip_dan_belum_berjalan(): void
    {
        $this->travelTo('2026-05-15');
        $this->rencanaLengkap();

        $tw3 = $this->service->getQuarterProgress(2026, 3);
        $this->assertNull($tw3['persentase_rata_rata_presisi']);
        $this->assertNull($tw3['capaian_kinerja']);

        foreach ($this->kpi(2026, 3) as $stat) {
            $this->assertSame('–', $stat['value']);
            $this->assertStringContainsString('(belum berjalan)', $stat['label']);
            $this->assertStringContainsStringIgnoringCase('belum berjalan', $stat['description']);
        }
    }

    // 10
    public function test_peringatan_x3_tetap_tampil_pada_kpi(): void
    {
        $this->rencanaLengkap();
        $this->buatRencana(2026, 'final', [[$this->unitA, 'jaringan', 9]]);

        $kpi = $this->kpi(2026, 2);

        $this->assertSame('–', $kpi[0]['value']);
        $this->assertSame('–', $kpi[2]['value']);
        $this->assertStringContainsString('2 Rencana TIK', $kpi[0]['description']);
        $this->assertStringContainsString('2 Rencana TIK', $kpi[2]['description']);
        $this->assertStringContainsString('2026', $kpi[2]['description']);
    }

    // 11
    public function test_filter_tahun_dan_triwulan_tetap_mengalir_ke_kpi(): void
    {
        $this->rencanaLengkap();

        Livewire::test(DashboardSpbe::class)
            ->assertSet('triwulan', 4)
            ->fillForm(['triwulan' => 1])
            ->assertSet('triwulan', 1)
            ->fillForm(['tahun' => 2025])
            ->assertSet('triwulan', 1)
            ->assertSet('tahun', 2025);

        $this->assertStringContainsString('TW II 2026', $this->kpi(2026, 2)[0]['label']);
        $this->assertStringContainsString('TW I 2025', $this->kpi(2025, 1)[0]['label']);
    }
}
