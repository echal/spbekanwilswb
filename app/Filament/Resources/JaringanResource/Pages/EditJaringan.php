<?php

namespace App\Filament\Resources\JaringanResource\Pages;

use App\Filament\Resources\JaringanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJaringan extends EditRecord
{
    protected static string $resource = JaringanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
