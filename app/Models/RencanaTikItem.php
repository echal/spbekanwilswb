<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RencanaTikItem extends Model
{
    protected $table = 'rencana_tik_items';

    protected $fillable = [
        'rencana_tik_id',
        'kategori',
        'unit_kerja_id',
        'nama_item',
        'jumlah_direncanakan',
        'jumlah_terpenuhi',
        'status_realisasi',
        'prioritas',
        'estimasi_anggaran',
        'tanggal_target',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_direncanakan' => 'integer',
            'jumlah_terpenuhi' => 'integer',
            'estimasi_anggaran' => 'decimal:2',
            'tanggal_target' => 'date',
        ];
    }

    // Relationships
    public function rencana(): BelongsTo
    {
        return $this->belongsTo(RencanaTik::class, 'rencana_tik_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    /**
     * Semua entri realisasi untuk item ini.
     */
    public function realisasi(): HasMany
    {
        return $this->hasMany(RealisasiTik::class, 'rencana_item_id');
    }

    /**
     * Total realisasi yang sudah disetujui.
     */
    public function totalRealisasiDisetujui(): int
    {
        return $this->realisasi()->where('status_verifikasi', 'disetujui')->sum('jumlah_realisasi');
    }
}
