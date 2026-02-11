<?php

namespace App\Filament\Resources\MonitoringJaringanResource\Pages;

use App\Filament\Resources\MonitoringJaringanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMonitoringJaringan extends CreateRecord
{
    protected static string $resource = MonitoringJaringanResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('create');
    }
}
