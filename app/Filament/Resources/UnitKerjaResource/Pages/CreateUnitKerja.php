<?php

namespace App\Filament\Resources\UnitKerjaResource\Pages;

use App\Filament\Resources\UnitKerjaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUnitKerja extends CreateRecord
{
    protected static string $resource = UnitKerjaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('create');
    }
}
