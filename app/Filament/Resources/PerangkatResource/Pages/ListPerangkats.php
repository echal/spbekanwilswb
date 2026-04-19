<?php

namespace App\Filament\Resources\PerangkatResource\Pages;

use App\Exports\PerangkatExport;
use App\Filament\Resources\PerangkatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListPerangkats extends ListRecords
{
    protected static string $resource = PerangkatResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        return [
            Actions\CreateAction::make(),

            Actions\Action::make('download_excel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => $user?->hasAnyRole(['Admin', 'Auditor']))
                ->action(function (): BinaryFileResponse {
                    $user = auth()->user();
                    $filters = [];
                    if ($user?->hasRole('Operator') && $user->managed_unit_kerja_id) {
                        $filters['unit_kerja_id'] = $user->managed_unit_kerja_id;
                    }
                    return Excel::download(
                        new PerangkatExport($filters),
                        'perangkat-' . now()->format('Ymd-His') . '.xlsx'
                    );
                }),
        ];
    }
}
