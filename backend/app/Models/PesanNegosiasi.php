<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesanNegosiasi extends Model
{
    protected $table = 'pesan_negosiasi';

    public $timestamps = false;

    protected $fillable = [
        'kasus_id',
        'pengirim',
        'isi',
    ];

    protected $appends = ['pesan'];

    public function getPesanAttribute(): ?string
    {
        return $this->attributes['isi'] ?? null;
    }

    public function setPesanAttribute($value): void
    {
        $this->attributes['isi'] = $value;
    }

    protected function casts(): array
    {
        return [
            'dibuat_pada' => 'datetime',
        ];
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(KasusPenggusuran::class, 'kasus_id');
    }
}

