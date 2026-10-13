<?php

namespace App\Filament\Widgets;

use App\Services\SpbeEvaluationService;
use App\Services\SpbeQuarterlyPerformanceService;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

class SpbeUnitChart extends ChartWidget
{
    protected ?string $heading = 'Capaian per Unit Kerja';

    protected static ?int $sort = 3;

    protected static bool $isDiscovered = false;

    // Reactive: nilai ikut berubah saat filter tahun di DashboardSpbe diganti (hanya dibaca, tidak dimutasi widget)
    #[Reactive]
    public ?int $tahun = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Auditor']) ?? false;
    }

    public function getDescription(): ?string
    {
        $tahun = $this->tahun ?? now()->year;
        $keterangan = "Posisi tahunan {$tahun}, tidak mengikuti filter triwulan. "
            . 'Komponen Aplikasi tidak disertakan sementara karena target belum dialokasikan per unit.';

        if ($peringatan = app(SpbeQuarterlyPerformanceService::class)->sumberTarget($tahun)['peringatan']) {
            return $peringatan . ' ' . $keterangan;
        }

        if (app(SpbeEvaluationService::class)->getByUnit($tahun)->isEmpty()) {
            return 'Belum ada rencana per unit untuk tahun ini. ' . $keterangan;
        }

        return $keterangan;
    }

    protected function getData(): array
    {
        $service = app(SpbeEvaluationService::class);
        $data = $service->getByUnit($this->tahun ?? now()->year);

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

    /**
     * RawJs agar callback dikirim sebagai fungsi JavaScript; opsi berbentuk array
     * di-encode sebagai JSON sehingga callback berupa string tidak dijalankan Chart.js.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                indexAxis: 'y',
                scales: {
                    x: {
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
