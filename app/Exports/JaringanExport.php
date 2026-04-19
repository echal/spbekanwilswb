<?php

namespace App\Exports;

use App\Models\Jaringan;
use Illuminate\Database\Eloquent\Builder;

class JaringanExport extends BaseExport
{
    public function title(): string
    {
        return 'Data Jaringan';
    }

    protected function sheetTitle(): string
    {
        return 'DAFTAR INFRASTRUKTUR JARINGAN';
    }

    protected function columnCount(): int
    {
        return 6;
    }

    protected function columnHeadings(): array
    {
        return [
            'No',
            'Nama Jaringan',
            'Provider',
            'Bandwidth',
            'Lokasi',
            'Jumlah Monitoring',
        ];
    }

    public function query(): Builder
    {
        return Jaringan::query()
            ->withCount('monitoringJaringan')
            ->orderBy('nama_jaringan');
    }

    public function map($jaringan): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $jaringan->nama_jaringan ?? '-',
            $jaringan->provider ?? '-',
            $jaringan->bandwidth ?? '-',
            $jaringan->lokasi ?? '-',
            $jaringan->monitoring_jaringan_count ?? 0,
        ];
    }
}
