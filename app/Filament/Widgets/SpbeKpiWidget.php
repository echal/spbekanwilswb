<?php

namespace App\Filament\Widgets;

use App\Services\SpbeQuarterlyPerformanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

class SpbeKpiWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected static bool $isDiscovered = false;

    // Reactive: nilai ikut berubah saat filter tahun di DashboardSpbe diganti (hanya dibaca, tidak dimutasi widget)
    #[Reactive]
    public ?int $tahun = null;

    // Reactive: triwulan terpilih di DashboardSpbe
    #[Reactive]
    public ?int $triwulan = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Auditor']) ?? false;
    }

    protected function getStats(): array
    {
        $service = app(SpbeQuarterlyPerformanceService::class);
        $year    = $this->tahun ?? now()->year;

        // Triwulan dari filter halaman; tanpa filter → nilai awal tahun terpilih
        $quarter = $this->triwulan ?? $service->triwulanAwal($year);

        // Ter-cache 5 menit; semua angka (termasuk capaian kinerja) dihitung di service
        $tw      = $service->getQuarterProgress($year, $quarter);
        $periode = 'TW ' . $tw['triwulan'] . ' ' . $year . $this->penandaPeriode($tw['status_periode']);
        $belum   = $tw['status_periode'] === SpbeQuarterlyPerformanceService::PERIODE_BELUM;
        $induk   = $tw['persentase_rata_rata_presisi'];

        return [
            // KPI 1 — Capaian indikator induk s.d. triwulan terpilih (rata-rata sederhana 4 komponen)
            Stat::make('Capaian Indikator Induk s.d. ' . $periode, $this->formatPersen($induk))
                ->description($this->keteranganCapaian($tw))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($service->progressWarna($induk))
                ->chart($this->buildMiniChart($induk ?? 0.0)),

            // KPI 2 — Target kinerja kumulatif triwulan terpilih (config/spbe.php melalui service)
            Stat::make(
                'Target Kinerja Kumulatif ' . $periode,
                match (true) {
                    $belum                        => '–',
                    $tw['target_kumulatif'] === null => 'Belum ditetapkan',
                    default                       => $this->formatPersen($tw['target_kumulatif']),
                }
            )
                ->description($belum
                    ? 'Triwulan belum berjalan'
                    : 'Jumlah target tambahan TW I s.d. TW ' . $tw['triwulan'] . ' (konfigurasi target indikator)')
                ->descriptionIcon('heroicon-m-flag')
                ->color('gray'),

            // KPI 3 — Capaian Kinerja = capaian indikator induk ÷ target kinerja kumulatif × 100 (tidak dibatasi 100%)
            Stat::make('Capaian Kinerja ' . $periode, $this->formatPersen($tw['capaian_kinerja']))
                ->description($this->keteranganKinerja($tw))
                ->descriptionIcon('heroicon-m-scale')
                ->color($tw['capaian_kinerja'] === null ? 'gray' : 'primary'),
        ];
    }

    protected function keteranganKinerja(array $progress): string
    {
        return match (true) {
            $progress['status_periode'] === SpbeQuarterlyPerformanceService::PERIODE_BELUM => 'Triwulan belum berjalan',
            filled($progress['peringatan_target'] ?? null) => $progress['peringatan_target'],
            $progress['persentase_rata_rata_presisi'] === null => 'Capaian indikator induk belum tersedia (target komponen belum lengkap)',
            $progress['capaian_kinerja'] === null => 'Target kinerja kumulatif belum ditetapkan',
            default => 'Capaian indikator induk dibanding target kinerja kumulatif triwulan (bukan persentase realisasi)',
        };
    }

    protected function formatPersen(?float $persentase): string
    {
        return $persentase === null ? '–' : number_format($persentase, 2) . '%';
    }

    protected function penandaPeriode(string $status): string
    {
        return match ($status) {
            SpbeQuarterlyPerformanceService::PERIODE_BERJALAN => ' (sementara)',
            SpbeQuarterlyPerformanceService::PERIODE_BELUM    => ' (belum berjalan)',
            default                                           => '',
        };
    }

    protected function keteranganCapaian(array $progress): string
    {
        $periode = 'Kumulatif ' . Carbon::parse($progress['periode_awal'])->translatedFormat('j M')
            . ' – ' . Carbon::parse($progress['periode_akhir'])->translatedFormat('j M Y');

        if (($progress['status_periode'] ?? null) === SpbeQuarterlyPerformanceService::PERIODE_BELUM) {
            return $periode . ' · triwulan belum berjalan, realisasi belum tersedia';
        }

        if (filled($progress['peringatan_target'] ?? null)) {
            return $periode . ' · ' . $progress['peringatan_target'];
        }

        if ($progress['persentase_rata_rata'] === null) {
            return $periode . ' · target belum lengkap: ' . implode(', ', $progress['komponen_tanpa_target']);
        }

        return $periode . ' · rata-rata 4 komponen terhadap target tahunan';
    }

    protected function buildMiniChart(float $persentase): array
    {
        $filled = (int) round($persentase / 10);
        $chart  = [];
        for ($i = 0; $i < 10; $i++) {
            $chart[] = $i < $filled ? round($persentase) : 0;
        }

        return $chart;
    }
}
