<?php

namespace App\Filament\Resources\MonitoringJaringanResource\Pages;

use App\Exports\MonitoringJaringanExport;
use App\Filament\Resources\MonitoringJaringanResource;
use App\Models\Jaringan;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Select;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListMonitoringJaringans extends ListRecords
{
    protected static string $resource = MonitoringJaringanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('download_excel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Operator', 'Auditor']))
                ->form([
                    Select::make('tahun')
                        ->label('Tahun')
                        ->options(fn () => collect(range(now()->year, now()->year - 5))
                            ->mapWithKeys(fn ($y) => [$y => $y])
                            ->toArray()
                        )
                        ->nullable()
                        ->placeholder('Semua Tahun'),

                    Select::make('jaringan_id')
                        ->label('Jaringan')
                        ->options(fn () => Jaringan::orderBy('nama_jaringan')->pluck('nama_jaringan', 'id'))
                        ->nullable()
                        ->placeholder('Semua Jaringan'),
                ])
                ->action(function (array $data): BinaryFileResponse {
                    $filters = array_filter($data);
                    return Excel::download(
                        new MonitoringJaringanExport($filters),
                        'monitoring-jaringan-' . now()->format('Ymd-His') . '.xlsx'
                    );
                }),
        ];
    }
}
