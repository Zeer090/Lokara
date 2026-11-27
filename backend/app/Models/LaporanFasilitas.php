<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanFasilitas extends Model
{
    use HasUuids;

    protected $table = 'laporan_fasilitas';

    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = 'diperbarui_pada';

    protected $fillable = [
        'lokasi_id',
        'pelapor_id',
        'ringkasan',
        'isi',
        'status',
        'catatan_penanganan',
        'dikonfirmasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'dikonfirmasi_pada' => 'datetime',
            'dibuat_pada'       => 'datetime',
            'diperbarui_pada'   => 'datetime',
        ];
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pelapor_id');
    }
}

