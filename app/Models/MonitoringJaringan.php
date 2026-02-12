<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringJaringan extends Model
{
    use HasAuditTrail;

    protected $table = 'monitoring_jaringan';

    protected $fillable = [
        'jaringan_id',
        'tanggal_monitoring',
        'jumlah_pengguna',
        'jumlah_perangkat',
        'upload_speed',
        'download_speed',
        'ping',
        'status_koneksi',
        'kendala',
        'tindak_lanjut',
        'link_eviden',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_monitoring' => 'date',
        ];
    }

    public function jaringan(): BelongsTo
    {
        return $this->belongsTo(Jaringan::class);
    }
}
