<?php

namespace App\Filament\Resources\RealisasiTiks\Pages;

use App\Filament\Resources\RealisasiTiks\RealisasiTikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRealisasiTik extends EditRecord
{
    protected static string $resource = RealisasiTikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasRole('Admin') || $this->record->isEditable()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Cek ulang otorisasi saat menyimpan dengan data terbaru:
        // status verifikasi bisa berubah setelah halaman edit dibuka.
        abort_unless(static::getResource()::canEdit($this->record->fresh()), 403);

        // Tidak bisa mengubah realisasi yang sudah diverifikasi
        if ($this->record->isVerified()) {
            abort(403, 'Realisasi yang sudah diverifikasi tidak dapat diubah.');
        }

        // Operator tidak bisa mengubah status verifikasi
        if (! auth()->user()?->hasRole('Admin')) {
            $data['status_verifikasi'] = $this->record->status_verifikasi;
        }

        return $data;
    }
}
