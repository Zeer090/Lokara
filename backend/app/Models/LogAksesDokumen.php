<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAksesDokumen extends Model
{
    protected $table = 'log_akses_dokumen';

    public $timestamps = false;

    protected $fillable = [
        'pengguna_id',
        'jenis_dokumen',
        'dokumen_id',
        'reservasi_id',
        'aksi',
        'hasil',
        'alasan_tolak',
        'alamat_ip',
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
}

