<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 010_reservasi_jenis_alur
 *
 * Enum jenis_penggunaan & alur_persetujuan, kolom jumlah_peserta,
 * CHECK NOT VALID untuk menjaga data tes F-11, trigger validasi lokasi & peran,
 * dan constraint trigger status-slot (invarian disetujui <=> slot).
 * Lihat docs/02_database_postgresql.md bagian 5.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
              CREATE TYPE jenis_penggunaan AS ENUM ('rutin', 'event_kegiatan');
            EXCEPTION
              WHEN duplicate_object THEN null;
            END $$;
        SQL);

        DB::statement(<<<'SQL'
            DO $$ BEGIN
              CREATE TYPE alur_persetujuan AS ENUM ('otomatis', 'manual');
            EXCEPTION
              WHEN duplicate_object THEN null;
            END $$;
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE reservasi
              ADD COLUMN IF NOT EXISTS jenis_penggunaan jenis_penggunaan,
              ADD COLUMN IF NOT EXISTS alur_persetujuan alur_persetujuan,
              ADD COLUMN IF NOT EXISTS jumlah_peserta INTEGER
                    CHECK (jumlah_peserta IS NULL OR jumlah_peserta > 0)
        SQL);

        DB::statement('ALTER TABLE reservasi DROP CONSTRAINT IF EXISTS reservasi_jenis_alur_wajib');
        DB::statement(<<<'SQL'
            ALTER TABLE reservasi ADD CONSTRAINT reservasi_jenis_alur_wajib
              CHECK (jenis_penggunaan IS NOT NULL AND alur_persetujuan IS NOT NULL) NOT VALID
        SQL);

        DB::statement('ALTER TABLE reservasi DROP CONSTRAINT IF EXISTS reservasi_jenis_alur_cocok');
        DB::statement(<<<'SQL'
            ALTER TABLE reservasi ADD CONSTRAINT reservasi_jenis_alur_cocok
              CHECK (
                (jenis_penggunaan = 'rutin' AND alur_persetujuan = 'otomatis' AND surat_id IS NULL) OR
                (jenis_penggunaan = 'event_kegiatan' AND alur_persetujuan = 'manual' AND surat_id IS NOT NULL)
              ) NOT VALID
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_reservasi_validasi() RETURNS trigger AS $$
            DECLARE v_jenis jenis_lokasi; v_kap INTEGER; v_peran peran_pengguna;
            BEGIN
              SELECT jenis, kapasitas INTO v_jenis, v_kap FROM lokasi WHERE id = NEW.lokasi_id;
              SELECT peran INTO v_peran FROM pengguna WHERE id = NEW.pengguna_id;

              IF v_peran <> 'pengguna' THEN
                RAISE EXCEPTION 'hanya akun pengguna yang boleh mengajukan reservasi';
              END IF;
              IF (v_jenis = 'ruangan' AND NEW.jenis_penggunaan <> 'rutin')
              OR (v_jenis = 'gedung' AND NEW.jenis_penggunaan <> 'event_kegiatan') THEN
                RAISE EXCEPTION 'jenis penggunaan % tidak cocok untuk lokasi %', NEW.jenis_penggunaan, v_jenis;
              END IF;
              IF NEW.jumlah_peserta IS NOT NULL AND NEW.jumlah_peserta > v_kap THEN
                RAISE EXCEPTION 'jumlah peserta melebihi kapasitas lokasi';
              END IF;
              RETURN NEW;
            END $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_reservasi_validasi ON reservasi');
        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_reservasi_validasi
              BEFORE INSERT ON reservasi
              FOR EACH ROW EXECUTE FUNCTION fn_reservasi_validasi()
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_cek_status_slot() RETURNS trigger AS $$
            DECLARE n INTEGER;
            BEGIN
              SELECT count(*) INTO n FROM slot_terkunci WHERE reservasi_id = NEW.id;
              IF NEW.status = 'disetujui' AND n <> 1 THEN
                RAISE EXCEPTION 'reservasi disetujui wajib punya tepat satu slot (ada %)', n;
              ELSIF NEW.status <> 'disetujui' AND n <> 0 THEN
                RAISE EXCEPTION 'reservasi berstatus % tidak boleh punya slot', NEW.status;
              END IF;
              RETURN NULL;
            END $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS ctrg_status_slot ON reservasi');
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER ctrg_status_slot
              AFTER INSERT OR UPDATE OF status ON reservasi
              DEFERRABLE INITIALLY DEFERRED
              FOR EACH ROW EXECUTE FUNCTION fn_cek_status_slot()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS ctrg_status_slot ON reservasi');
        DB::statement('DROP FUNCTION IF EXISTS fn_cek_status_slot()');
        DB::statement('DROP TRIGGER IF EXISTS trg_reservasi_validasi ON reservasi');
        DB::statement('DROP FUNCTION IF EXISTS fn_reservasi_validasi()');
        DB::statement('ALTER TABLE reservasi DROP CONSTRAINT IF EXISTS reservasi_jenis_alur_cocok');
        DB::statement('ALTER TABLE reservasi DROP CONSTRAINT IF EXISTS reservasi_jenis_alur_wajib');
        DB::statement('ALTER TABLE reservasi DROP COLUMN IF EXISTS jumlah_peserta');
        DB::statement('ALTER TABLE reservasi DROP COLUMN IF EXISTS alur_persetujuan');
        DB::statement('ALTER TABLE reservasi DROP COLUMN IF EXISTS jenis_penggunaan');
        DB::statement('DROP TYPE IF EXISTS alur_persetujuan');
        DB::statement('DROP TYPE IF EXISTS jenis_penggunaan');
    }
};
