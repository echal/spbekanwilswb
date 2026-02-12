<?php

namespace App\Filament\Widgets;

use App\Models\Perangkat;
use App\Models\UnitKerja;
use Filament\Widgets\ChartWidget;

class PerangkatPerUnitChart extends ChartWidget
{
    protected ?string $heading = 'Distribusi Perangkat per Unit Kerja';

    protected static ?int $sort = 4;

    // Hanya tampil untuk Operator
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('Operator') ?? false;
    }

    protected function getData(): array
    {
        // Ambil semua unit kerja
        $unitKerjas = UnitKerja::all();

        $labels = [];
        $data = [];

        foreach ($unitKerjas as $unit) {
            $labels[] = $unit->nama_unit;

            // Hitung perangkat per unit kerja
            $count = Perangkat::query()
                ->where(function ($q) use ($unit) {
                    $q->whereHas('pegawai', fn ($sub) => $sub->where('unit_kerja_id', $unit->id))
                      ->orWhereHas('ruangan', fn ($sub) => $sub->where('unit_kerja_id', $unit->id));
                })
                ->count();

            $data[] = $count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Perangkat',
                    'data' => $data,
                    'backgroundColor' => '#3b82f6', // blue
                    'borderColor' => '#2563eb',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
