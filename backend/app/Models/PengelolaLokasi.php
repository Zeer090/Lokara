<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengelolaLokasi extends Model
{
    use HasUuids;

    protected $table = 'pengelola_lokasi';

    public $timestamps = false;

    protected $fillable = [
        'pengguna_id',
        'lokasi_id',
        'ditugaskan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'dibuat_pada' => 'datetime',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }

    public function penugas(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'ditugaskan_oleh');
    }
}

