<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitKerja extends Model
{
    use HasAuditTrail;

    protected $table = 'unit_kerja';

    protected $fillable = [
        'nama_unit',
        'kode_unit',
    ];

    public function pegawai(): HasMany
    {
        return $this->hasMany(Pegawai::class);
    }

    public function ruangan(): HasMany
    {
        return $this->hasMany(Ruangan::class);
    }
}
