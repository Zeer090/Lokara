<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsulanAgent extends Model
{
    use HasUuids;

    protected $table = 'usulan_agent';

    public $timestamps = false;

    protected $fillable = [
        'kasus_id',
        'lokasi_id',
        'mulai',
        'selesai',
        'alasan_usulan',
        'status',
        'bentrok_dicek',
        'dibuat_pada',
    ];

    protected $appends = ['catatan', 'kapasitas', 'lokasi_nama'];

    protected function casts(): array
    {
        return [
            'mulai'         => 'datetime',
            'selesai'       => 'datetime',
            'bentrok_dicek' => 'boolean',
            'dibuat_pada'   => 'datetime',
        ];
    }

    public function getCatatanAttribute(): ?string
    {
        return $this->alasan_usulan;
    }

    public function getKapasitasAttribute(): ?int
    {
        return $this->lokasi?->kapasitas;
    }

    public function getLokasiNamaAttribute(): ?string
    {
        return $this->lokasi?->nama;
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(KasusPenggusuran::class, 'kasus_id');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }
}

