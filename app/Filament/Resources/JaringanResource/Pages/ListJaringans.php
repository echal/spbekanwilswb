<?php

namespace App\Filament\Resources\JaringanResource\Pages;

use App\Exports\JaringanExport;
use App\Filament\Resources\JaringanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListJaringans extends ListRecords
{
    protected static string $resource = JaringanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('download_excel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Operator', 'Auditor']))
                ->action(fn (): BinaryFileResponse => Excel::download(
                    new JaringanExport(),
                    'jaringan-' . now()->format('Ymd-His') . '.xlsx'
                )),
        ];
    }
}
