<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE academic_schedules DROP CONSTRAINT IF EXISTS unique_schedule_per_period');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS unique_active_schedule_per_period ON academic_schedules (academic_period_id, lokasi_id, hari, jam_mulai, jam_selesai) WHERE aktif = true');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_active_schedule_per_period');
        DB::statement('ALTER TABLE academic_schedules ADD CONSTRAINT unique_schedule_per_period UNIQUE (academic_period_id, lokasi_id, hari, jam_mulai, jam_selesai)');
    }
};

