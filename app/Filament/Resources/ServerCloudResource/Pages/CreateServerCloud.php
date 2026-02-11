<?php

namespace App\Filament\Resources\ServerCloudResource\Pages;

use App\Filament\Resources\ServerCloudResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServerCloud extends CreateRecord
{
    protected static string $resource = ServerCloudResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('create');
    }
}
