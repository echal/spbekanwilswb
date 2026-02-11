<?php

namespace App\Filament\Widgets;

use App\Models\Aplikasi;
use App\Models\Jaringan;
use App\Models\MonitoringJaringan;
use App\Models\Perangkat;
use App\Models\ServerCloud;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SpbeStatsOverview extends BaseWidget
{
    protected static ?int $sort = -2;

    // Dashboard statistik global hanya untuk Admin dan Auditor
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['Admin', 'Auditor']);
    }

    protected function getStats(): array
    {
        $totalPerangkat = Perangkat::count();
        $perangkatBaik = Perangkat::where('kondisi', 'Baik')->count();
        $persentaseBaik = $totalPerangkat > 0
            ? round(($perangkatBaik / $totalPerangkat) * 100, 1)
            : 0;

        $lastMonitoring = MonitoringJaringan::latest('tanggal_monitoring')->first();
        $monitoringDesc = $lastMonitoring
            ? $lastMonitoring->jaringan?->nama_jaringan . ' - ' . $lastMonitoring->status_koneksi
            : 'Belum ada data';

        return [
            Stat::make('Total Perangkat', $totalPerangkat)
                ->description('Semua perangkat terdaftar')
                ->descriptionIcon('heroicon-m-computer-desktop')
                ->color('primary'),
            Stat::make('Total Aplikasi', Aplikasi::count())
                ->description('Aplikasi terdaftar')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('success'),
            Stat::make('Total Layanan Cloud', ServerCloud::count())
                ->description('Server & cloud services')
                ->descriptionIcon('heroicon-m-cloud')
                ->color('info'),
            Stat::make('Total Infrastruktur Jaringan', Jaringan::count())
                ->description('Jaringan terdaftar')
                ->descriptionIcon('heroicon-m-signal')
                ->color('warning'),
            Stat::make('Perangkat Kondisi Baik', $persentaseBaik . '%')
                ->description($perangkatBaik . ' dari ' . $totalPerangkat . ' perangkat')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($persentaseBaik >= 80 ? 'success' : ($persentaseBaik >= 50 ? 'warning' : 'danger')),
            Stat::make('Monitoring Terakhir', $lastMonitoring?->tanggal_monitoring?->format('d/m/Y') ?? '-')
                ->description($monitoringDesc)
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($lastMonitoring?->status_koneksi === 'Stabil' ? 'success' : 'warning'),
        ];
    }
}
