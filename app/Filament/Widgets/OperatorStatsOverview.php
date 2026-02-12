<?php

namespace App\Filament\Widgets;

use App\Models\Aplikasi;
use App\Models\PegawaiAplikasi;
use App\Models\MonitoringJaringan;
use App\Models\Perangkat;
use App\Models\ServerCloud;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperatorStatsOverview extends StatsOverviewWidget
{
    // Hanya tampil untuk Operator
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('Operator') ?? false;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $unitKerjaId = $user?->managed_unit_kerja_id;

        // Query perangkat berdasarkan unit kerja operator
        $totalPerangkat = Perangkat::query()
            ->where(function ($q) use ($unitKerjaId) {
                $q->whereHas('pegawai', fn ($sub) => $sub->where('unit_kerja_id', $unitKerjaId))
                  ->orWhereHas('ruangan', fn ($sub) => $sub->where('unit_kerja_id', $unitKerjaId));
            })
            ->count();

        // Total akses aplikasi untuk pegawai di unit kerja operator
        $totalAkses = PegawaiAplikasi::query()
            ->whereHas('pegawai', fn ($q) => $q->where('unit_kerja_id', $unitKerjaId))
            ->count();

        // Total monitoring jaringan (semua, karena jaringan adalah infrastruktur bersama)
        $totalMonitoring = MonitoringJaringan::query()
            ->whereMonth('tanggal_monitoring', now()->month)
            ->count();

        return [
            Stat::make('Total Perangkat', $totalPerangkat)
                ->description('Perangkat di unit kerja Anda')
                ->descriptionIcon('heroicon-o-computer-desktop')
                ->color('success'),

            Stat::make('Total Akses Aplikasi', $totalAkses)
                ->description('Akses aplikasi pegawai')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('info'),

            Stat::make('Total Aplikasi', Aplikasi::count())
                ->description('Aplikasi terdaftar')
                ->descriptionIcon('heroicon-o-squares-2x2')
                ->color('warning'),

            Stat::make('Monitoring Bulan Ini', $totalMonitoring)
                ->description('Monitoring jaringan aktif')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('primary'),
        ];
    }
}
