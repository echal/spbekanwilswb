<?php

namespace App\Filament\Resources\ServerCloudResource\Pages;

use App\Filament\Resources\ServerCloudResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServerCloud extends EditRecord
{
    protected static string $resource = ServerCloudResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
