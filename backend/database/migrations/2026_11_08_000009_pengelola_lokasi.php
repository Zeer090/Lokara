<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 009_pengelola_lokasi
 *
 * Kolom lokasi.sementara, tabel pengelola_lokasi, trigger penugasan, dan trigger peran pengguna.
 * Lihat docs/02_database_postgresql.md bagian 5.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE lokasi ADD COLUMN IF NOT EXISTS sementara BOOLEAN NOT NULL DEFAULT FALSE');
        DB::statement('UPDATE lokasi SET sementara = TRUE');

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS pengelola_lokasi (
              id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              pengguna_id      UUID NOT NULL REFERENCES pengguna(id) ON DELETE CASCADE,
              lokasi_id        UUID NOT NULL REFERENCES lokasi(id)   ON DELETE CASCADE,
              ditugaskan_oleh  UUID NOT NULL REFERENCES pengguna(id),
              dibuat_pada      TIMESTAMPTZ NOT NULL DEFAULT now(),
              UNIQUE (pengguna_id, lokasi_id)
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS idx_pl_lokasi ON pengelola_lokasi (lokasi_id)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_pengelola_lokasi_valid() RETURNS trigger AS $$
            DECLARE v_peran peran_pengguna; v_jenis jenis_lokasi;
            BEGIN
              SELECT peran INTO v_peran FROM pengguna WHERE id = NEW.pengguna_id;
              SELECT jenis INTO v_jenis FROM lokasi   WHERE id = NEW.lokasi_id;
              IF NOT (v_jenis = 'gedung' AND v_peran = 'pengelola_gedung') THEN
                RAISE EXCEPTION 'penugasan hanya untuk pengelola_gedung pada gedung (peran %, lokasi %)',
                                v_peran, v_jenis;
              END IF;
              RETURN NEW;
            END $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_pengelola_lokasi_valid ON pengelola_lokasi');
        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_pengelola_lokasi_valid
              BEFORE INSERT OR UPDATE ON pengelola_lokasi
              FOR EACH ROW EXECUTE FUNCTION fn_pengelola_lokasi_valid()
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_pengguna_peran() RETURNS trigger AS $$
            BEGIN
              IF NEW.peran IN ('admin', 'pimpinan')
                 AND (TG_OP = 'INSERT' OR OLD.peran IS DISTINCT FROM NEW.peran) THEN
                RAISE EXCEPTION 'peran % sudah tidak dipakai', NEW.peran;
              END IF;

              IF TG_OP = 'UPDATE'
                 AND NEW.peran IN ('pengelola_gedung', 'pengelola_sistem')
                 AND OLD.peran IS DISTINCT FROM NEW.peran THEN
                IF EXISTS (SELECT 1 FROM reservasi
                           WHERE pengguna_id = NEW.id AND status IN ('menunggu', 'disetujui'))
                   OR EXISTS (SELECT 1 FROM kasus_penggusuran k
                              JOIN reservasi r ON r.id = k.reservasi_id
                              WHERE r.pengguna_id = NEW.id AND k.status <> 'selesai') THEN
                  RAISE EXCEPTION 'pengguna masih punya reservasi aktif atau kasus terbuka';
                END IF;
              END IF;
              RETURN NEW;
            END $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_pengguna_peran ON pengguna');
        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_pengguna_peran
              BEFORE INSERT OR UPDATE OF peran ON pengguna
              FOR EACH ROW EXECUTE FUNCTION fn_pengguna_peran()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_pengguna_peran ON pengguna');
        DB::statement('DROP FUNCTION IF EXISTS fn_pengguna_peran()');
        DB::statement('DROP TRIGGER IF EXISTS trg_pengelola_lokasi_valid ON pengelola_lokasi');
        DB::statement('DROP FUNCTION IF EXISTS fn_pengelola_lokasi_valid()');
        DB::statement('DROP TABLE IF EXISTS pengelola_lokasi CASCADE');
        DB::statement('ALTER TABLE lokasi DROP COLUMN IF EXISTS sementara');
    }
};
