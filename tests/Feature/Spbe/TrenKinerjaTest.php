<?php

namespace Tests\Feature\Spbe;

use App\Filament\Widgets\SpbeKpiWidget;
use App\Filament\Widgets\SpbeTrenKinerjaChart;
use App\Models\RencanaTik;
use App\Services\SpbeQuarterlyPerformanceService;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use ReflectionMethod;

/**
 * Pekerjaan D: grafik tren kinerja TW I–IV. Semua angka adalah fixture uji.
 */
class TrenKinerjaTest extends SpbeTestCase
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
        $this->buatRealisasi($item('perangkat'), 2, 'disetujui', '2026-03-10');
        $this->buatRealisasi($item('perangkat'), 6, 'disetujui', '2026-08-01'); // s.d. TW III: 8/7 > 100%
        if ($denganServer) {
            $this->buatRealisasi($item('server'), 1, 'disetujui', '2026-01-15');
        }
        $this->beriAkses($this->buatPegawai(1)->first(), $this->buatAplikasi('App'), '2026-01-20');

        return $r;
    }

    protected function grafik(int $tahun, ?int $triwulan = null): array
    {
        $instance = Livewire::test(SpbeTrenKinerjaChart::class, array_filter(['tahun' => $tahun, 'triwulan' => $triwulan]))->instance();

        return (new ReflectionMethod($instance, 'getData'))->invoke($instance);
    }

    protected function seri(array $data, string $label): array
    {
        return collect($data['datasets'])->firstWhere('label', $label);
    }

    // 1 + 7
    public function test_empat_kategori_triwulan_dan_penanda_sementara(): void
    {
        $this->fixture();

        $data = $this->grafik(2026);

        $this->assertSame(['TW I', 'TW II', 'TW III', 'TW IV (sementara)'], $data['labels']);
        $this->assertSame(
            ['selesai', 'selesai', 'selesai', 'berjalan'],
            array_column($this->service->trenTahunan(2026)['titik'], 'status_periode'),
        );
    }

    // 2
    public function test_seri_target_dari_konfigurasi_kumulatif(): void
    {
        $this->fixture();

        $this->assertSame([20.0, 60.0, 75.0, 85.0], $this->seri($this->grafik(2026), 'Target Kinerja Kumulatif (%)')['data']);
    }

    public function test_seri_target_mengikuti_perubahan_konfigurasi(): void
    {
        config(['spbe.target_indikator.2026.tambahan_triwulan' => [1 => 10.0, 2 => 10.0, 3 => 10.0, 4 => 10.0]]);
        $this->fixture();

        $this->assertSame([10.0, 20.0, 30.0, 40.0], $this->seri($this->grafik(2026), 'Target Kinerja Kumulatif (%)')['data']);
    }

    // 3
    public function test_seri_aktual_konsisten_dengan_service_dan_kpi(): void
    {
        $this->fixture();

        $induk = $this->seri($this->grafik(2026), 'Capaian Indikator Induk (%)');

        foreach ([1, 2, 3, 4] as $tw) {
            $progress = $this->service->getQuarterProgress(2026, $tw);
            $this->assertSame($progress['persentase_rata_rata_presisi'], $induk['data'][$tw - 1], "TW {$tw}");
            $this->assertSame($progress['capaian_kinerja'], $induk['capaianKinerja'][$tw - 1], "TW {$tw}");

            // Sama dengan kartu KPI 1 Pekerjaan B
            $kpi = Livewire::test(SpbeKpiWidget::class, ['tahun' => 2026, 'triwulan' => $tw])->instance();
            $stat = (new ReflectionMethod($kpi, 'getStats'))->invoke($kpi)[0];
            $this->assertInstanceOf(Stat::class, $stat);
            $this->assertSame(number_format($induk['data'][$tw - 1], 2) . '%', (string) $stat->getValue());
        }
    }

    // 4
    public function test_nilai_di_atas_100_persen_tidak_dibatasi(): void
    {
        $this->fixture();

        $data = $this->grafik(2026);
        $perangkat = $this->seri($data, 'Capaian Perangkat Pegawai (%)')['data'];
        $this->assertGreaterThan(100, $perangkat[2]); // 8/7

        // Capaian kinerja TW I (tooltip) > 100%
        $this->assertGreaterThan(100, $this->seri($data, 'Capaian Indikator Induk (%)')['capaianKinerja'][0]);

        // Sumbu tanpa batas maksimum tetap
        $widget = Livewire::test(SpbeTrenKinerjaChart::class, ['tahun' => 2026])->instance();
        $opsi = (string) (new ReflectionMethod($widget, 'getOptions'))->invoke($widget);
        $this->assertStringContainsString('suggestedMax: 100', $opsi);
        $this->assertDoesNotMatchRegularExpression('/(?<![A-Za-z])max\s*:/', $opsi);
    }

    // 5 + 8 (komponen tanpa target)
    public function test_nilai_null_tetap_null_saat_komponen_tanpa_target(): void
    {
        $this->fixture(denganServer: false);

        $data = $this->grafik(2026);

        $this->assertSame([null, null, null, null], $this->seri($data, 'Capaian Indikator Induk (%)')['data']);
        $this->assertSame([null, null, null, null], $this->seri($data, 'Capaian Indikator Induk (%)')['capaianKinerja']);
        $this->assertSame([null, null, null, null], $this->seri($data, 'Capaian Cloud Server (%)')['data']);
        $this->assertFalse($this->seri($data, 'Capaian Indikator Induk (%)')['spanGaps']);
    }

    // 6
    public function test_triwulan_mendatang_tanpa_nilai_aktual(): void
    {
        $this->travelTo('2026-05-15');
        $this->fixture();

        $data = $this->grafik(2026);
        $induk = $this->seri($data, 'Capaian Indikator Induk (%)')['data'];

        $this->assertNotNull($induk[0]);
        $this->assertNotNull($induk[1]); // TW II berjalan
        $this->assertNull($induk[2]);
        $this->assertNull($induk[3]);
        $this->assertSame(['TW I', 'TW II (sementara)', 'TW III (belum berjalan)', 'TW IV (belum berjalan)'], $data['labels']);

        // Target triwulan mendatang tetap boleh tampil sebagai target
        $this->assertSame([20.0, 60.0, 75.0, 85.0], $this->seri($data, 'Target Kinerja Kumulatif (%)')['data']);

        // Garis komponen juga tidak digambar untuk triwulan mendatang
        $this->assertNull($this->seri($data, 'Capaian Jaringan (%)')['data'][2]);
    }

    // 8 (X3)
    public function test_ambiguitas_x3_membuat_capaian_tidak_tersedia(): void
    {
        $this->fixture();
        $this->buatRencana(2026, 'final', [[$this->unitA, 'jaringan', 9]]);

        $data = $this->grafik(2026);

        $this->assertSame([null, null, null, null], $this->seri($data, 'Capaian Indikator Induk (%)')['data']);

        $widget = Livewire::test(SpbeTrenKinerjaChart::class, ['tahun' => 2026])->instance();
        $this->assertStringContainsString('2 Rencana TIK', $widget->getDescription());
    }

    // 9
    public function test_filter_tahun_mengubah_rangkaian_dan_triwulan_hanya_penanda(): void
    {
        $this->fixture();

        $data2025 = $this->grafik(2025);
        $this->assertSame(['TW I', 'TW II', 'TW III', 'TW IV'], $data2025['labels']);
        $this->assertSame([null, null, null, null], $this->seri($data2025, 'Capaian Indikator Induk (%)')['data']);
        $this->assertSame([null, null, null, null], $this->seri($data2025, 'Target Kinerja Kumulatif (%)')['data']);

        $widget = Livewire::test(SpbeTrenKinerjaChart::class, ['tahun' => 2025])->instance();
        $this->assertSame('Tren Kinerja per Triwulan 2025', $widget->getHeading());
        $this->assertStringContainsString('Belum ada data', $widget->getDescription());

        // Triwulan terpilih tidak menyaring rangkaian, hanya memperbesar titiknya
        $tw2 = $this->seri($this->grafik(2026, 2), 'Capaian Indikator Induk (%)');
        $this->assertCount(4, $tw2['data']);
        $this->assertSame([4, 7, 4, 4], $tw2['pointRadius']);
    }

    public function test_keterangan_keterbatasan_historis_dan_bukan_baseline(): void
    {
        $this->fixture();

        $deskripsi = Livewire::test(SpbeTrenKinerjaChart::class, ['tahun' => 2026])->instance()->getDescription();

        $this->assertStringContainsString('bukan perbandingan dengan baseline 2025', $deskripsi);
        $this->assertStringContainsString('bukan snapshot historis terverifikasi', $deskripsi);

        // Empat garis komponen tersedia tetapi tersembunyi secara bawaan
        $komponen = collect($this->grafik(2026)['datasets'])->filter(fn ($d) => str_starts_with($d['label'], 'Capaian ') && $d['label'] !== 'Capaian Indikator Induk (%)');
        $this->assertCount(4, $komponen);
        $this->assertTrue($komponen->every(fn ($d) => $d['hidden'] === true));
    }
}
