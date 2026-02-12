<?php

namespace App\Filament\Resources\RencanaTiks\Pages;

use App\Filament\Resources\RencanaTiks\RencanaTikResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRencanaTik extends CreateRecord
{
    protected static string $resource = RencanaTikResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set created_by to current user
        $data['created_by'] = auth()->id();

        return $data;
    }
}
