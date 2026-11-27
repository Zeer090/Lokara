# Catatan Keputusan Teknis

Dokumen ini mencatat keputusan yang mengikat selama pengembangan SMARTSPACE AI,
beserta alasannya. Perubahan atas keputusan di sini harus dicatat ulang, bukan
dihapus diam-diam.

---

## 1. Versi Laravel: 12 (bukan 13)

**Keputusan:** Backend memakai **Laravel 12** (`laravel/laravel:^12.0`).

**Alasan:** Lingkungan pengembangan memakai **PHP 8.2.12** (XAMPP di
`C:\xampp\php\php.exe`). Laravel 13 mensyaratkan PHP 8.3+, sehingga tidak dapat
dijalankan di lingkungan ini. Laravel 12 masih menerima perbaikan keamanan dan
mendukung seluruh kebutuhan proyek (PostgreSQL, Eloquent, queue, dsb.).

**Tanggal:** 2026-10-29

---

## 2. Lingkungan

| Komponen | Versi / Lokasi | Catatan |
|---|---|---|
| PHP | 8.2.12 (`C:\xampp\php\php.exe`) | sudah di PATH |
| Ekstensi wajib | `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `fileinfo`, `curl` | aktif |
| Composer | 2.11.0 | terpasang |
| PostgreSQL | 17.11, server `postgresql-x64-17` berjalan | `C:\Program Files\PostgreSQL\17\bin` (ditambahkan ke PATH per sesi bila `psql` belum terbaca) |
| Proyek backend | `D:\smartspace\backend` | Laravel 12 |

Ekstensi PHP lain yang dibutuhkan paket nanti (`gd`/`dom` untuk dompdf,
`zip` untuk rilis) dicek saat paket tersebut dipasang.

---

## 3. Paket Tambahan (dipilih saat dibutuhkan)

| Paket | Versi | Alasan |
|---|---|---|
| `laravel/sanctum` | `^4.0` | Rilis 4.x mendukung Laravel 11/12 dan PHP 8.2; dipakai untuk token API |
| `barryvdh/laravel-dompdf` | `^3.1` | 3.x mendukung Laravel 9–12 dan PHP 8.2 (dompdf 3.x), dipakai untuk memo/surat PDF |

Catatan:
- Jangan memasang `laravel/sanctum:^5` atau `barryvdh/laravel-dompdf:^4` sebelum
  dipastikan mendukung Laravel 12 + PHP 8.2.
- Pemasangan dilakukan pada tahap fitur terkait, bukan pada tahap database.

---

## 4. Database

- DBMS: **PostgreSQL 17** (rancangan `docs/02_database_postgresql.md`).
- Nama database: `smartspace`.
- Koneksi Laravel: driver `pgsql`, host `127.0.0.1`, port `5432`.
- Kredensial disimpan di `backend/.env` (tidak di-commit). `.env.example` hanya
  memuat nilai contoh.
- Pemilik database: role `smartspace_app` (bukan `postgres`). Password hanya
  disimpan di `backend/.env`; tidak dicatat di dokumen atau repo.
- Skema ditulis sebagai **migrasi Laravel berisi SQL mentah** karena rancangan
  memakai tipe `ENUM`, `TSTZRANGE`, `EXCLUDE USING gist`, dan indeks parsial yang
  tidak dapat dinyatakan lewat Schema Builder.
- Nomor migrasi mengikuti `docs/02_database_postgresql.md` bagian 7:
  `001_ekstensi_enum`, `002_pengguna_lokasi`, `003_surat_reservasi`,
  `004_acara_slot`, `005_memo_kasus`, `006_pesan_usulan_log`,
  `007_notifikasi_pengaturan_info`.
- Migrasi bawaan `create_users_table` (tabel `users`, `password_reset_tokens`,
  `sessions`) **dihapus** karena autentikasi memakai tabel `pengguna` dan
  Sanctum (token), bukan guard session. `SESSION_DRIVER=file`.
- Tabel infrastruktur Laravel `cache`, `cache_locks`, `jobs`, `job_batches`, dan
  `failed_jobs` tetap dipertahankan (bukan bagian dari 14 tabel rancangan awal; sejak 2026-11-08 rancangan menjadi **18 tabel**, lihat bagian 10).
- Tambahan pengaman di luar teks rancangan: trigger
  `trg_log_agent_hanya_tambah` menolak `UPDATE`/`DELETE` pada `log_agent` di
  level database, bukan hanya lewat hak akses aplikasi. Ini memperkuat aturan
  "log agent tidak boleh diubah" (`02_database_postgresql.md` bagian 5).

---

## 5. Rencana 13 Langkah Tugas Database

| # | Langkah | Status |
|---|---|---|
| 1 | Cek lingkungan (PHP, ekstensi, Composer, psql) | selesai |
| 2 | Buat proyek Laravel 12 di `backend/` (Laravel 12.70.2) | selesai |
| 3 | Konfigurasi `.env` + `config/database.php` ke PostgreSQL | selesai |
| 4 | Siapkan database `smartspace` + role `smartspace_app` | selesai |
| 5 | Migrasi 001: ekstensi `btree_gist`/`pgcrypto` + 7 tipe enum | selesai |
| 6 | Migrasi 002: `pengguna`, `lokasi` | selesai |
| 7 | Migrasi 003: `surat`, `reservasi` + indeks | selesai |
| 8 | Migrasi 004: `acara_kampus`, `slot_terkunci` + exclusion constraint | selesai |
| 9 | Migrasi 005: `memo`, `kasus_penggusuran` + indeks | selesai |
| 10 | Migrasi 006: `pesan_negosiasi`, `usulan_agent`, `log_agent` | selesai |
| 11 | Migrasi 007: `notifikasi`, `pengaturan`, `percakapan_info` | selesai |
| 12 | Seeder data awal (admin, lokasi, pengaturan) | selesai |
| 13 | Verifikasi: `migrate:fresh --seed`, rollback, uji constraint bentrok & cascade | selesai |

Catatan: daftar 13 langkah di atas adalah pemecahan tugas database dari
`docs/01_backend.md` bagian 12 butir 1 ("Database + migrasi + seed") dan
`docs/02_database_postgresql.md` bagian 3–7.

### Hasil verifikasi (2026-10-29)

> **Riwayat.** Verifikasi ini memakai `migrate:fresh --seed` pada database yang masih kosong. **Sejak 2026-11-06 `migrate:fresh` dilarang** (bagian 10, butir 8) karena database kini memuat data tes F-11.

- `php artisan migrate:fresh --seed` berhasil; 7 migrasi proyek + 2 migrasi
  infrastruktur berjalan.
- `php artisan migrate:rollback` berhasil menghapus semuanya, lalu
  `php artisan migrate --seed` berhasil membangun ulang (down/up terbukti).
- `php artisan smartspace:verifikasi` lulus seluruh pemeriksaan:
  14 tabel, 7 enum, 2 ekstensi, exclusion constraint, 2 indeks unik parsial,
  trigger log, penolakan jadwal tumpang tindih (SQLSTATE `23P01`), penolakan
  `UPDATE log_agent`, dan `ON DELETE CASCADE` slot saat reservasi dihapus.
- Data awal terverifikasi (setelah seeder dijalankan dua kali, tetap idempoten):
  3 akun uji (admin, pengguna, pimpinan) **[riwayat; peran admin dan pimpinan sudah tidak dipakai]**, 9 lokasi (4 gedung + 5 ruangan),
  2 baris `pengaturan`.
- Kredensial akun uji pengembangan diatur lewat `ADMIN_PASSWORD`,
  `PENGGUNA_PASSWORD`, dan `PIMPINAN_PASSWORD` di `.env`. Nilai default
  (`Admin123!`, `Pengguna2026!`, `Pimpinan2026!`) wajib diganti sebelum
  produksi; `.env.example` hanya memuat placeholder.

---

## 6. Konvensi Penamaan

- Tabel dan kolom: **bahasa Indonesia**, `snake_case` (mengikuti dokumen
  rancangan), bukan konvensi jamak Inggris bawaan Laravel.
- Primary key: `id` UUID (`gen_random_uuid()`), kecuali tabel log/percakapan yang
  memakai `BIGSERIAL` sesuai rancangan.
- Waktu: `TIMESTAMPTZ` (`timestamp with time zone`).
- Alasan penggusuran disimpan apa adanya; tidak ada normalisasi teks.

---

## 7. Hal yang Belum Diputuskan

- Nomor WhatsApp Bu Eci (diisi di tabel `pengaturan`, bukan `.env`; saat ini
  masih placeholder `62xxxxxxxxxxx`).
- Format resmi nomor memo kampus (sementara `MEMO/YYYY/0001`).
- ~~Apakah reservasi ruangan oleh organisasi juga wajib surat~~ **[DIJAWAB 2026-11-08: tidak. Semua reservasi ruangan otomatis tanpa surat, lihat bagian 10.]**
  (`01_backend.md` bagian 13).

---

## 8. Ditunda ke Fase Backend B5/B6

Dua hal berikut **tidak** dikerjakan pada tahap database dan sengaja ditunda:

1. **`seed_skenario_uji.sql`** — data uji skenario sidang (mudah, sedang,
   sulit, buntu, uji keamanan). Menunggu alur backend B5/B6 agar data uji
   konsisten dengan perilaku endpoint dan agent.
2. **Tes transaksi acara kampus** — penggusuran reservasi + rollback saat acara
   kampus bentrok dengan acara kampus lain (`02_database_postgresql.md` §4.2).
   Logikanya milik layanan backend, bukan skema; akan diuji bersama endpoint
   Atur Jadwal pada B5/B6.

Yang sudah diuji pada tahap ini adalah jaminan level database: exclusion
constraint menolak slot tumpang tindih, cascade slot, dan trigger log agent.

---

## 9. Fase B1: Fondasi Backend & Autentikasi (2026-10-29)

**Keputusan & Implementasi:**
- **Paket:** `laravel/sanctum` versi `v4.3.3` (kompatibel dengan Laravel 12 dan PHP 8.2).
- **Model `Pengguna`:** Diubah menjadi `Authenticatable` dengan `HasApiTokens` dan `HasUuids`. Password divalidasi lewat `getAuthPassword()` yang mengembalikan kolom `password_hash`.
- **Autentikasi Token:** Menggunakan token Bearer Sanctum (masa berlaku default 8 jam / 480 menit).
- **Middleware Peran:** `App\Http\Middleware\Peran` (`peran:admin`, `peran:pengguna`, dst.) **[DIGANTI 2026-11-08: `peran:pengguna`, `peran:pengelola_gedung`, `peran:pengelola_sistem` ditambah Policy per lokasi, lihat bagian 10.]** mengembalikan respons HTTP 403 dengan format JSON `{ "galat": "..." }` jika tidak sesuai peran.
- **Exception Handler:** Seluruh respons error API dipetakan ke format JSON `{ "galat": "pesan jelas" }` (400, 401, 403, 404, 429, 500).
- **CORS:** Diatur di `config/cors.php` dengan asal dari `CORS_ORIGIN` (`http://localhost:3000`) **[DIGANTI 2026-11-08: `http://localhost:5173`, lihat bagian 10.]**.
- **Rate Limit:** Limit khusus auth (10 req/menit) dan API umum (60 req/menit).
- **Pengujian:** Seluruh tes fitur B1 (`AuthTest.php`) lulus (daftar, masuk, login gagal, akun non-aktif, profil `/saya`, keluar, token kedaluwarsa/hapus, dan format galat 404).

---

## 10. Revisi Role dan Keputusan Teknis (2026-11-06, dilanjutkan 2026-11-08)

Bagian ini **mengikat** dan mengutamakan nilai lama di bagian 1 sampai 9 yang bertentangan. Nilai lama **tidak dihapus**, hanya diberi penanda **[DIGANTI]** di tempatnya. Rujukan bisnis ada di `00_PRIORITAS.md` (revisi 4 role).

### 10.1 Keputusan teknis

| # | Topik | Keputusan | Menggantikan |
|---|---|---|---|
| 1 | Kategori akses | Empat: Public (tidak disimpan), `pengguna`, `pengelola_gedung`, `pengelola_sistem`. Tidak ada `admin`, `pimpinan`, maupun `pengelola_ruangan` | Role `admin`, `pimpinan` (bagian 9) |
| 2 | Middleware peran | `peran:pengguna`, `peran:pengelola_gedung`, `peran:pengelola_sistem`, ditambah **Policy per lokasi** lewat `pengelola_lokasi` | `peran:admin` (bagian 9) |
| 3 | Port dan CORS | Backend `:8000`, frontend `:5173`, `CORS_ORIGIN=http://localhost:5173` | `http://localhost:3000` (bagian 9) |
| 4 | Token | Sanctum **Bearer token**, 8 jam. Bukan JWT, bukan cookie. `SANCTUM_STATEFUL_DOMAINS` tidak dipakai | Tidak ada nilai lama yang bertentangan |
| 5 | Ruangan | Semua reservasi ruangan **otomatis, tanpa surat**, tanpa pengelola dan tanpa memo. Gedung: manual dengan surat | Pertanyaan terbuka bagian 7 |
| 6 | Jenis penggunaan | Ditentukan jenis lokasi: ruangan = `rutin` + `otomatis`, gedung = `event_kegiatan` + `manual`. Kolom tetap disimpan **[USULAN]** | |
| 7 | Jumlah tabel | **18 tabel**: 14 awal ditambah `pengelola_lokasi`, `memo_riwayat`, `log_akses_dokumen`, `laporan_fasilitas` **[USULAN, daftar diturunkan dari PRIORITAS dan Kontrak]** | "14 tabel" (bagian 4 dan 5) |
| 8 | Migrasi | Hanya migrasi transisi **008 sampai 013**. **Tidak ada `migrate:fresh`**. `migrate:rollback` hanya di lingkungan uji terpisah. `pg_dump` sebelum transisi | Prosedur `migrate:fresh --seed` (bagian 5) |
| 9 | Enum peran | `ADD VALUE` hanya `pengelola_gedung` dan `pengelola_sistem`. `admin` dan `pimpinan` **tidak bisa dihapus dari enum PostgreSQL**, dikunci trigger | |
| 10 | Akun lama | `admin`/`pimpinan` tetap ada sampai dipetakan (A-01). Login boleh, semua rute berperan 403, `/auth/saya` mengembalikan `perlu_pemetaan` | |
| 11 | Seeder | Tidak membuat akun `admin` atau `pimpinan` baru. Sembilan lokasi tetap, `sementara = TRUE`. `pengelola_lokasi` kosong | Akun uji `ADMIN_PASSWORD`, `PIMPINAN_PASSWORD` (bagian 5) |
| 12 | Akun `pengelola_sistem` pertama | Dibuat lewat `php artisan smartspace:buat-pengelola-sistem` **[USULAN]**, bukan seeder | |
| 13 | Data tes F-11 | Tidak diberi nilai kolom baru. Constraint baru memakai `NOT VALID`. Setiap `UPDATE` ke baris itu ditolak. Karena ruangan kini otomatis, baris itu tidak punya jalur keputusan, penanganannya diputuskan pemilik proyek | |
| 14 | Dokumen | Setiap akses surat dan memo dicatat ke `log_akses_dokumen`, termasuk yang ditolak. `pengelola_sistem` tidak punya akses dokumen | |
| 15 | Percakapan | Retensi `percakapan_info` maksimal 30 hari. Kolom `layanan` memisahkan `asisten_info` dan `pembanding` | |
| 16 | Chatbot pembanding | Terpisah dari Asisten Informasi, dikendalikan `PEMBANDING_AKTIF` (bawaan `false`) **[USULAN]** | |

### 10.2 Rencana migrasi transisi

| Nomor | Isi |
|---|---|
| `008_peran_pengelola` | `ADD VALUE` dua peran baru (`$withinTransaction = false`) |
| `009_pengelola_lokasi` | Kolom `lokasi.sementara`, tabel `pengelola_lokasi`, trigger penugasan dan trigger peran pengguna |
| `010_reservasi_jenis_alur` | Enum dan kolom `jenis_penggunaan`, `alur_persetujuan`, `jumlah_peserta`, CHECK `NOT VALID`, trigger validasi, constraint trigger status-slot |
| `011_dokumen_akses` | `uq_memo_aktif`, `memo_riwayat`, `log_akses_dokumen`, trigger hanya-tambah |
| `012_laporan_fasilitas` | Enum `status_laporan`, tabel `laporan_fasilitas` |
| `013_percakapan_layanan` | Kolom `percakapan_info.layanan` dan indeks retensi |

Detail SQL ada di `02_database_postgresql.md` bagian 5.

### 10.3 Dampak pada pekerjaan yang sudah selesai

- **Fase B1 (auth)** perlu disesuaikan: role baru, penanganan akun lama, CORS ke `:5173`, dan `AuthTest.php` diperbarui.
- `php artisan smartspace:verifikasi` perlu diperbarui: 18 tabel, enum dan trigger baru, serta uji cascade slot yang menyisipkan slot dalam transaksi yang sama dengan reservasi `disetujui`.
- Tes transaksi acara kampus (bagian 8) tetap ditunda ke fase B5/B6, tetapi kini hanya untuk **gedung** dan oleh `pengelola_gedung`.

### 10.4 Asumsi yang diperkenalkan revisi ini

Semuanya boleh dikoreksi pemilik proyek. Yang berasal dari `00_PRIORITAS.md` bagian 8 tidak diulang di sini.

1. Satu gedung boleh punya lebih dari satu pengelola, dan satu pengelola boleh mengelola banyak gedung (`UNIQUE (pengguna_id, lokasi_id)`, bukan `UNIQUE (lokasi_id)`).
2. Reservasi `disetujui` boleh dibatalkan pemiliknya sebelum dimulai.
3. Saat `selesai`, slot dihapus dan baris reservasi dipertahankan sebagai riwayat.
4. Batas rentang kueri jadwal publik 62 hari.
5. Token disimpan di `sessionStorage` pada frontend.
6. Rute `/sistem/log/agent` dan `/sistem/log/akses-dokumen` keduanya dibuka untuk `pengelola_sistem`, mengikuti matriks "Melihat log: Ya".
7. "Kasus terbuka" berarti semua status kasus selain `selesai`.

### 10.5 Hal yang masih belum diputuskan (tambahan)

- Nomor WhatsApp Bu Eci, format memo, data resmi lokasi, retensi laporan fasilitas, dan pemetaan akun lama (E-01, E-02, L-01, L-02, A-01).
- Penanganan data tes F-11 yang berstatus `menunggu` di ruangan.
- Siapa yang berwenang menggusur reservasi **ruangan**, bila kebutuhan itu muncul (PRIORITAS bagian 8 butir 1).
