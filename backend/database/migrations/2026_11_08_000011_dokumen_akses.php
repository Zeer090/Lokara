<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 011_dokumen_akses
 *
 * Indeks unik memo aktif, tabel memo_riwayat, tabel log_akses_dokumen,
 * dan trigger perlindungan append-only (hanya tambah).
 * Lihat docs/02_database_postgresql.md bagian 5.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_memo_aktif ON memo (reservasi_id) WHERE status = \'berlaku\'');

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS memo_riwayat (
              id          BIGSERIAL PRIMARY KEY,
              memo_id     UUID NOT NULL REFERENCES memo(id),
              aksi        VARCHAR(20) NOT NULL CHECK (aksi IN ('terbit', 'dicabut')),
              oleh        UUID REFERENCES pengguna(id),
              alasan      TEXT,
              dibuat_pada TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS idx_memo_riwayat ON memo_riwayat (memo_id, id)');

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS log_akses_dokumen (
              id            BIGSERIAL PRIMARY KEY,
              pengguna_id   UUID NOT NULL REFERENCES pengguna(id),
              jenis_dokumen VARCHAR(10) NOT NULL CHECK (jenis_dokumen IN ('surat', 'memo')),
              dokumen_id    UUID NOT NULL,
              reservasi_id  UUID,
              aksi          VARCHAR(10) NOT NULL CHECK (aksi IN ('lihat', 'unduh')),
              hasil         VARCHAR(10) NOT NULL CHECK (hasil IN ('diizinkan', 'ditolak')),
              alasan_tolak  TEXT,
              alamat_ip     VARCHAR(45),
              dibuat_pada   TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS idx_lad_dokumen ON log_akses_dokumen (jenis_dokumen, dokumen_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_lad_pengguna ON log_akses_dokumen (pengguna_id, id)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_hanya_tambah() RETURNS trigger AS $$
            BEGIN
              RAISE EXCEPTION 'tabel % hanya boleh INSERT', TG_TABLE_NAME;
            END $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_memo_riwayat_hanya_tambah ON memo_riwayat');
        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_memo_riwayat_hanya_tambah
              BEFORE UPDATE OR DELETE ON memo_riwayat
              FOR EACH ROW EXECUTE FUNCTION fn_hanya_tambah()
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_lad_hanya_tambah ON log_akses_dokumen');
        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_lad_hanya_tambah
              BEFORE UPDATE OR DELETE ON log_akses_dokumen
              FOR EACH ROW EXECUTE FUNCTION fn_hanya_tambah()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_lad_hanya_tambah ON log_akses_dokumen');
        DB::statement('DROP TRIGGER IF EXISTS trg_memo_riwayat_hanya_tambah ON memo_riwayat');
        DB::statement('DROP FUNCTION IF EXISTS fn_hanya_tambah()');
        DB::statement('DROP TABLE IF EXISTS log_akses_dokumen CASCADE');
        DB::statement('DROP TABLE IF EXISTS memo_riwayat CASCADE');
        DB::statement('DROP INDEX IF EXISTS uq_memo_aktif');
    }
};
