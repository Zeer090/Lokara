<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KasusPenggusuran extends Model
{
    use HasUuids;

    protected $table = 'kasus_penggusuran';

    public $timestamps = false;

    protected $fillable = [
        'reservasi_id',
        'acara_id',
        'alasan',
        'alasan_penggusuran',
        'dibuat_oleh',
        'status',
        'catatan_keputusan',
        'reservasi_pengganti_id',
        'diputuskan_pada',
        'selesai_pada',
    ];

    protected function casts(): array
    {
        return [
            'catatan_keputusan' => 'array',
            'dibuat_pada'       => 'datetime',
            'diputuskan_pada'   => 'datetime',
            'selesai_pada'      => 'datetime',
        ];
    }

    public function setAlasanPenggusuranAttribute($value): void
    {
        $this->attributes['alasan_penggusuran'] = $value;
        $this->attributes['alasan'] = $value;
    }

    public function getAlasanPenggusuranAttribute(): ?string
    {
        return $this->attributes['alasan_penggusuran'] ?? $this->attributes['alasan'] ?? null;
    }

    public function reservasi(): BelongsTo
    {
        return $this->belongsTo(Reservasi::class, 'reservasi_id');
    }

    public function acara(): BelongsTo
    {
        return $this->belongsTo(AcaraKampus::class, 'acara_id');
    }

    public function acaraKampus(): BelongsTo
    {
        return $this->acara();
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh');
    }

    public function reservasiPengganti(): BelongsTo
    {
        return $this->belongsTo(Reservasi::class, 'reservasi_pengganti_id');
    }

    public function pesanNegosiasi(): HasMany
    {
        return $this->hasMany(PesanNegosiasi::class, 'kasus_id')->orderBy('dibuat_pada');
    }

    public function usulanAgent(): HasMany
    {
        return $this->hasMany(UsulanAgent::class, 'kasus_id');
    }

    public function logAgent(): HasMany
    {
        return $this->hasMany(LogAgent::class, 'kasus_id')->orderBy('langkah');
    }
}

