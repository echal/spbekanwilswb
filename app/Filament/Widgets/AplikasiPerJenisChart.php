<?php

namespace App\Filament\Widgets;

use App\Models\Aplikasi;
use Filament\Widgets\ChartWidget;

class AplikasiPerJenisChart extends ChartWidget
{
    protected ?string $heading = 'Distribusi Aplikasi per Jenis';

    protected static ?int $sort = 5;

    // Hanya tampil untuk Operator
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('Operator') ?? false;
    }

    protected function getData(): array
    {
        // Hitung aplikasi per jenis
        $jenisCounts = Aplikasi::query()
            ->selectRaw('jenis, COUNT(*) as total')
            ->groupBy('jenis')
            ->pluck('total', 'jenis');

        $internal = $jenisCounts->get('Internal', 0);
        $nasional = $jenisCounts->get('Nasional', 0);
        $eksternal = $jenisCounts->get('Eksternal', 0);

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Aplikasi',
                    'data' => [$internal, $nasional, $eksternal],
                    'backgroundColor' => [
                        '#06b6d4', // cyan for Internal
                        '#10b981', // green for Nasional
                        '#f59e0b', // yellow for Eksternal
                    ],
                ],
            ],
            'labels' => ['Internal', 'Nasional', 'Eksternal'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
