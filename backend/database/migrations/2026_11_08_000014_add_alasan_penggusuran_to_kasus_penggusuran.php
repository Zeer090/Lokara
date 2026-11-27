<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the missing column to the eviction case table.
        // Use IF NOT EXISTS to make the migration idempotent.
        DB::statement("ALTER TABLE kasus_penggusuran ADD COLUMN IF NOT EXISTS alasan_penggusuran TEXT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the column if it exists.
        DB::statement("ALTER TABLE kasus_penggusuran DROP COLUMN IF EXISTS alasan_penggusuran");
    }
};

