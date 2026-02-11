<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiAplikasi extends Model
{
    use HasAuditTrail;

    protected $table = 'pegawai_aplikasi';

    protected $fillable = [
        'pegawai_id',
        'aplikasi_id',
        'peran_pengguna',
        'status_akses',
        'tanggal_diberikan',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_diberikan' => 'date',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }
}
