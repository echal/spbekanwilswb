<?php

namespace App\Filament\Widgets;

use App\Services\SpbeEvaluationService;
use Filament\Widgets\ChartWidget;

class SpbeUnitChart extends ChartWidget
{
    protected ?string $heading = 'Capaian per Unit Kerja';

    protected static ?int $sort = 3;

    protected static bool $isDiscovered = false;

    public int $tahun;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Auditor']) ?? false;
    }

    public function mount(): void
    {
        $this->tahun = $this->tahun ?? now()->year;
    }

    protected function getData(): array
    {
        $service = app(SpbeEvaluationService::class);
        $data = $service->getByUnit($this->tahun);

        $labels = $data->pluck('unit_kerja')->toArray();
        $values = $data->pluck('persentase')->toArray();

        $colors = $data->map(function ($row) {
            return match (true) {
                $row->persentase >= 80 => '#10b981',
                $row->persentase >= 60 => '#f59e0b',
                default => '#ef4444',
            };
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Persentase Capaian (%)',
                    'data' => $values,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'ticks' => [
                        'callback' => '(value) => value + "%"',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
