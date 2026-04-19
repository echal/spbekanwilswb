<?php

namespace App\Filament\Resources\AplikasiResource\Pages;

use App\Exports\AplikasiExport;
use App\Filament\Resources\AplikasiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListAplikasis extends ListRecords
{
    protected static string $resource = AplikasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('download_excel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Auditor']))
                ->action(fn (): BinaryFileResponse => Excel::download(
                    new AplikasiExport(),
                    'aplikasi-' . now()->format('Ymd-His') . '.xlsx'
                )),
        ];
    }
}
