<?php

namespace App\Filament\Resources\JaringanResource\Pages;

use App\Filament\Resources\JaringanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJaringan extends CreateRecord
{
    protected static string $resource = JaringanResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('create');
    }
}
