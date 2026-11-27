<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 012_laporan_fasilitas
 *
 * Enum status_laporan dan tabel laporan_fasilitas.
 * Lihat docs/02_database_postgresql.md bagian 5.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
              CREATE TYPE status_laporan AS ENUM ('baru', 'diproses', 'selesai', 'ditolak');
            EXCEPTION
              WHEN duplicate_object THEN null;
            END $$;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS laporan_fasilitas (
              id                 UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              lokasi_id          UUID REFERENCES lokasi(id),
              pelapor_id         UUID REFERENCES pengguna(id),
              ringkasan          VARCHAR(200) NOT NULL,
              isi                TEXT NOT NULL,
              status             status_laporan NOT NULL DEFAULT 'baru',
              catatan_penanganan TEXT,
              dikonfirmasi_pada  TIMESTAMPTZ NOT NULL,
              dibuat_pada        TIMESTAMPTZ NOT NULL DEFAULT now(),
              diperbarui_pada    TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS idx_laporan_lokasi ON laporan_fasilitas (lokasi_id, status)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS laporan_fasilitas CASCADE');
        DB::statement('DROP TYPE IF EXISTS status_laporan');
    }
};

