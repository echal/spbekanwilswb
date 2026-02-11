<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruangan extends Model
{
    use HasAuditTrail;

    protected $table = 'ruangan';

    protected $fillable = [
        'nama_ruangan',
        'unit_kerja_id',
    ];

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function perangkat(): HasMany
    {
        return $this->hasMany(Perangkat::class);
    }
}
