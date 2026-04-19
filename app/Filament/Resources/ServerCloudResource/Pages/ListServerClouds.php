<?php

namespace App\Filament\Resources\ServerCloudResource\Pages;

use App\Exports\ServerCloudExport;
use App\Filament\Resources\ServerCloudResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListServerClouds extends ListRecords
{
    protected static string $resource = ServerCloudResource::class;

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
                    new ServerCloudExport(),
                    'server-cloud-' . now()->format('Ymd-His') . '.xlsx'
                )),
        ];
    }
}
