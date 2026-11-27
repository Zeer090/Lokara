<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotTerkunci extends Model
{
    use HasUuids;

    protected $table = 'slot_terkunci';

    public $timestamps = false;

    protected $fillable = [
        'lokasi_id',
        'rentang',
        'sumber',
        'reservasi_id',
        'acara_id',
    ];

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }

    public function reservasi(): BelongsTo
    {
        return $this->belongsTo(Reservasi::class, 'reservasi_id');
    }

    public function acara(): BelongsTo
    {
        return $this->belongsTo(AcaraKampus::class, 'acara_id');
    }
}

