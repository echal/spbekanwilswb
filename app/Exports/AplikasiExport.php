<?php

namespace App\Exports;

use App\Models\Aplikasi;
use Illuminate\Database\Eloquent\Builder;

class AplikasiExport extends BaseExport
{
    public function title(): string
    {
        return 'Data Aplikasi';
    }

    protected function sheetTitle(): string
    {
        return 'DAFTAR APLIKASI PEMERINTAH';
    }

    protected function columnCount(): int
    {
        return 8;
    }

    protected function columnHeadings(): array
    {
        return [
            'No',
            'Nama Aplikasi',
            'Jenis',
            'Basis',
            'Tingkat Kritis',
            'URL Aplikasi',
            'Jumlah Pengguna',
            'Link Eviden',
        ];
    }

    public function query(): Builder
    {
        return Aplikasi::query()
            ->withCount('pegawai')
            ->orderBy('nama_aplikasi');
    }

    public function map($aplikasi): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $aplikasi->nama_aplikasi ?? '-',
            $aplikasi->jenis ?? '-',
            $aplikasi->basis ?? '-',
            $aplikasi->tingkat_kritis ?? '-',
            $aplikasi->url_aplikasi ?? '-',
            $aplikasi->pegawai_count ?? 0,
            $aplikasi->link_eviden ?? '-',
        ];
    }
}
