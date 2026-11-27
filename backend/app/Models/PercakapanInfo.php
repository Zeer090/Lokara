<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PercakapanInfo extends Model
{
    protected $table = 'percakapan_info';

    public $timestamps = false;

    protected $fillable = [
        'pengguna_id',
        'sesi',
        'peran',
        'isi',
        'layanan',
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

