<?php

namespace App\Filament\Widgets;

use App\Services\SpbeEvaluationService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class SpbeGlobalProgressWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    // Tidak auto-discover di default dashboard
    protected static bool $isDiscovered = false;

    // Terima filter tahun dari DashboardSpbe page
    // Reactive: nilai ikut berubah saat filter tahun di DashboardSpbe diganti (hanya dibaca, tidak dimutasi widget)
    #[Reactive]
    public ?int $tahun = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Auditor']) ?? false;
    }

    protected function getStats(): array
    {
        $service = app(SpbeEvaluationService::class);
        $data = $service->getGlobalProgress($this->tahun ?? now()->year);

        // Rincian per komponen; tidak dijumlahkan karena satuan tiap komponen berbeda.
        // realisasi_kumulatif null = periode belum berjalan (bukan nol).
        $stats = collect($data['komponen'])
            ->map(fn (array $k) => Stat::make(
                $k['label'],
                ($k['realisasi_kumulatif'] === null ? '–' : number_format($k['realisasi_kumulatif']))
                    . ' / ' . ($k['target_tahunan'] > 0 ? number_format($k['target_tahunan']) : '–')
            )
                ->description(match (true) {
                    $k['realisasi_kumulatif'] === null => 'Belum berjalan',
                    $k['persentase'] === null          => 'Target belum ada',
                    default                            => $k['persentase'] . '%',
                } . ' · ' . $k['satuan'])
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($k['persentase'] === null ? 'gray' : 'primary'))
            ->values()
            ->all();

        return [
            ...$stats,

            Stat::make('Capaian SPBE', $data['persentase'] === null ? '–' : $data['persentase'] . '%')
                ->description($data['persentase'] === null
                    ? ($data['peringatan_target'] ?? null)
                        ?: (collect($data['komponen'])->every(fn (array $k) => $k['realisasi_kumulatif'] === null)
                            ? 'Tahun belum berjalan'
                            : 'Target belum lengkap: ' . implode(', ', $data['komponen_tanpa_target']))
                    : $this->getCapaianLabel($data['persentase']) . ' · rata-rata 4 komponen')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($data['warna'])
                ->chart($this->getMiniChart($data['persentase'] ?? 0.0)),
        ];
    }

    /**
     * Widget ini belum mengikuti filter triwulan: tampil sebagai posisi tahunan.
     */
    protected function getDescription(): ?string
    {
        return 'Posisi tahunan (kumulatif s.d. 31 Desember ' . ($this->tahun ?? now()->year) . '), tidak mengikuti filter triwulan.';
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
