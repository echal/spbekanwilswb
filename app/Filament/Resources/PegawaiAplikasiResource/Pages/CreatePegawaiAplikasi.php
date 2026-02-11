<?php

namespace App\Filament\Resources\PegawaiAplikasiResource\Pages;

use App\Filament\Resources\PegawaiAplikasiResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePegawaiAplikasi extends CreateRecord
{
    protected static string $resource = PegawaiAplikasiResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('create');
    }
}
