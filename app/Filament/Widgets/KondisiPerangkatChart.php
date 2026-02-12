<?php

namespace App\Filament\Widgets;

use App\Models\Perangkat;
use Filament\Widgets\ChartWidget;

class KondisiPerangkatChart extends ChartWidget
{
    protected ?string $heading = 'Kondisi Perangkat';

    protected static ?int $sort = 3;

    // Hanya tampil untuk Operator
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('Operator') ?? false;
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $unitKerjaId = $user?->managed_unit_kerja_id;

        // Hitung kondisi perangkat untuk unit kerja operator
        $kondisiCounts = Perangkat::query()
            ->where(function ($q) use ($unitKerjaId) {
                $q->whereHas('pegawai', fn ($sub) => $sub->where('unit_kerja_id', $unitKerjaId))
                  ->orWhereHas('ruangan', fn ($sub) => $sub->where('unit_kerja_id', $unitKerjaId));
            })
            ->selectRaw('kondisi, COUNT(*) as total')
            ->groupBy('kondisi')
            ->pluck('total', 'kondisi');

        $baik = $kondisiCounts->get('Baik', 0);
        $rusakRingan = $kondisiCounts->get('Rusak Ringan', 0);
        $rusakBerat = $kondisiCounts->get('Rusak Berat', 0);

        return [
            'datasets' => [
                [
                    'label' => 'Kondisi Perangkat',
                    'data' => [$baik, $rusakRingan, $rusakBerat],
                    'backgroundColor' => [
                        '#10b981', // green for Baik
                        '#f59e0b', // yellow for Rusak Ringan
                        '#ef4444', // red for Rusak Berat
                    ],
                ],
            ],
            'labels' => ['Baik', 'Rusak Ringan', 'Rusak Berat'],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
