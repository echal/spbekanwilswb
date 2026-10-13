<?php

namespace App\Filament\Resources\RencanaTiks\Pages;

use App\Filament\Resources\RencanaTiks\RencanaTikResource;
use App\Support\IntegritasRealisasi;
use Illuminate\Validation\ValidationException;
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

    /**
     * Dijalankan setelah validasi dan SEBELUM relasi repeater disimpan
     * (relasi disimpan di dalam form->getState(), mendahului mutateFormDataBeforeSave()).
     */
    protected function beforeSave(): void
    {
        // Cek ulang otorisasi saat menyimpan dengan data terbaru:
        // status rencana bisa berubah setelah halaman edit dibuka.
        abort_unless(static::getResource()::canEdit($this->record->fresh()), 403);

        // Lock jika status = final
        if ($this->record->status === 'final') {
            abort(403, 'Data sudah final dan tidak bisa diubah.');
        }

        // Rencana yang sudah memiliki realisasi tidak boleh berganti tahun atau turun ke draft
        // (state request diperiksa langsung, tidak bergantung pada field yang dikunci di UI)
        $pesan = IntegritasRealisasi::pesanPerubahanRencana(
            $this->record,
            (int) $this->record->tahun,
            (string) $this->record->status,
            (int) ($this->data['tahun'] ?? $this->record->tahun),
            (string) ($this->data['status'] ?? $this->record->status),
        );

        if ($pesan !== []) {
            throw ValidationException::withMessages(
                collect($pesan)->mapWithKeys(fn (string $isi, string $field) => ["data.{$field}" => $isi])->all()
            );
        }

        // Item yang sudah memiliki realisasi tidak boleh dihapus atau diubah kategorinya
        IntegritasRealisasi::validasiStateItems($this->record, $this->data['items'] ?? []);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
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
}
