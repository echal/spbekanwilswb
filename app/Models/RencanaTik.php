<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class RencanaTik extends Model
{
    protected $table = 'rencana_tik';

    protected $fillable = [
        'tahun',
        'nama_rencana',
        'keterangan',
        'status',
        'created_by',
        'approved_by',
    ];

    // Eager load items untuk mencegah N+1 query
    protected $with = ['items'];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
        ];
    }

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(RencanaTikItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Accessors - Collection-based untuk performance
    protected function totalKebutuhan(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->items->sum('jumlah_direncanakan')
        );
    }

    protected function totalAnggaran(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->items->sum('estimasi_anggaran')
        );
    }
}
