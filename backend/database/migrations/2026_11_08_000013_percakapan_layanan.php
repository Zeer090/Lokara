<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 013_percakapan_layanan
 *
 * Kolom percakapan_info.layanan dan indeks retensi 30 hari.
 * Lihat docs/02_database_postgresql.md bagian 5.6.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE percakapan_info
              ADD COLUMN IF NOT EXISTS layanan VARCHAR(20) NOT NULL DEFAULT 'asisten_info'
                  CHECK (layanan IN ('asisten_info', 'pembanding'));
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS idx_percakapan_retensi ON percakapan_info (dibuat_pada)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_percakapan_retensi');
        DB::statement('ALTER TABLE percakapan_info DROP COLUMN IF EXISTS layanan');
    }
};

