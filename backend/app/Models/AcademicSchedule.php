<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AcademicSchedule extends Model
{
    use HasUuids;

    protected $table = 'academic_schedules';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'academic_period_id',
        'lokasi_id',
        'nama_mata_kuliah',
        'kode_mk',
        'kelas',
        'program_studi',
        'nama_dosen',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'study_program_id',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function period()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }
}

