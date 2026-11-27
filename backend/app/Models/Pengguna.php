<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Pengguna extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $table = 'pengguna';

    /**
     * Kolom timestamp tidak memakai created_at/updated_at standar Laravel.
     */
    public $timestamps = false;

    protected $fillable = [
        'nama',
        'email',
        'password_hash',
        'peran',
        'organisasi',
        'no_hp',
        'aktif',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * Sanctum / Auth memakai kolom password_hash, bukan password.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    protected function casts(): array
    {
        return [
            'aktif'       => 'boolean',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function pengelolaLokasi(): HasMany
    {
        return $this->hasMany(PengelolaLokasi::class, 'pengguna_id');
    }

    public function gedungDikelola(): BelongsToMany
    {
        return $this->belongsToMany(Lokasi::class, 'pengelola_lokasi', 'pengguna_id', 'lokasi_id')
            ->withPivot('ditugaskan_oleh', 'dibuat_pada');
    }

    public function reservasi(): HasMany
    {
        return $this->hasMany(Reservasi::class, 'pengguna_id');
    }

    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'pengguna_id');
    }

    public function mengelolaLokasi(string $lokasiId): bool
    {
        return $this->gedungDikelola()->where('lokasi.id', $lokasiId)->exists();
    }
}
