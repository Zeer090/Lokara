<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoRiwayat extends Model
{
    protected $table = 'memo_riwayat';

    public $timestamps = false;

    protected $fillable = [
        'memo_id',
        'aksi',
        'oleh',
        'alasan',
    ];

    protected function casts(): array
    {
        return [
            'dibuat_pada' => 'datetime',
        ];
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(Memo::class, 'memo_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'oleh');
    }
}

