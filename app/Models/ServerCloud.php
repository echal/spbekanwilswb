<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;

class ServerCloud extends Model
{
    use HasAuditTrail;

    protected $table = 'server_cloud';

    protected $fillable = [
        'jenis_layanan',
        'nama_layanan',
        'provider',
        'status_kepemilikan',
        'nomor_kontrak',
        'masa_berlaku',
        'jumlah_user',
        'kategori_data',
    ];

    protected function casts(): array
    {
        return [
            'masa_berlaku' => 'date',
        ];
    }
}
