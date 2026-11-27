<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE laporan_fasilitas ADD COLUMN IF NOT EXISTS foto_path VARCHAR(500)');
        DB::statement('ALTER TABLE laporan_fasilitas ADD COLUMN IF NOT EXISTS hasil_analisis_ai JSONB');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE laporan_fasilitas DROP COLUMN IF EXISTS hasil_analisis_ai');
        DB::statement('ALTER TABLE laporan_fasilitas DROP COLUMN IF EXISTS foto_path');
    }
};
