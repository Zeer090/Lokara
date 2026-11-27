<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAgent extends Model
{
    protected $table = 'log_agent';

    public $timestamps = false;

    protected $fillable = [
        'kasus_id',
        'langkah',
        'jenis',
        'nama_fungsi',
        'masukan',
        'hasil',
        'catatan',
        'dibuat_pada',
    ];

    protected $appends = ['nama_alat', 'parameter', 'aksi', 'keterangan'];

    protected function casts(): array
    {
        return [
            'langkah'     => 'integer',
            'masukan'     => 'array',
            'hasil'       => 'array',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function getNamaAlatAttribute(): ?string
    {
        return $this->nama_fungsi;
    }

    public function setNamaAlatAttribute($value): void
    {
        $this->attributes['nama_fungsi'] = $value;
    }

    public function getParameterAttribute(): mixed
    {
        return $this->masukan;
    }

    public function setParameterAttribute($value): void
    {
        $this->attributes['masukan'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getAksiAttribute(): ?string
    {
        return $this->nama_fungsi ?: $this->jenis;
    }

    public function getKeteranganAttribute(): ?string
    {
        return $this->catatan;
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(KasusPenggusuran::class, 'kasus_id');
    }
}

