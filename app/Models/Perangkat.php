<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Perangkat extends Model
{
    use HasAuditTrail;

    protected $table = 'perangkat';

    protected $fillable = [
        'kode_inventaris',
        'jenis_perangkat',
        'merek',
        'tipe',
        'processor',
        'ram',
        'penyimpanan',
        'os',
        'ip_address',
        'kondisi',
        'pegawai_id',
        'ruangan_id',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }
}
