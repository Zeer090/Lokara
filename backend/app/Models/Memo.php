<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Memo extends Model
{
    use HasUuids;

    protected $table = 'memo';

    public $timestamps = false;

    protected $fillable = [
        'reservasi_id',
        'nomor_memo',
        'path_berkas',
        'status',
        'diterbitkan_pada',
        'dicabut_pada',
        'alasan_cabut',
    ];

    protected function casts(): array
    {
        return [
            'diterbitkan_pada' => 'datetime',
            'dicabut_pada'     => 'datetime',
        ];
    }

    public function reservasi(): BelongsTo
    {
        return $this->belongsTo(Reservasi::class, 'reservasi_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(MemoRiwayat::class, 'memo_id');
    }
}

