<?php

namespace App\Filament\Resources\RealisasiTiks\Pages;

use App\Filament\Resources\RealisasiTiks\RealisasiTikResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRealisasiTik extends CreateRecord
{
    protected static string $resource = RealisasiTikResource::class;

    /**
     * Auto-fill dibuat_oleh dengan user yang login.
     * Status verifikasi selalu mulai dari 'menunggu'.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['dibuat_oleh']       = auth()->id();
        $data['status_verifikasi'] = 'menunggu';

        return $data;
    }
}
