<?php

namespace App\Filament\Widgets;

use App\Services\SpbeEvaluationService;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

class SpbeKategoriChart extends ChartWidget
{
    protected ?string $heading = 'Capaian per Kategori';

    protected static ?int $sort = 2;

    protected static bool $isDiscovered = false;

    // Reactive: nilai ikut berubah saat filter tahun di DashboardSpbe diganti (hanya dibaca, tidak dimutasi widget)
    #[Reactive]
    public ?int $tahun = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Auditor']) ?? false;
    }

    /**
     * Grafik ini belum mengikuti filter triwulan: tampil sebagai posisi tahunan.
     */
    public function getDescription(): ?string
    {
        return 'Posisi tahunan (kumulatif s.d. 31 Desember ' . ($this->tahun ?? now()->year) . '), tidak mengikuti filter triwulan.';
    }

    protected function getData(): array
    {
        $service = app(SpbeEvaluationService::class);
        $data = $service->getByKategori($this->tahun ?? now()->year);

        $labels = $data->pluck('kategori')->map(fn ($k) => ucfirst($k))->toArray();
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

    /**
     * RawJs agar callback dikirim sebagai fungsi JavaScript; opsi berbentuk array
     * di-encode sebagai JSON sehingga callback berupa string tidak dijalankan Chart.js.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: (value) => value + '%',
                        },
                    },
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                },
            }
        JS);
    }
}
