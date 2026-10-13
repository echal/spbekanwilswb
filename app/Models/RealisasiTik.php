<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealisasiTik extends Model
{
    protected $table = 'realisasi_tik';

    protected $fillable = [
        'rencana_item_id',
        'jumlah_realisasi',
        'tanggal_realisasi',
        'nomor_dokumen',
        'eviden_file',
        'eviden_url',
        'keterangan',
        'status_verifikasi',
        'catatan_verifikasi',
        'diverifikasi_at',
        'dibuat_oleh',
        'diverifikasi_oleh',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_realisasi'  => 'integer',
            'tanggal_realisasi' => 'date',
            'diverifikasi_at'   => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // RELATIONSHIPS
    // ----------------------------------------------------------------

    /**
     * Item rencana yang direalisiskan.
     * realisasi_tik → rencana_tik_items
     */
    public function rencanaItem(): BelongsTo
    {
        return $this->belongsTo(RencanaTikItem::class, 'rencana_item_id');
    }

    /**
     * Pelaksana yang menginput realisasi.
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Verifikator yang menyetujui / menolak.
     */
    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    // ----------------------------------------------------------------
    // SCOPES
    // ----------------------------------------------------------------

    public function scopeDisetujui($query)
    {
        return $query->where('status_verifikasi', 'disetujui');
    }

    public function scopeMenunggu($query)
    {
        return $query->where('status_verifikasi', 'menunggu');
    }

    // ----------------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------------

    public function isVerified(): bool
    {
        return $this->status_verifikasi === 'disetujui';
    }

    public function isEditable(): bool
    {
        return $this->status_verifikasi === 'menunggu';
    }
}
