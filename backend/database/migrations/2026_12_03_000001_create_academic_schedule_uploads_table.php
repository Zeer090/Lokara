<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_schedule_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academic_period_id');
            $table->uuid('uploaded_by_user_id');
            $table->string('file_path');
            $table->enum('status', ['pending', 'validated', 'active', 'superseded', 'failed'])->default('pending');
            $table->json('validation_errors')->nullable();
            $table->timestamps();

            $table->foreign('academic_period_id')->references('id')->on('academic_periods')->onDelete('cascade');
            $table->foreign('uploaded_by_user_id')->references('id')->on('pengguna')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_schedule_uploads');
    }
};

