<?php

namespace App\Filament\Resources\RuanganResource\Pages;

use App\Exports\RuanganExport;
use App\Filament\Resources\RuanganResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListRuangan extends ListRecords
{
    protected static string $resource = RuanganResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        return [
            Actions\CreateAction::make(),

            Actions\Action::make('download_excel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => $user?->hasAnyRole(['Admin', 'Operator', 'Auditor']))
                ->action(function (): BinaryFileResponse {
                    $user = auth()->user();
                    $filters = [];
                    if ($user?->hasRole('Operator') && $user->managed_unit_kerja_id) {
                        $filters['unit_kerja_id'] = $user->managed_unit_kerja_id;
                    }
                    return Excel::download(
                        new RuanganExport($filters),
                        'ruangan-' . now()->format('Ymd-His') . '.xlsx'
                    );
                }),
        ];
    }
}
