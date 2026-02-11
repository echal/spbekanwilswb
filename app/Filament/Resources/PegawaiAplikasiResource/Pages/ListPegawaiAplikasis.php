<?php

namespace App\Filament\Resources\PegawaiAplikasiResource\Pages;

use App\Filament\Resources\PegawaiAplikasiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiAplikasis extends ListRecords
{
    protected static string $resource = PegawaiAplikasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
