<?php

namespace App\Exports;

use App\Models\Perangkat;
use Illuminate\Database\Eloquent\Builder;

class PerangkatExport extends BaseExport
{
    public function title(): string
    {
        return 'Data Perangkat';
    }

    protected function sheetTitle(): string
    {
        return 'DAFTAR INVENTARIS PERANGKAT TIK';
    }

    protected function columnCount(): int
    {
        return 11;
    }

    protected function columnHeadings(): array
    {
        return [
            'No',
            'Kode Inventaris',
            'Jenis Perangkat',
            'Merek',
            'Tipe',
            'Kondisi',
            'Status Kepemilikan',
            'IP Address',
            'Pegawai Pengguna',
            'Ruangan',
            'Unit Kerja',
        ];
    }

    public function query(): Builder
    {
        return Perangkat::query()
            ->with(['pegawai.unitKerja', 'ruangan.unitKerja'])
            ->when(isset($this->filters['unit_kerja_id']), fn($q) =>
                $q->whereHas('ruangan', fn($q) => $q->where('unit_kerja_id', $this->filters['unit_kerja_id']))
                  ->orWhereHas('pegawai', fn($q) => $q->where('unit_kerja_id', $this->filters['unit_kerja_id']))
            )
            ->orderBy('kode_inventaris');
    }

    public function map($perangkat): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $perangkat->kode_inventaris ?? '-',
            $perangkat->jenis_perangkat ?? '-',
            $perangkat->merek ?? '-',
            $perangkat->tipe ?? '-',
            $perangkat->kondisi ?? '-',
            $perangkat->status_kepemilikan ?? '-',
            $perangkat->ip_address ?? '-',
            $perangkat->pegawai?->nama ?? '-',
            $perangkat->ruangan?->nama_ruangan ?? '-',
            $perangkat->pegawai?->unitKerja?->nama_unit ?? $perangkat->ruangan?->unitKerja?->nama_unit ?? '-',
        ];
    }
}
