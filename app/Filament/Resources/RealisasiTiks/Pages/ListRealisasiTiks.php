<?php

namespace App\Filament\Resources\RealisasiTiks\Pages;

use App\Filament\Resources\RealisasiTiks\RealisasiTikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRealisasiTiks extends ListRecords
{
    protected static string $resource = RealisasiTikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Operator'])),
        ];
    }
}
