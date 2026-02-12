<?php

namespace App\Filament\Widgets;

use App\Services\SpbeEvaluationService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SpbeGlobalProgressWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    // Tidak auto-discover di default dashboard
    protected static bool $isDiscovered = false;

    // Terima filter tahun dari DashboardSpbe page
    public int $tahun;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Auditor']) ?? false;
    }

    public function mount(): void
    {
        $this->tahun = $this->tahun ?? now()->year;
    }

    protected function getStats(): array
    {
        $service = app(SpbeEvaluationService::class);
        $data = $service->getGlobalProgress($this->tahun);

        return [
            Stat::make('Total Direncanakan', number_format($data['total_direncanakan']))
                ->description('Item infrastruktur TIK')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('Total Terpenuhi', number_format($data['total_terpenuhi']))
                ->description('Item terealisasi')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($data['warna']),

            Stat::make('Capaian SPBE', $data['persentase'] . '%')
                ->description($this->getCapaianLabel($data['persentase']))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($data['warna'])
                ->chart($this->getMiniChart($data['persentase'])),
        ];
    }

    protected function getCapaianLabel(float $persentase): string
    {
        return match (true) {
            $persentase >= 80 => 'Sangat Baik',
            $persentase >= 60 => 'Cukup Baik',
            default => 'Perlu Perbaikan',
        };
    }

    /**
     * Mini chart visual sebagai progress indicator.
     */
    protected function getMiniChart(float $persentase): array
    {
        $filled = (int) round($persentase / 10);
        $chart = [];
        for ($i = 0; $i < 10; $i++) {
            $chart[] = $i < $filled ? $persentase : 0;
        }

        return $chart;
    }
}
