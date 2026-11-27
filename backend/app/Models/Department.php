<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasUuids;

    protected $table = 'departments';
    public $timestamps = true;
    protected $fillable = [
        'nama',
    ];

    public function studyPrograms(): HasMany
    {
        return $this->hasMany(StudyProgram::class, 'department_id');
    }
}
