<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AcademicScheduleUpload extends Model
{
    use HasUuids;

    protected $table = 'academic_schedule_uploads';
    public $timestamps = true;
    protected $fillable = [
        'academic_period_id',
        'department_id',
        'study_program_id',
        'uploaded_by_user_id',
        'file_path',
        'status',
        'validation_errors',
    ];

    protected $casts = [
        'validation_errors' => 'array',
    ];

    public function period()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function uploader()
    {
        return $this->belongsTo(Pengguna::class, 'uploaded_by_user_id');
    }
}

