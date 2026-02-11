<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jaringan extends Model
{
    use HasAuditTrail;

    protected $table = 'jaringan';

    protected $fillable = [
        'nama_jaringan',
        'provider',
        'bandwidth',
        'lokasi',
    ];

    public function monitoringJaringan(): HasMany
    {
        return $this->hasMany(MonitoringJaringan::class);
    }
}
