<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pegawai extends Model
{
    use HasAuditTrail;

    protected $table = 'pegawai';

    protected $fillable = [
        'nip',
        'nama',
        'jabatan',
        'unit_kerja_id',
    ];

    // Satu pegawai bisa memiliki satu akun user (relasi inverse)
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function perangkat(): HasMany
    {
        return $this->hasMany(Perangkat::class);
    }

    public function aplikasi(): BelongsToMany
    {
        return $this->belongsToMany(Aplikasi::class, 'pegawai_aplikasi')
            ->withPivot('peran_pengguna', 'status_akses', 'tanggal_diberikan', 'keterangan')
            ->withTimestamps();
    }
}
