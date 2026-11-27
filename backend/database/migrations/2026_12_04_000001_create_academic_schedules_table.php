<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academic_period_id');
            $table->uuid('lokasi_id');
            $table->string('nama_mata_kuliah');
            $table->string('kode_mk')->nullable();
            $table->string('kelas')->nullable();
            $table->string('program_studi')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->enum('hari', ['senin','selasa','rabu','kamis','jumat','sabtu','minggu']);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('academic_period_id')->references('id')->on('academic_periods')->onDelete('cascade');
            $table->foreign('lokasi_id')->references('id')->on('lokasi')->onDelete('cascade');
            $table->unique(['academic_period_id','lokasi_id','hari','jam_mulai','jam_selesai'], 'unique_schedule_per_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_schedules');
    }
};

