<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Surat extends Model
{
    use HasUuids;

    protected $table = 'surat';

    public $timestamps = false;

    protected $fillable = [
        'pengguna_id',
        'nama_asli',
        'path_berkas',
        'ukuran_byte',
    ];

    protected function casts(): array
    {
        return [
            'ukuran_byte' => 'integer',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }
}

