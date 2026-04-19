<?php

namespace App\Exports;

use App\Models\ServerCloud;
use Illuminate\Database\Eloquent\Builder;

class ServerCloudExport extends BaseExport
{
    public function title(): string
    {
        return 'Data Server & Cloud';
    }

    protected function sheetTitle(): string
    {
        return 'DAFTAR SERVER DAN LAYANAN CLOUD';
    }

    protected function columnCount(): int
    {
        return 9;
    }

    protected function columnHeadings(): array
    {
        return [
            'No',
            'Jenis Layanan',
            'Nama Layanan',
            'Provider',
            'Status Kepemilikan',
            'Nomor Kontrak',
            'Masa Berlaku',
            'Jumlah User',
            'Kategori Data',
        ];
    }

    public function query(): Builder
    {
        return ServerCloud::query()
            ->when(isset($this->filters['jenis_layanan']), fn($q) =>
                $q->where('jenis_layanan', $this->filters['jenis_layanan'])
            )
            ->orderBy('jenis_layanan')
            ->orderBy('nama_layanan');
    }

    public function map($server): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $server->jenis_layanan ?? '-',
            $server->nama_layanan ?? '-',
            $server->provider ?? '-',
            $server->status_kepemilikan ?? '-',
            $server->nomor_kontrak ?? '-',
            $this->formatDate($server->masa_berlaku),
            $server->jumlah_user ?? '-',
            $server->kategori_data ?? '-',
        ];
    }
}
