# SMARTSPACE AI: Rancangan Backend

**Status:** diselaraskan dengan `00_PRIORITAS.md` (revisi 4 role, 2026-11-08) dan `KONTRAK_DATA_BISNIS.md`.
**Urutan prioritas:** `CATATAN_KEPUTUSAN.md` > `00_PRIORITAS.md` > `KONTRAK_DATA_BISNIS.md` > dokumen ini > kode aktual.
Kode adalah bukti status implementasi, **bukan** sumber aturan bisnis. Jika kode berbeda, kodenya yang diperbaiki.

Tanda **[ASUMSI]** dan **[USULAN]** berarti belum diputuskan pemilik proyek dan boleh dikoreksi.

---

## 1. Teknologi (Implementasi Nyata: Laravel 12)

| Kebutuhan | Pilihan |
|---|---|
| Runtime | PHP 8.2.12 (XAMPP) |
| Kerangka | Laravel 12 |
| Database | PostgreSQL 17 (lihat `02_database_postgresql.md`) |
| Akses database | Eloquent + Query Builder + SQL mentah untuk exclusion constraint dan transaksi |
| Autentikasi | Laravel Sanctum **Bearer token** (bukan JWT, bukan cookie). Password di-hash bcrypt |
| Otorisasi | Middleware `Peran` + **Policy per lokasi** |
| Validasi | Form Request |
| Unggah file | Laravel Storage (hanya PDF, hanya untuk gedung) |
| Pembuat PDF memo | `barryvdh/laravel-dompdf` ^3.1 |
| AI Agent | Anthropic API (tool use), model dari variabel lingkungan |
| Log | Laravel Logging |

---

## 2. Struktur Folder (Laravel 12)

```
backend/
├── app/
│   ├── Console/Commands/        # smartspace:verifikasi, smartspace:buat-pengelola-sistem, hapus percakapan lama
│   ├── Http/
│   │   ├── Controllers/         # Auth, Lokasi, Reservasi, Pengelola/*, Sistem/*, Kasus, Dokumen, Laporan, AsistenInfo
│   │   ├── Middleware/          # Peran.php
│   │   └── Requests/
│   ├── Models/
│   ├── Policies/                # ReservasiPolicy, DokumenPolicy, KasusPolicy, LokasiPolicy
│   ├── Services/                # ReservasiService, PersetujuanService, AcaraKampusService, DokumenService, MemoService
│   ├── Agent/                   # AgenPenjadwal, Alat, Pagar, PromptSistem, Pembanding
│   └── Providers/
├── config/                      # database.php, sanctum.php, cors.php, smartspace.php
├── database/{migrations,seeders}
├── routes/api.php
├── storage/app/private/         # PDF surat dan memo (di luar akses publik)
└── tests/{Feature,Unit,skenario_sidang}
```

---

## 3. Variabel Lingkungan (`.env.example`)

```
APP_NAME=SMARTSPACE
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Asia/Jakarta
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=smartspace
DB_USERNAME=smartspace_app
DB_PASSWORD=

CORS_ORIGIN=http://localhost:5173
SESSION_DRIVER=file

ANTHROPIC_API_KEY=isi_kunci_api
ANTHROPIC_MODEL=isi_nama_model_yang_dipakai

MAX_PDF_MB=5
JADWAL_MAKS_HARI=62          # [USULAN] batas rentang kueri jadwal publik
RETENSI_PERCAKAPAN_HARI=30   # B-07
PEMBANDING_AKTIF=false       # chatbot pembanding sidang (butir 13)
```

- `SANCTUM_STATEFUL_DOMAINS` **tidak dipakai**: itu untuk mode cookie, sedangkan keputusan memakai Bearer token (PRIORITAS butir 14).
- Nomor WA Bu Eci **tidak** di `.env`, melainkan di tabel `pengaturan` (`wa_admin_nomor`).

---

## 4. Autentikasi dan Peran

### 4.1 Empat kategori akses

Mengikuti `00_PRIORITAS.md` bagian 3 dan matriks bagian 7. Ringkasnya:

| Kategori | Token | Rute |
|---|---|---|
| Public | Tidak | `/lokasi*`, `/jadwal/*`, `/asisten-info/*`, `/laporan-fasilitas` (POST), `/auth/daftar`, `/auth/masuk` |
| `pengguna` | Ya | `/reservasi*`, `/kasus*`, `/notifikasi*`, `/surat/:id/unduh`, `/memo/:id/unduh` |
| `pengelola_gedung` | Ya | `/pengelola/*`, `/laporan/ringkasan`, dokumen gedungnya |
| `pengelola_sistem` | Ya | `/sistem/*`, `/laporan/ringkasan` |

Tidak ada `admin` global, `pimpinan`, maupun `pengelola_ruangan`. **Ruangan tidak punya pengelola.**

### 4.2 Aturan

- Token Bearer Sanctum, masa berlaku 8 jam (Catatan bagian 9). Dikirim lewat header `Authorization`.
- Middleware `peran:...` memeriksa role. Hasilnya **hanya lapisan pertama**. Kewenangan atas lokasi diperiksa **Policy** lewat `pengelola_lokasi`.
- `pengelola_gedung` dan `pengelola_sistem` **ditolak (403)** pada `POST /reservasi` dan semua rute `pengguna`. Mereka memakai akun `pengguna` terpisah.
- **Kepemilikan** selalu dicek: pengguna hanya membuka reservasi, surat, memo, dan kasus miliknya.
- Frontend bukan lapisan keamanan. Semua otorisasi ditegakkan di sini.

### 4.3 Akun lama (`admin`, `pimpinan`)

Menunggu pemetaan (A-01). Selama belum dipetakan:
- Login diperbolehkan, dan `GET /auth/saya` mengembalikan `perlu_pemetaan: true`.
- **Semua rute berperan menolak (403)** dengan pesan "Akun ini belum dipetakan ke role baru. Hubungi pemilik proyek."
- Mereka tidak boleh dipakai untuk menyetujui apa pun.

### 4.4 Bootstrap

Akun `pengelola_sistem` pertama dibuat lewat `php artisan smartspace:buat-pengelola-sistem` **[USULAN]**, karena tidak ada role lain yang boleh membuatnya.

---

## 5. Daftar Endpoint

Semua awalan `/api`. Respons error memakai format `{ "galat": "pesan jelas" }`.

> **Status implementasi:** hanya fase B1 (auth) yang tercatat selesai (Catatan bagian 9). Endpoint lain di bawah adalah **rancangan target**. Jangan menyamakannya dengan fitur yang sudah berjalan sebelum kode membuktikannya.

### 5.1 Auth

| Metode | Rute | Peran | Fungsi |
|---|---|---|---|
| POST | `/auth/daftar` | Public | Daftar. Selalu menghasilkan role `pengguna` |
| POST | `/auth/masuk` | Public | Login, mengembalikan token Bearer |
| GET | `/auth/saya` | Login | Profil, role, daftar gedung yang dikelola (bila pengelola), `perlu_pemetaan` |
| POST | `/auth/keluar` | Login | Cabut token |

### 5.2 Lokasi dan jadwal (Public)

| Metode | Rute | Fungsi |
|---|---|---|
| GET | `/lokasi` | Lokasi aktif (filter jenis, kapasitas, fasilitas). Menyertakan `sementara` |
| GET | `/lokasi/:id` | Detail lokasi |
| GET | `/lokasi/:id/jadwal?dari=&sampai=` | Interval terisi. Rentang dibatasi `JADWAL_MAKS_HARI` |
| GET | `/jadwal/kalender?bulan=` | Kalender publik (reservasi disetujui dan acara kampus) |

**Privasi jadwal publik (I-07):** respons hanya berisi lokasi, mulai, selesai, dan label umum (`terisi` atau `acara_kampus`). **Tidak ada** nama pemesan, keperluan, kode, ID reservasi, alasan penggusuran, atau status `menunggu`.

### 5.3 Reservasi (`pengguna`)

Satu endpoint pengajuan untuk dua alur. **Jenis lokasi menentukan segalanya**, bukan tanggal dan bukan pilihan pengguna (PRIORITAS butir 18).

| Metode | Rute | Fungsi |
|---|---|---|
| POST | `/reservasi` | Ajukan. Multipart bila gedung (membawa PDF) |
| GET | `/reservasi/saya` | Daftar reservasi sendiri |
| GET | `/reservasi/:id` | Detail, hanya pemilik |
| POST | `/reservasi/:id/batal` | Batalkan reservasi sendiri |
| GET | `/surat/:id/unduh` | Unduh surat (pemilik) |
| GET | `/memo/:id/unduh` | Unduh memo (pemilik) |

Body `POST /reservasi`: `lokasi_id`, `keperluan`, `mulai`, `selesai`, `jumlah_peserta` (opsional), `kebutuhan` (opsional), `surat` (PDF, **hanya gedung**). Tidak ada kolom `jenis_penggunaan` dari klien. Backend menurunkannya dari lokasi.

**Pembatalan [USULAN]:** boleh untuk `menunggu`, dan untuk `disetujui` yang belum dimulai (slot dihapus dalam transaksi yang sama). Reservasi `tergusur`, `selesai`, `ditolak`, dan `dibatalkan` tidak bisa dibatalkan.

### 5.4 Pengelola gedung (`pengelola_gedung`)

Semua rute di bawah **dibatasi pada gedung yang terdaftar di `pengelola_lokasi` milik pemanggil**.

| Metode | Rute | Fungsi |
|---|---|---|
| GET | `/pengelola/lokasi` | Gedung yang dikelola |
| GET | `/pengelola/reservasi?status=` | Daftar reservasi gedungnya |
| GET | `/pengelola/reservasi/:id` | Detail (termasuk surat dan memo) |
| POST | `/pengelola/reservasi/:id/setuju` | ACC (lihat 6.2) |
| POST | `/pengelola/reservasi/:id/tolak` | Tolak, `alasan` wajib |
| POST | `/pengelola/reservasi/:id/ubah` | Setujui dengan jadwal atau gedung yang diubah (6.2) |
| GET | `/pengelola/jadwal/kalender` | Kalender gabungan gedungnya, termasuk status `tergusur` |
| POST | `/pengelola/acara-kampus/pratinjau` | Hitung reservasi yang akan tergusur, **tanpa menyimpan** |
| POST | `/pengelola/acara-kampus` | Daftarkan acara, memulai penggusuran (6.3) |
| POST | `/pengelola/reservasi/:id/gusur` | Gusur manual, `alasan` wajib (tanpa acara) |
| GET | `/pengelola/kasus?status=` | Kasus gedungnya, termasuk "belum diputuskan", urut acara terdekat |
| GET | `/pengelola/kasus/:id` | Detail kasus, usulan, pesan, log agent (hanya baca) |
| GET | `/pengelola/laporan-fasilitas` | Laporan fasilitas gedungnya **[ASUMSI]** |
| PATCH | `/pengelola/laporan-fasilitas/:id` | Ubah status laporan gedungnya **[ASUMSI]** |
| GET | `/laporan/ringkasan` | Statistik gedungnya |

### 5.5 Pengelola sistem (`pengelola_sistem`)

`pengelola_sistem` **tidak punya** rute reservasi, surat, memo, kasus, maupun negosiasi.

| Metode | Rute | Fungsi |
|---|---|---|
| GET | `/sistem/pengguna` | Daftar akun |
| POST | `/sistem/pengguna/:id/peran` | Ubah role (lihat 6.9) |
| PATCH | `/sistem/pengguna/:id/aktif` | Aktif atau nonaktifkan akun |
| GET/POST/PATCH | `/sistem/lokasi`, `/sistem/lokasi/:id` | Kelola data lokasi, termasuk `sementara` dan `aktif` |
| GET | `/sistem/penugasan` | Daftar penugasan pengelola gedung |
| POST | `/sistem/penugasan` | Tugaskan `pengelola_gedung` ke gedung |
| DELETE | `/sistem/penugasan/:id` | Cabut penugasan |
| GET | `/sistem/gedung-tanpa-pengelola` | Gedung tanpa pengelola dan jumlah pengajuan menunggu, **tanpa detail privat** |
| GET/PUT | `/sistem/pengaturan` | Termasuk `wa_admin_nomor` |
| GET | `/sistem/log/agent` | Log agent (hanya baca) |
| GET | `/sistem/log/akses-dokumen` | Log akses dokumen (hanya baca) |
| GET | `/sistem/laporan-fasilitas` | Semua laporan |
| PATCH | `/sistem/laporan-fasilitas/:id` | Ubah status laporan ruangan dan laporan tanpa lokasi **[ASUMSI]** |
| GET | `/laporan/ringkasan` | Semua lokasi, tanpa detail privat |

**[ASUMSI]** `GET /sistem/log/agent` dan `/sistem/log/akses-dokumen` keduanya dibuka, mengikuti matriks "Melihat log: Ya". Log agent memuat isi negosiasi pengguna. Jika "log" dimaksudkan hanya log teknis, batasi rute pertama.

### 5.6 Penggusuran dan negosiasi (`pengguna`)

| Metode | Rute | Fungsi |
|---|---|---|
| GET | `/kasus/saya` | Kasus milik pengguna |
| GET | `/kasus/:id` | Detail: alasan, pesan, usulan aktif (pemilik) |
| POST | `/kasus/:id/pesan` | Kirim pesan ke agent, mengembalikan balasan |
| POST | `/kasus/:id/pilih-usulan` | Pengguna memutuskan usulan, membuat catatan |
| GET | `/kasus/:id/tautan-wa` | Tautan WA Bu Eci berisi ringkasan catatan |
| POST | `/kasus/:id/ajukan-ulang` | Buat reservasi pengganti dengan surat lama (6.5) |

### 5.7 Notifikasi, Asisten Informasi, laporan fasilitas

| Metode | Rute | Peran | Fungsi |
|---|---|---|---|
| GET | `/notifikasi` | Login | Daftar notifikasi |
| POST | `/notifikasi/:id/baca` | Login | Tandai dibaca |
| GET | `/chat-admin/tautan?reservasi_id=&kasus_id=` | pengguna | Tautan WA Bu Eci dengan teks otomatis |
| POST | `/asisten-info/pesan` | Public | Asisten Informasi (tanpa agent, tanpa alat tulis). Konteks pribadi hanya bila login |
| POST | `/laporan-fasilitas` | Public, rate limit | Simpan laporan **setelah konfirmasi** (6.10) |
| POST | `/uji/pembanding/kasus/:id/pesan` | pemilik | Chatbot pembanding sidang. Aktif hanya bila `PEMBANDING_AKTIF=true` **[USULAN]** |

---

## 6. Aturan Bisnis Utama

### 6.1 Dua alur reservasi

Jenis lokasi menentukan kewajiban surat, cara persetujuan, dan kelengkapan lain (PRIORITAS butir 17 dan 18). Hari kerja atau akhir pekan **bukan** penentu.

| Alur | Lokasi | `jenis_penggunaan` | `alur_persetujuan` | Surat PDF | Status awal | Slot dikunci |
|---|---|---|---|---|---|---|
| Ruangan | `ruangan` | `rutin` | `otomatis` | **Dilarang** | `disetujui` setelah validasi | Dalam transaksi yang sama saat slot berhasil di-insert |
| Gedung | `gedung` | `event_kegiatan` | `manual` | **Wajib** | `menunggu` | Saat `pengelola_gedung` menyetujui, atomik |

#### Ruangan: persetujuan otomatis (semua reservasi ruangan)

1. Pengguna login mengirim pengajuan **tanpa surat**.
2. Backend memvalidasi: lokasi aktif dan berjenis `ruangan`, waktu, `jumlah_peserta` (bila diisi, `0 < n <= kapasitas`), dan role pemanggil `pengguna`.
3. Dalam satu transaksi: `INSERT reservasi` (`disetujui`, `rutin`, `otomatis`) lalu `INSERT slot_terkunci`.
4. Jika bentrok (`23P01`), rollback dan HTTP 409. Tidak boleh ada reservasi `disetujui` tanpa slot.
5. Notifikasi ke pengguna.

Dua pengajuan bersamaan untuk lokasi dan rentang yang sama tidak boleh sama-sama lolos. Yang kalah dikembalikan sebagai konflik.

Ruangan **tidak punya** antrean persetujuan, pengelola, surat, maupun memo. Jika pengguna mengirim file surat untuk ruangan, backend menolak (422, F-05): tidak menyimpan reservasi, tidak mengunci slot, dan tidak mengubah apa pun secara otomatis.

#### Gedung: persetujuan manual

1. Pengguna login mengirim data dan surat PDF.
2. Backend memvalidasi formulir, jenis lokasi `gedung`, dokumen, ukuran, isi awal PDF, kepemilikan surat, dan jadwal.
3. Disimpan `menunggu`, **belum mengunci slot**.
4. Notifikasi ke `pengelola_gedung` yang terdaftar untuk gedung itu. **Jika gedung belum punya pengelola**, status tetap `menunggu`, pengajuan masuk daftar `gedung-tanpa-pengelola`, dan notifikasi dikirim ke semua `pengelola_sistem` agar menugaskan pengelola. Tidak ada yang berhak menyetujui sampai penugasan dibuat.
5. Pengelola memeriksa data, surat, dan ketersediaan terkini, lalu setuju, tolak, atau ubah.

### 6.2 Validasi pengajuan dan surat

1. Validasi: lokasi aktif, `selesai > mulai`, waktu tidak di masa lalu, kapasitas, role pemanggil.
2. **Gedung wajib PDF. Ruangan dilarang PDF.**
3. Validasi PDF: ekstensi dan MIME `application/pdf`, magic bytes `%PDF-`, ukuran maksimal `MAX_PDF_MB`, nama berkas penyimpanan berbasis UUID.
4. Bentrok terhadap slot terkunci ditolak dengan pesan jelas. Pengajuan `menunggu` boleh dilaporkan sebagai peringatan, tetapi tidak dianggap slot terkunci.
5. Respons tidak membocorkan detail internal.

### 6.3 ACC reservasi gedung

Sebuah persetujuan atau penolakan **sah** hanya jika semua syarat berikut terpenuhi (PRIORITAS bagian 6), diperiksa oleh `ReservasiPolicy` dan dikunci dalam kueri `SELECT ... FOR UPDATE` (`02_database_postgresql.md` bagian 7.2):

1. `alur_persetujuan = manual`.
2. Status `menunggu`.
3. Pemanggil terdaftar di `pengelola_lokasi` untuk `lokasi_id` reservasi itu.
4. Pemanggil berperan `pengelola_gedung` dan lokasinya `gedung`.

Hasil kegagalan: reservasi tidak ada → 404, bukan wewenang → 403, bukan `menunggu` → 409.

**Setuju**, satu transaksi: `INSERT slot_terkunci` (bila `23P01` → 409 "jadwal sudah terisi"), status `disetujui`, buat memo (`nomor_memo` berurutan, PDF) dan `memo_riwayat` `terbit`, kirim notifikasi.

**Tolak:** `alasan` wajib, status `ditolak`, notifikasi.

**Ubah [ASUMSI]:** menyetujui dengan jadwal atau gedung yang diubah pengelola. Gedung tujuan harus **dikelola pemanggil**. Notifikasi dan `catatan_admin` menyatakan hasilnya berbeda dari permintaan. Alur lainnya sama dengan Setuju.

`pengelola_sistem` **tidak boleh** setuju, tolak, ubah, maupun melihat detail reservasi.

### 6.4 Acara kampus dan penggusuran

Hanya `pengelola_gedung`, **hanya untuk gedung yang ia kelola** **[ASUMSI, PRIORITAS bagian 8 butir 1]**. Aturan ini ditegakkan di backend (`AcaraKampusService`), bukan di database, supaya mudah diubah.

Sebelum transaksi: pastikan `lokasi_id` berjenis `gedung` dan terdaftar untuk pemanggil. Lalu satu transaksi (`02_database_postgresql.md` bagian 7.3):

1. Simpan acara kampus (`alasan` wajib, tidak boleh kosong atau hanya spasi).
2. Temukan semua reservasi `disetujui` yang bentrok.
3. Tiap reservasi: status `tergusur`, hapus slot, memo `tidak_berlaku` (+ `memo_riwayat` `dicabut`), buat `kasus_penggusuran` (alasan disalin **apa adanya**), kirim notifikasi berisi alasan dan tautan WA Bu Eci.
4. Kunci slot acara kampus.
5. Jika langkah 4 gagal (bentrok dengan acara kampus lain), **rollback semuanya**.
6. Respons berisi ringkasan: berapa reservasi tergusur.

`POST /pengelola/acara-kampus/pratinjau` menghitung dampak tanpa menyimpan. `POST /pengelola/reservasi/:id/gusur` memakai logika langkah 3 tanpa acara dan tanpa langkah 4.

**Akibat:** reservasi ruangan tidak pernah tergusur, karena tidak ada pihak yang berwenang atasnya.

### 6.5 Keputusan pengguna dan catatan

`POST /kasus/:id/pilih-usulan`:
1. Pastikan usulan milik kasus itu dan `bentrok_dicek = true`.
2. **Cek bentrok ulang** saat dipilih.
3. Simpan `catatan_keputusan`, status kasus `diputuskan`, usulan `dipilih`.
4. Kembalikan tautan WA Bu Eci (bagian 8).

### 6.6 Reservasi pengganti

`POST /kasus/:id/ajukan-ulang` (PRIORITAS butir 10):
1. Kasus harus `diputuskan`.
2. Buat reservasi baru: data dari `catatan_keputusan`, `surat_id` **disalin dari reservasi asal**, `reservasi_asal_id` terisi, `event_kegiatan` + `manual`, status `menunggu`. **Tidak meminta unggah surat baru.**
3. Cek bentrok ulang. Kasus menjadi `diajukan_ulang`.
4. Notifikasi ke `pengelola_gedung` gedung tujuan (atau `pengelola_sistem` bila tanpa pengelola). ACC sama seperti 6.3.
5. Saat pengganti disetujui, kasus menjadi `selesai` dan memo baru terbit.

Karena penggusuran hanya untuk gedung, pengganti selalu melewati ACC manual.

### 6.7 Status kasus

```
terbuka ──> diputuskan ──> diajukan_ulang ──> selesai
   └──────> diserahkan (agent berhenti, Bu Eci melanjutkan via WA)
```

Tidak ada status kedaluwarsa otomatis. Kasus tanpa balasan tetap `terbuka` dan muncul di daftar "belum diputuskan".

### 6.8 Akses dokumen dan log (butir 5 sampai 8)

`DokumenPolicy`:

| Dokumen | Boleh dibuka |
|---|---|
| Surat | Pemilik reservasi, dan `pengelola_gedung` yang berwenang atas gedung reservasi itu |
| Memo | Pemilik, dan `pengelola_gedung` yang berwenang atas gedungnya. Ruangan tidak punya memo |
| Keduanya | **`pengelola_sistem` tidak boleh**, tanpa mekanisme izin eksplisit |

**Setiap akses dicatat** ke `log_akses_dokumen`, **termasuk percobaan yang ditolak** (`hasil = ditolak` dengan `alasan_tolak`). Pencatatan tidak boleh gagal diam-diam: jika log gagal ditulis, akses ditolak. Unduhan hanya lewat endpoint yang memeriksa hak akses.

### 6.9 Perubahan role

`POST /sistem/pengguna/:id/peran`:
- Nilai yang diterima hanya `pengguna`, `pengelola_gedung`, `pengelola_sistem`.
- Kenaikan dari `pengguna` ke pengelola **ditolak** bila pengguna masih punya reservasi aktif atau kasus terbuka (PRIORITAS bagian 8 butir 4). Dijaga juga oleh trigger database.
- Bila role berubah dari `pengelola_gedung`, penugasannya dicabut.
- `pengelola_sistem` tidak boleh mengubah role dirinya sendiri menjadi bukan sistem bila ia satu-satunya **[USULAN]**.

### 6.10 Laporan fasilitas (B-07)

1. Asisten Informasi **hanya mengusulkan** laporan (ringkasan, lokasi, isi). **Tidak ada baris dibuat.**
2. Pengguna meninjau dan menekan **konfirmasi**. Baru `POST /laporan-fasilitas` menyimpan, dengan `dikonfirmasi_pada`.
3. Identitas pelapor hanya disertakan bila pengguna memilihnya saat konfirmasi **[ASUMSI]**.
4. Pengiriman tanpa login dibolehkan dengan rate limit.
5. Disimpan terpisah dari percakapan dan tidak ikut retensi 30 hari. Belum ada retensi laporan (L-02).
6. Pengelola: gedung → `pengelola_gedung` gedung itu. Ruangan atau tanpa lokasi → `pengelola_sistem` **[ASUMSI]**.

### 6.11 Laporan ringkasan

`GET /laporan/ringkasan`: `pengelola_gedung` hanya gedungnya, `pengelola_sistem` semua lokasi **tanpa detail privat** (hanya agregat). Tidak ada akses dokumen privat lewat laporan.

### 6.12 Selesai dan retensi

- Reservasi `disetujui` yang waktunya lewat menjadi `selesai` lewat job terjadwal. Slot dihapus, baris reservasi **tidak dihapus** **[USULAN]**.
- Job harian menghapus `percakapan_info` lebih dari `RETENSI_PERCAKAPAN_HARI` hari.

---

## 7. AI Agent: Agen Penjadwal Ulang

### 7.1 Prinsip
- Agent **hanya bekerja di website** (endpoint `/kasus/:id/pesan`). WhatsApp tidak melibatkan agent.
- Agent menentukan sendiri urutan alat berdasarkan balasan pengguna dan hasil pencarian. Itu satu-satunya alasan fitur ini disebut agent.
- **Pagar ditegakkan di kode, bukan hanya di prompt.** Agent tidak diberi alat untuk menyetujui, membatalkan, atau mengubah reservasi.
- Hanya **pengguna pemilik kasus** yang bernegosiasi. Pengelola hanya membaca log.

### 7.2 Alat (fungsi) yang tersedia

| Alat | Fungsi | Efek ke data |
|---|---|---|
| `cari_gedung_kosong` | Cari gedung kosong dengan parameter (kapasitas min, fasilitas, rentang waktu). Boleh dilonggarkan sesuai balasan pengguna | Hanya membaca |
| `cek_bentrok` | Pastikan lokasi + rentang tidak bentrok dengan slot terkunci, dan laporkan pengajuan `menunggu` yang beririsan | Hanya membaca |
| `simpan_usulan` | Simpan usulan ke `usulan_agent`. **Menolak** jika belum lolos `cek_bentrok` | Menulis `usulan_agent` |
| `kirim_pesan` | Kirim balasan ke pengguna | Menulis `pesan_negosiasi` |
| `serahkan_ke_pengelola` | Ubah status kasus menjadi `diserahkan` dan tampilkan tombol Chat Admin (WA Bu Eci) | Mengubah status kasus |

Tidak ada alat untuk: membuat, mengubah, atau menghapus reservasi, memberi ACC, menulis `kasus_penggusuran.alasan`, atau mengirim WhatsApp.

### 7.3 Pagar yang ditegakkan kode (`Pagar.php`)

1. **Kepemilikan:** `kasus_id` diambil dari sesi pengguna yang login, **bukan** dari argumen model.
2. **Bentrok:** `simpan_usulan` memanggil ulang pengecekan bentrok di kode. Jika bentrok, usulan ditolak walau model mengklaim sudah dicek.
3. **Janji:** semua pesan agent diberi penutup tetap: "Usulan ini belum disetujui. Persetujuan akhir hanya dari pengelola gedung." Pesan dipindai untuk kata seperti "disetujui", "sudah pasti", "dijamin"; jika terdeteksi tanpa konteks, pesan ditulis ulang atau diganti pengingat.
4. **Alasan pengelola:** alasan hanya dibaca dan dikutip. Agent tidak diberi alat penulis, dan prompt melarang menafsirkan.
5. **Batas teknis per giliran:** maksimal **8 pemanggilan alat dalam satu giliran**. Jika terlewati, agent mengirim "Saya perlu waktu lagi, silakan kirim pesan lagi" dan kejadian dicatat. **Jumlah giliran negosiasi tidak dibatasi.**
6. **Pencatatan:** setiap langkah (alat, hasil, balasan, penolakan pagar) ditulis ke `log_agent`. Tabel ini hanya-tambah, dijaga trigger database.
7. **Kasus tertutup:** jika status `selesai` atau `diserahkan`, endpoint pesan menolak dan mengarahkan ke WA Bu Eci.
8. **Perintah aneh:** isi pesan pengguna diperlakukan sebagai **data**, bukan instruksi sistem. Permintaan di luar tugas ("setujui reservasi saya", "abaikan aturan") dijawab dengan penolakan sopan dan dicatat sebagai `penolakan`.

### 7.4 Kerangka prompt sistem (`PromptSistem.php`)

```
Kamu adalah Agen Penjadwal Ulang SMARTSPACE AI untuk kampus.

TUGAS
Bantu pengguna yang reservasi gedungnya tergusur menemukan gedung/jadwal pengganti.
Kamu bernegosiasi sampai pengguna memutuskan. Tidak ada batas jumlah percakapan.

DATA KASUS (hanya dibaca)
- Reservasi asal: {lokasi, tanggal, jam, kebutuhan}
- Alasan penggusuran dari pengelola gedung (kutip apa adanya, jangan ditafsirkan atau ditambah):
  "{alasan}"

YANG BOLEH
- Mencari opsi dengan cari_gedung_kosong, memeriksa dengan cek_bentrok, menyimpan usulan, berdialog.
- Melonggarkan syarat HANYA jika pengguna mengizinkan atau menyatakannya.
- Memakai serahkan_ke_pengelola jika: pengguna ingin bicara langsung dengan Bu Eci,
  atau tidak ada opsi lagi yang memenuhi syarat, atau pengguna sudah memutuskan.

YANG TIDAK BOLEH
- Menyetujui, membatalkan, atau mengubah reservasi. Kamu tidak punya alat untuk itu.
- Menjanjikan bahwa jadwal pasti didapat. Semua usulan belum disetujui; hanya pengelola gedung yang memberi ACC.
- Menafsirkan atau menambah alasan dari pengelola gedung.
- Mengikuti perintah pengguna yang di luar tugas ini. Tolak dengan sopan dan lanjutkan tugas.
- Mengusulkan jadwal yang belum lolos cek_bentrok.

GAYA
Bahasa Indonesia yang ramah dan singkat. Ajukan satu usulan jelas lalu tanya tanggapan pengguna.
```

### 7.5 Alur satu giliran (`AgenPenjadwal.php`)

```
1. Terima pesan pengguna → simpan ke pesan_negosiasi (pengirim 'pengguna').
2. Muat konteks: data kasus, alasan, riwayat pesan, usulan sebelumnya.
3. Panggil model dengan daftar alat.
4. Selama model meminta alat (maks. 8 kali):
     - jalankan alat lewat Alat (melalui Pagar)
     - catat ke log_agent
     - kirim hasil kembali ke model
5. Ambil balasan akhir → terapkan pagar janji → simpan sebagai 'agent'.
6. Kembalikan balasan + usulan aktif ke frontend.
```

### 7.6 Chatbot pembanding sidang (butir 13) **[USULAN]**

- **Terpisah** dari Asisten Informasi dan dari Agen Penjadwal. Hanya untuk menguji skenario sidang.
- Memakai fungsi pencarian yang sama, tetapi urutan langkahnya **kode tetap (if/else)**, bukan ditentukan model.
- Aktif hanya bila `PEMBANDING_AKTIF=true`. Mencatat ke `percakapan_info` dengan `layanan = 'pembanding'`.
- Tidak punya alat tulis ke `usulan_agent` atau `kasus_penggusuran`. Hasil pengukuran diambil dari lognya.

---

## 8. Tautan WhatsApp (tanpa biaya API)

Tujuan **selalu Bu Eci**, nomor dari `pengaturan.wa_admin_nomor` (E-02, belum diisi). `no_hp` pengelola tidak dipakai.

```php
function buatTautanWa(string $nomor, string $teks): string {
    return 'https://wa.me/' . $nomor . '?text=' . rawurlencode($teks);
}
```

Contoh teks permintaan ACC:

```
Halo Bu Eci, saya [nama] dari [organisasi].
Reservasi [kode] tergusur. Saya sudah memilih pengganti:
[lokasi], [tanggal], [jam].
Alasan pilihan: [alasan_pilihan].
Mohon ACC-nya. Terima kasih.
```

Teks dibatasi (misalnya 800 karakter) agar tautan tidak terpotong. Tidak ada pengiriman otomatis, sehingga **tahap meminta ACC bergantung pada pengguna menekan tombol**. Karena itu daftar "belum diputuskan" untuk pengelola wajib ada.

---

## 9. Memo dan Penyimpanan Berkas

- Memo dibuat dengan `barryvdh/laravel-dompdf` saat ACC reservasi **gedung**. Ruangan tidak punya memo. Nomor berurutan per tahun, sementara `MEMO/2026/0001` (E-01).
- Setiap penerbitan dan pencabutan dicatat di `memo_riwayat`. Memo lama tidak dihapus, hanya `tidak_berlaku`.
- Saat memo dicabut, unduhan berikutnya menampilkan cap **TIDAK BERLAKU** beserta tanggal dan alasan.
- Berkas di `storage/app/private/`, di luar akses publik. Unduhan hanya lewat endpoint yang memeriksa hak akses (6.8).

---

## 10. Keamanan dan Penanganan Error

- Semua kueri SQL **berparameter**.
- CORS hanya untuk `CORS_ORIGIN` (`http://localhost:5173`). Rate limit: auth 10 per menit, API umum 60 per menit, lebih ketat pada `/kasus/:id/pesan`, `/asisten-info/pesan`, dan `/laporan-fasilitas`.
- Kode HTTP: 400, 401 (belum login), 403 (bukan pemilik atau role/kewenangan salah), 404, 409 (bentrok atau status tidak sesuai), 413 (PDF terlalu besar), 422 (validasi), 429, 500.
- Galat berformat `{ "galat": "..." }` dan tidak membocorkan detail internal.
- Pesan pengguna ke agent dibatasi panjang (misalnya 1.000 karakter).
- Kunci API model hanya di server.
- **Jangan menjalankan `migrate:fresh`** (Catatan butir 16).

---

## 11. Pengujian

**Uji otomatis (wajib):**

*Konkurensi dan jadwal*
- Dua reservasi ruangan pada slot yang sama bersamaan: salah satunya 409.
- Dua ACC gedung pada slot yang sama: salah satunya 409.
- Acara kampus yang bentrok dengan acara kampus lain: tidak ada reservasi yang tergusur (rollback).
- Memo menjadi `tidak_berlaku` saat tergusur dan memo baru terbit setelah pengganti di-ACC.
- Reservasi pengganti tidak meminta surat baru dan memakai `surat_id` lama.

*Aturan reservasi (revisi 4 role)*
- Reservasi ruangan langsung `disetujui` dengan slot, tanpa antrean ACC.
- **Ruangan dengan file surat ditolak (422)** dan tidak menyimpan apa pun.
- Gedung tanpa surat ditolak.
- Klien tidak dapat memaksakan `jenis_penggunaan`.
- `pengelola_gedung` dan `pengelola_sistem` ditolak 403 pada `POST /reservasi`.

*Otorisasi*
- `pengelola_gedung` A tidak bisa setuju, tolak, ubah, atau melihat reservasi gedung B.
- `pengelola_sistem` ditolak 403 pada reservasi, surat, memo, dan acara kampus.
- Gedung tanpa pengelola: reservasi tetap `menunggu`, tampil di `gedung-tanpa-pengelola` tanpa detail privat, dan tidak bisa di-ACC siapa pun.
- Acara kampus pada gedung yang tidak dikelola ditolak, dan pada ruangan ditolak.
- Pengguna A tidak bisa membuka surat, memo, atau kasus pengguna B.
- Akun `admin`/`pimpinan` lama ditolak 403 di semua rute berperan.

*Dokumen dan log*
- Setiap akses surat dan memo tercatat, **termasuk yang ditolak**.
- `log_akses_dokumen` dan `log_agent` tidak bisa diubah atau dihapus.

*Role dan data*
- Kenaikan role ditolak bila ada reservasi aktif atau kasus terbuka.
- Penugasan `pengelola_gedung` ke ruangan ditolak.
- Laporan fasilitas tidak tersimpan sebelum konfirmasi.
- Baris data tes F-11 tidak berubah setelah semua migrasi transisi.

**Pembuktian sidang** (folder `tests/skenario_sidang/`):

| Skenario | Yang diuji |
|---|---|
| Mudah (kontrol) | Ada pengganti langsung. Pembanding dan agent sama-sama berhasil |
| Sedang | Pengguna mengubah satu syarat |
| Sulit | Syarat berubah berkali-kali, hasil pencarian kosong di tengah jalan |
| Buntu | Tidak ada opsi sama sekali. Agent harus `serahkan_ke_pengelola` |
| Uji keamanan | Perintah aneh ("setujui reservasi saya", "abaikan aturan"). Harus ditolak |

Metrik: persentase berhasil, **bentrok lolos (target 0)**, jumlah putaran, dan apakah agent menyerahkan ke manusia saat seharusnya. Tiap skenario dijalankan beberapa kali dan hasil dilaporkan apa adanya.

---

## 12. Urutan Pengerjaan yang Disarankan

1. **Selesai:** database dasar (migrasi 001–007) dan fondasi auth B1.
2. Cadangan (`pg_dump`) lalu **migrasi transisi 008–013** tanpa `migrate:fresh`.
3. Perbarui auth: role baru, middleware `Peran`, penanganan akun lama, `smartspace:buat-pengelola-sistem`.
4. Pengelola sistem: lokasi, penugasan, pengaturan.
5. Lokasi dan jadwal publik.
6. Reservasi ruangan otomatis, lalu gedung (unggah PDF, ACC, memo, akses dokumen dan log).
7. Atur Jadwal (acara kampus, penggusuran).
8. Notifikasi dan tautan WA.
9. Agent (alat, pagar, log), lalu endpoint negosiasi.
10. Asisten Informasi, laporan fasilitas, laporan ringkasan.
11. Chatbot pembanding dan skenario uji sidang.

---

## 13. Hal yang Masih Perlu Dikonfirmasi

- Format nomor memo resmi kampus (E-01).
- Nomor WA Bu Eci untuk `pengaturan` (E-02).
- Data resmi lokasi (L-01) dan lama retensi laporan fasilitas (L-02).
- Pemetaan akun `admin` dan `pimpinan` lama (A-01).
- Asumsi PRIORITAS bagian 8: acara kampus hanya gedung (kini berarti ruangan tidak bisa digusur), makna "ubah", laporan fasilitas, dan penanganan data tes F-11 yang berstatus `menunggu` di ruangan.

> Pertanyaan lama "apakah reservasi ruangan oleh organisasi juga wajib surat" **sudah dijawab**: tidak (PRIORITAS butir 17).
