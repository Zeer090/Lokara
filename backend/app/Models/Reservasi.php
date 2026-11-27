<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservasi extends Model
{
    use HasUuids;

    protected $table = 'reservasi';

    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = 'diperbarui_pada';

    protected $fillable = [
        'kode',
        'pengguna_id',
        'lokasi_id',
        'surat_id',
        'keperluan',
        'kebutuhan',
        'mulai',
        'selesai',
        'status',
        'catatan_admin',
        'reservasi_asal_id',
        'jenis_penggunaan',
        'alur_persetujuan',
        'jumlah_peserta',
    ];

    protected function casts(): array
    {
        return [
            'kebutuhan'       => 'array',
            'mulai'           => 'datetime',
            'selesai'         => 'datetime',
            'jumlah_peserta'  => 'integer',
            'dibuat_pada'     => 'datetime',
            'diperbarui_pada' => 'datetime',
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

    public function surat(): BelongsTo
    {
        return $this->belongsTo(Surat::class, 'surat_id');
    }

    public function memo(): HasMany
    {
        return $this->hasMany(Memo::class, 'reservasi_id');
    }

    public function memoAktif(): HasOne
    {
        return $this->hasOne(Memo::class, 'reservasi_id')->where('status', 'berlaku');
    }

    public function slotTerkunci(): HasOne
    {
        return $this->hasOne(SlotTerkunci::class, 'reservasi_id');
    }

    public function kasusPenggusuran(): HasOne
    {
        return $this->hasOne(KasusPenggusuran::class, 'reservasi_id');
    }

    public function reservasiAsal(): BelongsTo
    {
        return $this->belongsTo(Reservasi::class, 'reservasi_asal_id');
    }

    public function reservasiPengganti(): HasOne
    {
        return $this->hasOne(Reservasi::class, 'reservasi_asal_id');
    }
}

