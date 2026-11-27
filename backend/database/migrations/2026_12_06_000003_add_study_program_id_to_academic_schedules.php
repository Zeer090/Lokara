<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('academic_schedules', function (Blueprint $table) {
            $table->uuid('study_program_id')->nullable();
            $table->foreign('study_program_id')
                  ->references('id')->on('study_programs')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('academic_schedules', function (Blueprint $table) {
            $table->dropForeign(['study_program_id']);
            $table->dropColumn('study_program_id');
        });
    }
};

