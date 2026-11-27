<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lokasi extends Model
{
    use HasUuids;

    protected $table = 'lokasi';

    public $timestamps = false;

    protected $fillable = [
        'nama',
        'jenis',
        'kapasitas',
        'fasilitas',
        'keterangan',
        'aktif',
        'sementara',
    ];

    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
            'fasilitas' => 'array',
            'aktif' => 'boolean',
            'sementara' => 'boolean',
        ];
    }

    public function pengelolaLokasi(): HasMany
    {
        return $this->hasMany(PengelolaLokasi::class, 'lokasi_id');
    }

    public function pengelola(): BelongsToMany
    {
        return $this->belongsToMany(Pengguna::class, 'pengelola_lokasi', 'lokasi_id', 'pengguna_id')
            ->withPivot('ditugaskan_oleh', 'dibuat_pada');
    }

    public function reservasi(): HasMany
    {
        return $this->hasMany(Reservasi::class, 'lokasi_id');
    }

    public function slotTerkunci(): HasMany
    {
        return $this->hasMany(SlotTerkunci::class, 'lokasi_id');
    }
}
