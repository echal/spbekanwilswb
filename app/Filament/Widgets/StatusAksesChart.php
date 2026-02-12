<?php

namespace App\Filament\Widgets;

use App\Models\PegawaiAplikasi;
use Filament\Widgets\ChartWidget;

class StatusAksesChart extends ChartWidget
{
    protected ?string $heading = 'Status Akses Aplikasi';

    protected static ?int $sort = 2;

    // Hanya tampil untuk Operator
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('Operator') ?? false;
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $unitKerjaId = $user?->managed_unit_kerja_id;

        // Hitung status akses untuk pegawai di unit kerja operator
        $statusCounts = PegawaiAplikasi::query()
            ->whereHas('pegawai', fn ($q) => $q->where('unit_kerja_id', $unitKerjaId))
            ->selectRaw('status_akses, COUNT(*) as total')
            ->groupBy('status_akses')
            ->pluck('total', 'status_akses');

        $aktif = $statusCounts->get('Aktif', 0);
        $nonaktif = $statusCounts->get('Nonaktif', 0);

        return [
            'datasets' => [
                [
                    'label' => 'Status Akses',
                    'data' => [$aktif, $nonaktif],
                    'backgroundColor' => [
                        '#10b981', // green for Aktif
                        '#ef4444', // red for Nonaktif
                    ],
                ],
            ],
            'labels' => ['Aktif', 'Nonaktif'],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
