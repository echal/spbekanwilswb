<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Aplikasi extends Model
{
    use HasAuditTrail;

    protected $table = 'aplikasi';

    protected $fillable = [
        'nama_aplikasi',
        'jenis',
        'basis',
        'tingkat_kritis',
    ];

    public function pegawai(): BelongsToMany
    {
        return $this->belongsToMany(Pegawai::class, 'pegawai_aplikasi')
            ->withPivot('peran_pengguna', 'status_akses', 'tanggal_diberikan', 'keterangan')
            ->withTimestamps();
    }
}
