# SMARTSPACE AI: Rancangan Database (PostgreSQL)

**Status:** diselaraskan dengan `00_PRIORITAS.md` (revisi 4 role, 2026-11-08) dan `KONTRAK_DATA_BISNIS.md`.
**Urutan prioritas:** `CATATAN_KEPUTUSAN.md` > `00_PRIORITAS.md` > `KONTRAK_DATA_BISNIS.md` > dokumen ini.
**PostgreSQL:** 17 (lingkungan nyata, lihat Catatan bagian 2).

Tanda **[ASUMSI]** dan **[USULAN]** berarti belum diputuskan pemilik proyek dan boleh dikoreksi.

---

## 1. Prinsip Rancangan

1. **Hanya reservasi `disetujui` dan acara kampus yang mengunci jadwal.** Status `menunggu` tidak mengunci.
2. Penguncian lewat `slot_terkunci` dengan **exclusion constraint**. Database sendiri menolak jadwal tumpang tindih.
3. **Acara kampus mengalahkan reservasi**, dalam satu transaksi dengan urutan tetap.
4. Percakapan, usulan, dan langkah agent disimpan agar bisa diaudit.
5. Alasan penggusuran disimpan **apa adanya**.
6. **Gedung dan ruangan berdiri sendiri, tanpa relasi induk.** Hanya lokasi di tabel `lokasi` yang bisa dipesan.
7. **Ruangan selalu otomatis tanpa surat. Gedung selalu manual dengan surat** (PRIORITAS butir 17 dan 18). Kolom `jenis_penggunaan` (`rutin` | `event_kegiatan`, F-07) dan `alur_persetujuan` (`otomatis` | `manual`, F-08) tetap disimpan, tetapi nilainya **ditentukan jenis lokasi**: ruangan → `rutin` + `otomatis`, gedung → `event_kegiatan` + `manual`. **[USULAN]** kedua kolom dipertahankan untuk riwayat dan kosakata Kontrak, walau bisa diturunkan dari `lokasi.jenis`.
8. **Empat kategori akses, tiga disimpan.** Public tidak disimpan. Role di DB: `pengguna`, `pengelola_gedung`, `pengelola_sistem`.
9. **Penugasan pengelola hanya untuk gedung.** Ruangan tidak punya pengelola.
10. **Hanya migrasi transisi. Tidak ada `migrate:fresh`** (PRIORITAS butir 16), supaya data tes F-11 aman.
11. Aturan yang bergantung pada **[ASUMSI]** (misalnya acara kampus hanya untuk gedung) sengaja **ditegakkan di backend, bukan di DB**, agar mudah diubah bila asumsi dikoreksi.

---

## 2. Diagram Relasi (Ringkas)

```
pengguna ──< reservasi >── lokasi >──< pengelola_lokasi >── pengguna (pengelola_gedung)
                │  │                    (hanya lokasi berjenis gedung)
                │  └── surat (PDF, hanya gedung)
                ├──< memo ──< memo_riwayat   (hanya gedung)
                ├──1 slot_terkunci (hanya jika disetujui)
                └──< kasus_penggusuran >──< pesan_negosiasi
                          │          └──< usulan_agent
                          │          └──< log_agent
                          └── acara_kampus ──1 slot_terkunci

log_akses_dokumen  (setiap akses surat/memo, termasuk yang ditolak)
laporan_fasilitas  (terpisah dari percakapan)
notifikasi >── pengguna
pengaturan (wa_admin_nomor, dll.)
percakapan_info (Asisten Informasi dan chatbot pembanding, kolom `layanan`)
```

---

## 3. Daftar 18 Tabel

| # | Tabel | Asal |
|---|---|---|
| 1 | `pengguna` | lama (diubah: enum peran, trigger) |
| 2 | `lokasi` | lama (tambah `sementara`) |
| 3 | `surat` | lama |
| 4 | `reservasi` | lama (tambah `jenis_penggunaan`, `alur_persetujuan`, `jumlah_peserta`) |
| 5 | `acara_kampus` | lama |
| 6 | `slot_terkunci` | lama |
| 7 | `memo` | lama (tambah indeks unik memo aktif) |
| 8 | `kasus_penggusuran` | lama |
| 9 | `pesan_negosiasi` | lama |
| 10 | `usulan_agent` | lama |
| 11 | `log_agent` | lama |
| 12 | `notifikasi` | lama |
| 13 | `pengaturan` | lama |
| 14 | `percakapan_info` | lama (tambah `layanan`) |
| 15 | `pengelola_lokasi` | **baru** (migrasi 009) |
| 16 | `memo_riwayat` | **baru** (migrasi 011, B-04 / I-14) |
| 17 | `log_akses_dokumen` | **baru** (migrasi 011, butir 7 / I-13) |
| 18 | `laporan_fasilitas` | **baru** (migrasi 012, B-07 / I-18) |

Tabel infrastruktur Laravel (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `personal_access_tokens`) **bukan bagian dari 18 tabel** ini.

> **[USULAN]** PRIORITAS menyebut "18 tabel" tanpa merinci. Empat tabel baru di atas adalah penurunan dari butir 2, 7, 12 dan Kontrak B-04 / I-14. Koreksi bila hitungan pemilik proyek berbeda.

---

## 4. Skema Dasar (migrasi 001 sampai 007, sudah berjalan)

Bagian ini merangkum skema yang sudah ada. Yang berubah ada di bagian 5.

### 4.1 Ekstensi dan enum awal

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;
CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TYPE peran_pengguna   AS ENUM ('pengguna', 'admin', 'pimpinan');  -- lihat 5.1
CREATE TYPE jenis_lokasi     AS ENUM ('gedung', 'ruangan');
CREATE TYPE status_reservasi AS ENUM
  ('menunggu', 'disetujui', 'tergusur', 'selesai', 'ditolak', 'dibatalkan');
CREATE TYPE status_memo      AS ENUM ('berlaku', 'tidak_berlaku');
CREATE TYPE status_kasus     AS ENUM
  ('terbuka', 'diputuskan', 'diajukan_ulang', 'selesai', 'diserahkan');
CREATE TYPE pengirim_pesan   AS ENUM ('pengguna', 'agent', 'sistem');
CREATE TYPE status_usulan    AS ENUM ('diusulkan', 'dipilih', 'ditolak_pengguna', 'kedaluwarsa');
```

### 4.2 Tabel inti (ringkas)

```sql
CREATE TABLE pengguna (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  nama          VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  peran         peran_pengguna NOT NULL DEFAULT 'pengguna',
  organisasi    VARCHAR(160),
  no_hp         VARCHAR(20),
  aktif         BOOLEAN NOT NULL DEFAULT TRUE,
  dibuat_pada   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE lokasi (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  nama        VARCHAR(120) NOT NULL UNIQUE,
  jenis       jenis_lokasi NOT NULL,
  kapasitas   INTEGER NOT NULL CHECK (kapasitas > 0),
  fasilitas   JSONB NOT NULL DEFAULT '{}'::jsonb,
  keterangan  TEXT,
  aktif       BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE surat (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  pengguna_id   UUID NOT NULL REFERENCES pengguna(id),
  nama_asli     VARCHAR(255) NOT NULL,
  path_berkas   TEXT NOT NULL,
  ukuran_byte   INTEGER NOT NULL CHECK (ukuran_byte > 0),
  dibuat_pada   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE reservasi (
  id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  kode              VARCHAR(20) NOT NULL UNIQUE,
  pengguna_id       UUID NOT NULL REFERENCES pengguna(id),
  lokasi_id         UUID NOT NULL REFERENCES lokasi(id),
  surat_id          UUID REFERENCES surat(id),
  keperluan         TEXT NOT NULL,
  kebutuhan         JSONB NOT NULL DEFAULT '{}'::jsonb,
  mulai             TIMESTAMPTZ NOT NULL,
  selesai           TIMESTAMPTZ NOT NULL,
  status            status_reservasi NOT NULL DEFAULT 'menunggu',
  catatan_admin     TEXT,            -- nama kolom dipertahankan; berisi alasan tolak / catatan pengelola
  reservasi_asal_id UUID REFERENCES reservasi(id),
  dibuat_pada       TIMESTAMPTZ NOT NULL DEFAULT now(),
  diperbarui_pada   TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (selesai > mulai)
);
CREATE INDEX idx_reservasi_pengguna ON reservasi (pengguna_id);
CREATE INDEX idx_reservasi_status   ON reservasi (status);
CREATE INDEX idx_reservasi_lokasi_waktu ON reservasi (lokasi_id, mulai, selesai);

CREATE TABLE acara_kampus (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  nama             VARCHAR(200) NOT NULL,
  lokasi_id        UUID NOT NULL REFERENCES lokasi(id),
  mulai            TIMESTAMPTZ NOT NULL,
  selesai          TIMESTAMPTZ NOT NULL,
  alasan           TEXT NOT NULL,
  didaftarkan_oleh UUID NOT NULL REFERENCES pengguna(id),  -- pengelola_gedung (dicek backend)
  dibuat_pada      TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (selesai > mulai)
);

CREATE TABLE slot_terkunci (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  lokasi_id     UUID NOT NULL REFERENCES lokasi(id),
  rentang       TSTZRANGE NOT NULL,
  sumber        VARCHAR(20) NOT NULL CHECK (sumber IN ('reservasi', 'acara_kampus')),
  reservasi_id  UUID REFERENCES reservasi(id) ON DELETE CASCADE,
  acara_id      UUID REFERENCES acara_kampus(id) ON DELETE CASCADE,
  CHECK (
    (sumber = 'reservasi'    AND reservasi_id IS NOT NULL AND acara_id IS NULL) OR
    (sumber = 'acara_kampus' AND acara_id IS NOT NULL AND reservasi_id IS NULL)
  ),
  CONSTRAINT tidak_boleh_bentrok
    EXCLUDE USING gist (lokasi_id WITH =, rentang WITH &&)
);
CREATE UNIQUE INDEX uq_slot_reservasi ON slot_terkunci (reservasi_id) WHERE reservasi_id IS NOT NULL;
CREATE UNIQUE INDEX uq_slot_acara     ON slot_terkunci (acara_id)     WHERE acara_id IS NOT NULL;

CREATE TABLE memo (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  reservasi_id     UUID NOT NULL REFERENCES reservasi(id),
  nomor_memo       VARCHAR(60) NOT NULL UNIQUE,   -- sementara MEMO/YYYY/0001 (E-01)
  path_berkas      TEXT,
  status           status_memo NOT NULL DEFAULT 'berlaku',
  diterbitkan_pada TIMESTAMPTZ NOT NULL DEFAULT now(),
  dicabut_pada     TIMESTAMPTZ,
  alasan_cabut     TEXT
);
CREATE INDEX idx_memo_reservasi ON memo (reservasi_id);

CREATE TABLE kasus_penggusuran (
  id                     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  reservasi_id           UUID NOT NULL UNIQUE REFERENCES reservasi(id),
  acara_id               UUID REFERENCES acara_kampus(id),
  alasan                 TEXT NOT NULL,
  dibuat_oleh            UUID NOT NULL REFERENCES pengguna(id),   -- pengelola_gedung
  status                 status_kasus NOT NULL DEFAULT 'terbuka',
  catatan_keputusan      JSONB,
  reservasi_pengganti_id UUID REFERENCES reservasi(id),
  dibuat_pada            TIMESTAMPTZ NOT NULL DEFAULT now(),
  diputuskan_pada        TIMESTAMPTZ,
  selesai_pada           TIMESTAMPTZ
);
CREATE INDEX idx_kasus_status ON kasus_penggusuran (status);
```

`pesan_negosiasi`, `usulan_agent`, `log_agent`, `notifikasi`, `pengaturan`, dan `percakapan_info` tidak berubah strukturnya, kecuali `percakapan_info` (5.6). Trigger `trg_log_agent_hanya_tambah` yang sudah ada (Catatan bagian 4) tetap dipakai.

---

## 5. Migrasi Transisi (008 sampai 013)

> **Tidak ada `migrate:fresh`.** Setiap migrasi harus punya `down()` yang aman dan **tidak boleh menyentuh baris reservasi tes milik pemilik proyek (F-11)**.
> Migrasi memakai SQL mentah, mengikuti Catatan bagian 4.

### 5.1 Migrasi 008: role baru (`008_peran_pengelola`)

`ALTER TYPE ... ADD VALUE` tidak bisa memakai nilai barunya dalam transaksi yang sama, jadi migrasi ini berdiri sendiri (`public $withinTransaction = false;`).

```sql
ALTER TYPE peran_pengguna ADD VALUE IF NOT EXISTS 'pengelola_gedung';
ALTER TYPE peran_pengguna ADD VALUE IF NOT EXISTS 'pengelola_sistem';
```

Hanya **dua** nilai baru. **Tidak ada `pengelola_ruangan`** (PRIORITAS butir 17).

**Nilai `admin` dan `pimpinan` tidak bisa dihapus dari enum PostgreSQL.** Keduanya tetap ada di tipe, tetapi dikunci lewat trigger (5.2) sehingga tidak ada akun baru atau perubahan role yang memakainya. Akun lama yang masih berperan `admin`/`pimpinan` tetap ada sampai dipetakan (A-01) dan **tidak punya akses apa pun** di backend (lihat `01_backend.md` bagian 4).

### 5.2 Migrasi 009: penugasan pengelola dan penanda lokasi (`009_pengelola_lokasi`)

```sql
-- Penanda data contoh (butir 1, L-01)
ALTER TABLE lokasi ADD COLUMN sementara BOOLEAN NOT NULL DEFAULT FALSE;
UPDATE lokasi SET sementara = TRUE;   -- sembilan lokasi seeder = data contoh sementara

CREATE TABLE pengelola_lokasi (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  pengguna_id      UUID NOT NULL REFERENCES pengguna(id) ON DELETE CASCADE,
  lokasi_id        UUID NOT NULL REFERENCES lokasi(id)   ON DELETE CASCADE,
  ditugaskan_oleh  UUID NOT NULL REFERENCES pengguna(id),   -- pengelola_sistem
  dibuat_pada      TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (pengguna_id, lokasi_id)
);
CREATE INDEX idx_pl_lokasi ON pengelola_lokasi (lokasi_id);
```

**Dibiarkan kosong** setelah migrasi (butir 2). Sampai `pengelola_sistem` mengisinya, tidak ada yang berhak menyetujui reservasi gedung, termasuk Bu Eci (butir 3).

**[ASUMSI]** Satu gedung boleh punya lebih dari satu pengelola, dan satu pengelola boleh mengelola banyak gedung. PRIORITAS tidak melarang keduanya. Jika harus satu pengelola per gedung, tambahkan `UNIQUE (lokasi_id)`.

Trigger pengaman: penugasan hanya untuk `pengelola_gedung` pada lokasi berjenis `gedung`. Ruangan dan `pengelola_sistem` ditolak.

```sql
CREATE FUNCTION fn_pengelola_lokasi_valid() RETURNS trigger AS $$
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

CREATE TRIGGER trg_pengelola_lokasi_valid
  BEFORE INSERT OR UPDATE ON pengelola_lokasi
  FOR EACH ROW EXECUTE FUNCTION fn_pengelola_lokasi_valid();
```

Trigger peran pengguna: menolak peran lama untuk perubahan baru, dan menolak kenaikan role bila pengguna masih punya reservasi aktif atau kasus terbuka (PRIORITAS bagian 8 butir 4).

```sql
CREATE FUNCTION fn_pengguna_peran() RETURNS trigger AS $$
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

CREATE TRIGGER trg_pengguna_peran
  BEFORE INSERT OR UPDATE OF peran ON pengguna
  FOR EACH ROW EXECUTE FUNCTION fn_pengguna_peran();
```

**[ASUMSI]** "Kasus terbuka" dibaca sebagai semua status kasus selain `selesai` (termasuk `diserahkan`).

### 5.3 Migrasi 010: kolom Kontrak pada reservasi (`010_reservasi_jenis_alur`)

```sql
CREATE TYPE jenis_penggunaan AS ENUM ('rutin', 'event_kegiatan');
CREATE TYPE alur_persetujuan AS ENUM ('otomatis', 'manual');

ALTER TABLE reservasi
  ADD COLUMN jenis_penggunaan jenis_penggunaan,   -- nullable: baris lama tidak diklasifikasi (F-11)
  ADD COLUMN alur_persetujuan alur_persetujuan,
  ADD COLUMN jumlah_peserta   INTEGER
        CHECK (jumlah_peserta IS NULL OR jumlah_peserta > 0);   -- opsional (F-10)

-- NOT VALID: berlaku untuk baris BARU, baris lama tidak diperiksa dan tidak diubah (F-11)
ALTER TABLE reservasi ADD CONSTRAINT reservasi_jenis_alur_wajib
  CHECK (jenis_penggunaan IS NOT NULL AND alur_persetujuan IS NOT NULL) NOT VALID;

-- ruangan: rutin + otomatis + TANPA surat.  gedung: event_kegiatan + manual + WAJIB surat.
ALTER TABLE reservasi ADD CONSTRAINT reservasi_jenis_alur_cocok
  CHECK (
    (jenis_penggunaan = 'rutin'          AND alur_persetujuan = 'otomatis' AND surat_id IS NULL) OR
    (jenis_penggunaan = 'event_kegiatan' AND alur_persetujuan = 'manual'   AND surat_id IS NOT NULL)
  ) NOT VALID;
```

**Dampak yang disengaja (F-11):** reservasi tes lama (satu baris `menunggu`, `surat_id` kosong, di ruangan) tidak diberi nilai apa pun, dan **setiap `UPDATE` ke baris itu akan ditolak** oleh constraint di atas. Itu mencegah sistem mengubah, menyetujui, atau menolaknya secara tidak sengaja. Karena ruangan kini otomatis, baris itu **tidak punya jalur keputusan** (PRIORITAS bagian 8 butir 5). Penanganannya diputuskan pemilik proyek di luar sistem dan dicatat di Catatan.

Trigger validasi lintas tabel **[USULAN]**: mengikat jenis penggunaan ke jenis lokasi.

```sql
CREATE FUNCTION fn_reservasi_validasi() RETURNS trigger AS $$
DECLARE v_jenis jenis_lokasi; v_kap INTEGER; v_peran peran_pengguna;
BEGIN
  SELECT jenis, kapasitas INTO v_jenis, v_kap FROM lokasi   WHERE id = NEW.lokasi_id;
  SELECT peran INTO v_peran                   FROM pengguna WHERE id = NEW.pengguna_id;

  IF v_peran <> 'pengguna' THEN
    RAISE EXCEPTION 'hanya akun pengguna yang boleh mengajukan reservasi';
  END IF;
  IF (v_jenis = 'ruangan' AND NEW.jenis_penggunaan <> 'rutin')
  OR (v_jenis = 'gedung'  AND NEW.jenis_penggunaan <> 'event_kegiatan') THEN
    RAISE EXCEPTION 'jenis penggunaan % tidak cocok untuk lokasi %', NEW.jenis_penggunaan, v_jenis;
  END IF;
  IF NEW.jumlah_peserta IS NOT NULL AND NEW.jumlah_peserta > v_kap THEN
    RAISE EXCEPTION 'jumlah peserta melebihi kapasitas lokasi';
  END IF;
  RETURN NEW;
END $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_reservasi_validasi
  BEFORE INSERT ON reservasi
  FOR EACH ROW EXECUTE FUNCTION fn_reservasi_validasi();
```

Pemeriksaan `lokasi.aktif`, waktu di masa depan, dan kepemilikan surat (`surat.pengguna_id = reservasi.pengguna_id`) tetap di backend (Kontrak 5.3 dan 6.1).

Invarian status dan slot (Kontrak I-05, D-20) **[USULAN]**: `disetujui` wajib punya tepat satu slot, status lain tidak boleh punya slot. Diperiksa saat commit supaya reservasi dan slot boleh dibuat dalam satu transaksi.

```sql
CREATE FUNCTION fn_cek_status_slot() RETURNS trigger AS $$
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

CREATE CONSTRAINT TRIGGER ctrg_status_slot
  AFTER INSERT OR UPDATE OF status ON reservasi
  DEFERRABLE INITIALLY DEFERRED
  FOR EACH ROW EXECUTE FUNCTION fn_cek_status_slot();
```

> Perintah `php artisan smartspace:verifikasi` yang sudah ada menguji cascade slot dengan reservasi `disetujui`. Uji itu harus menyisipkan slot dalam transaksi yang sama, kalau tidak trigger ini akan menolaknya saat commit. Sesuaikan perintah itu.

### 5.4 Migrasi 011: dokumen, memo riwayat, log akses (`011_dokumen_akses`)

```sql
-- B-04: satu reservasi, satu memo AKTIF (periksa duplikat dulu sebelum menjalankan)
CREATE UNIQUE INDEX uq_memo_aktif ON memo (reservasi_id) WHERE status = 'berlaku';

CREATE TABLE memo_riwayat (
  id          BIGSERIAL PRIMARY KEY,
  memo_id     UUID NOT NULL REFERENCES memo(id),
  aksi        VARCHAR(20) NOT NULL CHECK (aksi IN ('terbit', 'dicabut')),
  oleh        UUID REFERENCES pengguna(id),     -- NULL bila otomatis oleh sistem (penggusuran)
  alasan      TEXT,
  dibuat_pada TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_memo_riwayat ON memo_riwayat (memo_id, id);

-- Butir 7: setiap akses surat dan memo dicatat, termasuk yang ditolak
CREATE TABLE log_akses_dokumen (
  id            BIGSERIAL PRIMARY KEY,
  pengguna_id   UUID NOT NULL REFERENCES pengguna(id),
  jenis_dokumen VARCHAR(10) NOT NULL CHECK (jenis_dokumen IN ('surat', 'memo')),
  dokumen_id    UUID NOT NULL,               -- tanpa FK, agar percobaan pada ID yang tidak ada tetap tercatat
  reservasi_id  UUID,
  aksi          VARCHAR(10) NOT NULL CHECK (aksi IN ('lihat', 'unduh')),
  hasil         VARCHAR(10) NOT NULL CHECK (hasil IN ('diizinkan', 'ditolak')),
  alasan_tolak  TEXT,
  alamat_ip     VARCHAR(45),
  dibuat_pada   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_lad_dokumen  ON log_akses_dokumen (jenis_dokumen, dokumen_id);
CREATE INDEX idx_lad_pengguna ON log_akses_dokumen (pengguna_id, id);

-- Hanya tambah (Kontrak B-06 / I-13)
CREATE FUNCTION fn_hanya_tambah() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'tabel % hanya boleh INSERT', TG_TABLE_NAME;
END $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_memo_riwayat_hanya_tambah
  BEFORE UPDATE OR DELETE ON memo_riwayat
  FOR EACH ROW EXECUTE FUNCTION fn_hanya_tambah();
CREATE TRIGGER trg_lad_hanya_tambah
  BEFORE UPDATE OR DELETE ON log_akses_dokumen
  FOR EACH ROW EXECUTE FUNCTION fn_hanya_tambah();
```

Memo lama **tidak dihapus**, hanya `tidak_berlaku`. Surat dan memo hanya ada untuk lokasi `gedung` (surat dijaga CHECK 5.3, memo dijaga backend dengan uji).

### 5.5 Migrasi 012: laporan fasilitas (`012_laporan_fasilitas`)

```sql
CREATE TYPE status_laporan AS ENUM ('baru', 'diproses', 'selesai', 'ditolak');

CREATE TABLE laporan_fasilitas (
  id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  lokasi_id         UUID REFERENCES lokasi(id),          -- boleh kosong bila lokasi tidak jelas
  pelapor_id        UUID REFERENCES pengguna(id),        -- hanya terisi jika pengguna memilih menyertakan identitas
  ringkasan         VARCHAR(200) NOT NULL,
  isi               TEXT NOT NULL,
  status            status_laporan NOT NULL DEFAULT 'baru',
  catatan_penanganan TEXT,
  dikonfirmasi_pada TIMESTAMPTZ NOT NULL,                -- wajib: AI tidak boleh membuat laporan tanpa konfirmasi (B-07)
  dibuat_pada       TIMESTAMPTZ NOT NULL DEFAULT now(),
  diperbarui_pada   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_laporan_lokasi ON laporan_fasilitas (lokasi_id, status);
```

- Disimpan **terpisah** dari `percakapan_info` dan **tidak** ikut penghapusan 30 hari.
- **Tidak ada penghapusan otomatis** sampai lama retensi ditetapkan (L-02).
- Usulan laporan dari chatbot **tidak disimpan** sebelum dikonfirmasi. Baris dibuat hanya saat pengguna menekan konfirmasi.
- Pengelola: laporan gedung dikelola `pengelola_gedung` gedung itu. Laporan ruangan dan laporan tanpa lokasi dikelola `pengelola_sistem` **[ASUMSI]**. Ditegakkan di backend.

### 5.6 Migrasi 013: layanan percakapan (`013_percakapan_layanan`)

```sql
ALTER TABLE percakapan_info
  ADD COLUMN layanan VARCHAR(20) NOT NULL DEFAULT 'asisten_info'
      CHECK (layanan IN ('asisten_info', 'pembanding'));   -- butir 13: dipisah
CREATE INDEX idx_percakapan_retensi ON percakapan_info (dibuat_pada);
```

Retensi percakapan publik **maksimal 30 hari** (B-07), dijalankan job harian:

```sql
DELETE FROM percakapan_info WHERE dibuat_pada < now() - interval '30 days';
```

`pengguna_id` hanya diisi bila jawaban memang memakai konteks pribadi. Selain itu dibiarkan `NULL` (anonim).

---

## 6. View dan Kueri Penting

### 6.1 Antrean gedung tanpa pengelola (untuk `pengelola_sistem`)

Hanya daftar gedung dan jumlah, **tanpa detail privat** (matriks PRIORITAS bagian 7). Ruangan tidak ikut karena tidak punya pengelola.

```sql
CREATE VIEW v_lokasi_tanpa_pengelola AS
SELECT l.id AS lokasi_id, l.nama, l.jenis, l.sementara,
       count(r.id) AS jumlah_menunggu
FROM lokasi l
LEFT JOIN reservasi r
       ON r.lokasi_id = l.id AND r.status = 'menunggu' AND r.alur_persetujuan = 'manual'
WHERE l.aktif
  AND l.jenis = 'gedung'
  AND NOT EXISTS (SELECT 1 FROM pengelola_lokasi p WHERE p.lokasi_id = l.id)
GROUP BY l.id, l.nama, l.jenis, l.sementara;
```

### 6.2 Daftar pengajuan untuk pengelola

```sql
SELECT r.*
FROM reservasi r
JOIN pengelola_lokasi p ON p.lokasi_id = r.lokasi_id AND p.pengguna_id = :me
WHERE r.alur_persetujuan = 'manual' AND r.status = 'menunggu';
```

---

## 7. Prosedur Penting (Transaksi)

### 7.1 Ruangan: persetujuan otomatis (semua reservasi ruangan)

Satu transaksi:
1. Backend memvalidasi: lokasi `ruangan` aktif, **tidak ada file surat**, waktu, jumlah peserta (bila diisi).
2. `INSERT reservasi` dengan `jenis_penggunaan = 'rutin'`, `alur_persetujuan = 'otomatis'`, `surat_id = NULL`, status `disetujui`.
3. `INSERT slot_terkunci` (`sumber = 'reservasi'`). Bila `23P01`, rollback dan kembalikan konflik. Tidak boleh ada reservasi `disetujui` tanpa slot.
4. Commit, lalu kirim notifikasi.

Reservasi ruangan **tidak punya antrean ACC**, tidak punya memo, dan tidak punya pengelola. Jika pengguna mengirim file surat untuk ruangan, backend menolak (F-05) tanpa menyimpan apa pun.

### 7.2 Gedung: pengajuan dan ACC manual

Pengajuan: `INSERT reservasi` dengan `jenis_penggunaan = 'event_kegiatan'`, `alur_persetujuan = 'manual'`, status `menunggu`, `surat_id` terisi, **tanpa slot**. Notifikasi ke pengelola gedung itu. Bila gedung belum punya pengelola, notifikasi ke semua `pengelola_sistem` (butir 3).

ACC, satu transaksi. Penguncian baris **sekaligus memeriksa kewenangan** (PRIORITAS bagian 6):

```sql
SELECT r.*
FROM reservasi r
JOIN lokasi l           ON l.id = r.lokasi_id
JOIN pengelola_lokasi p ON p.lokasi_id = l.id AND p.pengguna_id = :me
JOIN pengguna u         ON u.id = p.pengguna_id
WHERE r.id = :rid
  AND r.alur_persetujuan = 'manual'
  AND r.status = 'menunggu'
  AND l.jenis = 'gedung'
  AND u.peran = 'pengelola_gedung'
FOR UPDATE OF r;
```

Jika tidak ada baris, backend membedakan penyebabnya (reservasi tidak ada: 404, bukan wewenang: 403, bukan `menunggu`: 409). Bila ada baris:

1. `INSERT slot_terkunci`. Bila `23P01`, rollback dan HTTP 409 "jadwal sudah terisi".
2. `UPDATE reservasi SET status = 'disetujui'`.
3. `INSERT memo` dan `INSERT memo_riwayat (aksi = 'terbit')`.
4. `INSERT notifikasi` untuk pengguna.

**Ubah** = langkah yang sama dengan gedung dan/atau jadwal yang diubah pengelola, lalu `catatan_admin` dan notifikasi menyatakan hasilnya berbeda dari permintaan (PRIORITAS bagian 8 butir 2). Gedung tujuan harus **dikelola pemanggil**. Diperiksa lewat `pengelola_lokasi`.

### 7.3 Acara kampus dan penggusuran

Hanya `pengelola_gedung`, **hanya untuk gedung yang ia kelola** **[ASUMSI, PRIORITAS bagian 8 butir 1]**. Pemeriksaan wewenang dan jenis lokasi dilakukan **backend sebelum `BEGIN`**. Urutan transaksi tidak boleh dibalik:

```sql
BEGIN;

-- 1. simpan acara (didaftarkan_oleh = pengelola_gedung yang login)
INSERT INTO acara_kampus (nama, lokasi_id, mulai, selesai, alasan, didaftarkan_oleh)
VALUES ($1, $2, $3, $4, $5, $6) RETURNING id;      -- :acara

-- 2. temukan reservasi disetujui yang bentrok
SELECT r.id FROM reservasi r
JOIN slot_terkunci s ON s.reservasi_id = r.id
WHERE s.lokasi_id = $2 AND s.rentang && tstzrange($3, $4)
FOR UPDATE OF r;

-- 3. untuk tiap reservasi bentrok:
UPDATE reservasi SET status = 'tergusur', diperbarui_pada = now() WHERE id = :rid;
DELETE FROM slot_terkunci WHERE reservasi_id = :rid;
UPDATE memo SET status = 'tidak_berlaku', dicabut_pada = now(), alasan_cabut = $5
 WHERE reservasi_id = :rid AND status = 'berlaku' RETURNING id;      -- :memo
INSERT INTO memo_riwayat (memo_id, aksi, oleh, alasan) VALUES (:memo, 'dicabut', NULL, $5);
INSERT INTO kasus_penggusuran (reservasi_id, acara_id, alasan, dibuat_oleh)
VALUES (:rid, :acara, $5, $6);
INSERT INTO notifikasi (pengguna_id, judul, isi, tautan) VALUES (...);

-- 4. baru kunci slot acara kampus
INSERT INTO slot_terkunci (lokasi_id, rentang, sumber, acara_id)
VALUES ($2, tstzrange($3, $4), 'acara_kampus', :acara);

COMMIT;
```

Jika langkah 4 gagal (`23P01`), seluruh transaksi di-rollback. Pembatalan manual (`/gusur`) sama dengan langkah 3 dengan `acara_id = NULL` tanpa langkah 4.

Karena penggusuran hanya untuk gedung, reservasi ruangan **tidak pernah** masuk langkah ini (lihat PRIORITAS bagian 8 butir 1).

### 7.4 Reservasi pengganti (butir 10)

`INSERT reservasi` dengan `reservasi_asal_id` terisi, `surat_id` **disalin dari asal**, `jenis_penggunaan = 'event_kegiatan'`, `alur_persetujuan = 'manual'`, status `menunggu`. Karena asalnya selalu gedung, pengganti selalu melewati ACC manual pengelola gedung.

### 7.5 Selesainya reservasi (B-05) **[USULAN]**

Saat `disetujui` menjadi `selesai`: baris `slot_terkunci` dihapus (invarian 5.3 mewajibkannya), sedangkan **baris reservasi tidak pernah dihapus**. Jejak jadwal (`mulai`, `selesai`, `lokasi_id`, status) tetap ada di `reservasi`. Jika pemilik proyek ingin arsip slot terpisah, tambahkan tabel arsip. Itu akan menambah jumlah tabel.

### 7.6 Cek bentrok dan pencarian lokasi kosong

Dua kategori: **terkunci** (`slot_terkunci` beririsan) dan **menunggu** (hanya peringatan, relevan untuk gedung). `cari_gedung_kosong` memakai `l.aktif`, `l.jenis = 'gedung'`, `l.kapasitas`, fasilitas `@>`, dan `NOT EXISTS` slot beririsan.

---

## 8. Aturan Integritas

| Aturan | Cara menjamin |
|---|---|
| Tidak ada dua jadwal terkunci tumpang tindih | Exclusion constraint `tidak_boleh_bentrok` |
| Reservasi `disetujui` punya tepat satu slot, lainnya tidak | Constraint trigger `ctrg_status_slot` **[USULAN]** |
| Reservasi baru wajib punya `jenis_penggunaan` dan `alur_persetujuan` yang cocok dengan surat | CHECK `NOT VALID` pada migrasi 010 |
| Ruangan = `rutin` + `otomatis` tanpa surat, gedung = `event_kegiatan` + `manual` dengan surat | CHECK 010 dan trigger `trg_reservasi_validasi` **[USULAN]**, serta backend |
| Hanya `pengguna` yang boleh mengajukan reservasi, peserta ≤ kapasitas | Trigger `trg_reservasi_validasi` dan backend |
| Penugasan hanya `pengelola_gedung` ke gedung | Trigger `trg_pengelola_lokasi_valid` |
| Peran lama tidak bisa dipakai lagi, kenaikan role ditolak bila ada reservasi aktif atau kasus terbuka | Trigger `trg_pengguna_peran` |
| Persetujuan hanya oleh pengelola gedung yang berwenang | Kueri `FOR UPDATE` bagian 7.2 dan policy backend |
| Acara kampus dan penggusuran hanya gedung yang dikelola `pengelola_gedung` | **Backend saja** (bergantung pada [ASUMSI]) |
| Acara kampus mengalahkan reservasi | Urutan transaksi 7.3 |
| Satu reservasi, satu kasus penggusuran | `UNIQUE (reservasi_id)` |
| Satu reservasi, satu memo aktif, riwayat tetap ada | `uq_memo_aktif` dan `memo_riwayat` |
| Memo hanya untuk gedung | Backend, diuji |
| Surat milik pengaju | Backend (FK saja tidak cukup, Kontrak 5.3) |
| `log_agent`, `memo_riwayat`, `log_akses_dokumen` hanya tambah | Trigger `BEFORE UPDATE OR DELETE` |
| Alasan penggusuran apa adanya | Hanya `pengelola_gedung` yang menulis, agent hanya membaca |
| Data tes F-11 tidak berubah | Tanpa backfill, `NOT VALID`, tanpa `migrate:fresh` |

---

## 9. Data Awal (Seeder)

- **Tidak ada akun `admin` atau `pimpinan` baru.** Akun lama tetap sampai dipetakan (A-01).
- **Sembilan lokasi seeder dipertahankan** (4 gedung dan 5 ruangan) dengan `sementara = TRUE`. Data resmi menyusul (L-01).
- `pengelola_lokasi` **kosong**.
- `pengaturan`: `wa_admin_nomor = '62xxxxxxxxxxx'` (E-02, belum diisi) dan `wa_admin_nama = 'Bu Eci'`.
- **Masalah awal (bootstrap):** perlu satu akun `pengelola_sistem` untuk mengisi penugasan. **[USULAN]** buat lewat perintah `php artisan smartspace:buat-pengelola-sistem` (kata sandi dari input, tidak dicatat di repo), bukan lewat seeder.
- Seeder harus idempoten dan tidak menyentuh reservasi.

**Pemetaan akun lama (A-01):** audit daftar akun `admin` dan `pimpinan` → pemilik proyek memutuskan satu per satu → `UPDATE pengguna SET peran = ...`. Bu Eci menjadi `pengelola_gedung`. Setelah itu `pengelola_sistem` mengisi `pengelola_lokasi`, termasuk Bu Eci untuk gedung yang ia kelola.

Data uji skenario sidang tetap ditunda ke skrip terpisah `seed_skenario_uji.sql` (Catatan bagian 8).

---

## 10. Migrasi dan Pencadangan

- Nomor migrasi: `001`–`007` sudah berjalan, transisi `008_peran_pengelola`, `009_pengelola_lokasi`, `010_reservasi_jenis_alur`, `011_dokumen_akses`, `012_laporan_fasilitas`, `013_percakapan_layanan`.
- **Dilarang:** `migrate:fresh`. **Dibolehkan:** `migrate`, dan `migrate:rollback` hanya pada lingkungan uji terpisah.
- **Sebelum menjalankan transisi:** `pg_dump` kondisi sekarang (termasuk reservasi tes F-11) dan simpan.
- Setelah transisi, perintah `smartspace:verifikasi` diperbarui: 18 tabel proyek, enum baru, trigger baru, dan memastikan baris F-11 tidak berubah.
- Cadangan: `pg_dump` harian terjadwal, plus satu dump kondisi awal untuk dipulihkan sebelum tiap skenario sidang.
