<?php

namespace App\Filament\Resources\MonitoringJaringanResource\Pages;

use App\Filament\Resources\MonitoringJaringanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMonitoringJaringans extends ListRecords
{
    protected static string $resource = MonitoringJaringanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
