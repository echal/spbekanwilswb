<?php

namespace App\Exports;

use App\Models\MonitoringJaringan;
use Illuminate\Database\Eloquent\Builder;

class MonitoringJaringanExport extends BaseExport
{
    public function title(): string
    {
        return 'Monitoring Jaringan';
    }

    protected function sheetTitle(): string
    {
        return 'LAPORAN MONITORING JARINGAN';
    }

    protected function columnCount(): int
    {
        return 12;
    }

    protected function columnHeadings(): array
    {
        return [
            'No',
            'Jaringan',
            'Tanggal Monitoring',
            'Jumlah Pengguna',
            'Jumlah Perangkat',
            'Upload (Mbps)',
            'Download (Mbps)',
            'Ping (ms)',
            'Status Koneksi',
            'Kendala',
            'Tindak Lanjut',
            'Link Eviden',
        ];
    }

    public function query(): Builder
    {
        return MonitoringJaringan::query()
            ->with('jaringan')
            ->when(isset($this->filters['jaringan_id']), fn($q) =>
                $q->where('jaringan_id', $this->filters['jaringan_id'])
            )
            ->when(isset($this->filters['tahun']), fn($q) =>
                $q->whereYear('tanggal_monitoring', $this->filters['tahun'])
            )
            ->when(isset($this->filters['bulan']), fn($q) =>
                $q->whereMonth('tanggal_monitoring', $this->filters['bulan'])
            )
            ->orderBy('tanggal_monitoring', 'desc');
    }

    public function map($monitoring): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $monitoring->jaringan?->nama_jaringan ?? '-',
            $this->formatDate($monitoring->tanggal_monitoring),
            $monitoring->jumlah_pengguna ?? '-',
            $monitoring->jumlah_perangkat ?? '-',
            $monitoring->upload_speed ?? '-',
            $monitoring->download_speed ?? '-',
            $monitoring->ping ?? '-',
            $monitoring->status_koneksi ?? '-',
            $monitoring->kendala ?? '-',
            $monitoring->tindak_lanjut ?? '-',
            $monitoring->link_eviden ?? '-',
        ];
    }
}
