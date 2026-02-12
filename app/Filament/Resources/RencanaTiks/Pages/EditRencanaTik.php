<?php

namespace App\Filament\Resources\RencanaTiks\Pages;

use App\Filament\Resources\RencanaTiks\RencanaTikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRencanaTik extends EditRecord
{
    protected static string $resource = RencanaTikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasRole('Admin')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Lock jika status = final
        if ($this->record->status === 'final') {
            abort(403, 'Data sudah final dan tidak bisa diubah.');
        }

        $originalStatus = $this->record->status;

        // Operator tidak bisa ubah status
        if (! auth()->user()->hasRole('Admin')) {
            $data['status'] = $this->record->status;
        }

        // Jika status berubah ke disetujui, set approved_by
        if ($originalStatus !== 'disetujui' && $data['status'] === 'disetujui') {
            $data['approved_by'] = auth()->id();
        }

        return $data;
    }

    // Operator hanya bisa edit jika status = draft
    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if ($user?->hasRole('Admin')) {
            return true;
        }

        if ($user?->hasRole('Operator')) {
            return $record->status === 'draft';
        }

        return false;
    }
}
