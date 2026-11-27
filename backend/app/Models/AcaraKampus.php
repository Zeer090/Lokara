<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AcaraKampus extends Model
{
    use HasUuids;

    protected $table = 'acara_kampus';

    public $timestamps = false;

    protected $fillable = [
        'nama',
        'lokasi_id',
        'mulai',
        'selesai',
        'alasan',
        'didaftarkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'mulai'       => 'datetime',
            'selesai'     => 'datetime',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }

    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'didaftarkan_oleh');
    }

    public function slotTerkunci(): HasOne
    {
        return $this->hasOne(SlotTerkunci::class, 'acara_id');
    }
}

