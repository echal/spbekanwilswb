<?php

namespace App\Filament\Resources\RencanaTiks\Pages;

use App\Filament\Resources\RencanaTiks\RencanaTikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRencanaTiks extends ListRecords
{
    protected static string $resource = RencanaTikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
