<?php

namespace App\Exports;

use App\Models\Ruangan;
use Illuminate\Database\Eloquent\Builder;

class RuanganExport extends BaseExport
{
    public function title(): string
    {
        return 'Data Ruangan';
    }

    protected function sheetTitle(): string
    {
        return 'DAFTAR RUANGAN DAN FASILITAS';
    }

    protected function columnCount(): int
    {
        return 4;
    }

    protected function columnHeadings(): array
    {
        return [
            'No',
            'Nama Ruangan',
            'Unit Kerja',
            'Jumlah Perangkat',
        ];
    }

    public function query(): Builder
    {
        return Ruangan::query()
            ->with('unitKerja')
            ->withCount('perangkat')
            ->when(isset($this->filters['unit_kerja_id']), fn($q) =>
                $q->where('unit_kerja_id', $this->filters['unit_kerja_id'])
            )
            ->orderBy('unit_kerja_id')
            ->orderBy('nama_ruangan');
    }

    public function map($ruangan): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $ruangan->nama_ruangan ?? '-',
            $ruangan->unitKerja?->nama_unit ?? '-',
            $ruangan->perangkat_count ?? 0,
        ];
    }
}
