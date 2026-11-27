<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('academic_schedule_uploads', function (Blueprint $table) {
            $table->uuid('department_id')->nullable();
            $table->uuid('study_program_id')->nullable();

            $table->foreign('department_id')
                ->references('id')->on('departments')
                ->onDelete('restrict');

            $table->foreign('study_program_id')
                ->references('id')->on('study_programs')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('academic_schedule_uploads', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['study_program_id']);
            $table->dropColumn(['department_id', 'study_program_id']);
        });
    }
};

