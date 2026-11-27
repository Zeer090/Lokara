# SMARTSPACE AI: 00 PRIORITAS

**Status:** final, direvisi 2026-11-08 (revisi 4 role, menggantikan revisi 5 role 2026-11-06).
**Fungsi:** menentukan dokumen mana yang menang jika dua dokumen berbeda, dan mencatat bagian `KONTRAK_DATA_BISNIS.md` yang dibaca berbeda setelah revisi role.

---

## 1. Urutan prioritas jika terjadi konflik

| Urutan | Dokumen | Peran |
|---|---|---|
| 1 | `CATATAN_KEPUTUSAN.md` | Keputusan teknis yang mengikat (versi, lingkungan, port, token, kebijakan migrasi). Perubahan dicatat ulang, tidak dihapus diam-diam. |
| 2 | `00_PRIORITAS.md` (bagian 3 sampai 7) | Revisi role dan tabel pergantian istilah Kontrak. |
| 3 | `KONTRAK_DATA_BISNIS.md` | Aturan bisnis final (F-01 sampai F-11, B-01 sampai B-07), dibaca bersama tabel pergantian di bagian 5. |
| 4 | `01_backend.md`, `02_database_postgresql.md`, `03_frontend.md` | Rancangan per area. Mengikuti dokumen di atasnya. |
| 5 | Kode dan migrasi aktual | Bukti status implementasi, **bukan** sumber aturan bisnis. |

Aturan tambahan:
- Jika dua dokumen **setingkat** berbeda, jangan menebak. Tanyakan pemilik proyek, lalu catat jawabannya di `CATATAN_KEPUTUSAN.md`.
- Jika kode berbeda dari dokumen, dokumen yang benar dan kode yang diperbaiki. Perbedaan itu dicatat sebagai temuan, bukan dianggap aturan baru.
- Tanda **[ASUMSI]** atau **[USULAN]** berarti belum diputuskan pemilik proyek dan boleh dikoreksi.

---

## 2. Ringkasan revisi

**Revisi 2026-11-06** mengganti role lama (`admin`, `pimpinan`) dengan kategori akses baru dan menambah kewenangan per lokasi.

**Revisi 2026-11-08** meniadakan pengajuan ruangan memakai surat. **Semua reservasi ruangan otomatis dan tanpa surat.** Akibatnya role `pengelola_ruangan` tidak punya tugas dan dihapus. Kategori akses tersisa **empat**.

Keputusan bisnis F-01, F-03, F-04, F-07 sampai F-11 dan B-01 sampai B-07 **tetap berlaku**. Yang gugur atau berubah: F-02 (gugur), F-05 dan F-06 (bentuk berubah), B-02 (pelaksana berubah), B-03 (dihapus). Rinciannya di bagian 5.

---

## 3. Empat kategori akses

| Kategori | Disimpan di DB | Fungsi |
|---|---|---|
| Public | Tidak | Melihat lokasi, jadwal publik, ketersediaan, prosedur, dan memakai Asisten Informasi tanpa login. |
| `pengguna` | Ya | Mengajukan dan mengelola reservasi sendiri, notifikasi, negosiasi untuk kasus sendiri, dan Asisten Informasi dengan konteks pribadi. |
| `pengelola_gedung` | Ya | Memutuskan (setuju, tolak, ubah) reservasi gedung berstatus `menunggu` di gedung yang dikelolanya. Mendaftarkan acara kampus dan menggusur reservasi di gedungnya. Saat ini hanya akun Bu Eci. |
| `pengelola_sistem` | Ya | Administrasi teknis: akun, role, penugasan pengelola ke gedung, data lokasi, pengaturan, dan melihat log. Tidak menyentuh reservasi dan dokumen. |

Aturan yang berlaku untuk semua:
- Tidak ada admin global, tidak ada role `pimpinan`, dan tidak ada role `pengelola_ruangan`. Halaman `/pimpinan` dihapus.
- `pengelola_gedung` dan `pengelola_sistem` **tidak boleh mengajukan reservasi**. Jika perlu memesan, mereka memakai akun `pengguna` terpisah.
- **Reservasi ruangan tidak punya pengelola dan tidak punya antrean persetujuan.** Ruangan dipesan langsung oleh `pengguna` dan disetujui sistem.
- Frontend bukan lapisan keamanan. Semua otorisasi ditegakkan di backend.

---

## 4. Keputusan yang ditetapkan

| # | Topik | Keputusan |
|---|---|---|
| 1 | Gedung dan ruangan | Berdiri sendiri, **tanpa relasi induk**. Tidak semua gedung atau ruangan Polinela bisa direservasi. Hanya yang ada di tabel `lokasi` yang muncul dan bisa dipesan. Data resmi menyusul. Sembilan lokasi di seeder dipertahankan sebagai **data contoh bertanda sementara**. |
| 2 | Penugasan pengelola | Tabel `pengelola_lokasi` menautkan `pengelola_gedung` ke **gedung**. Ruangan tidak punya penugasan. Dibiarkan kosong dulu. `pengelola_sistem` yang mengisi dan mengelola data lokasi. |
| 3 | Gedung tanpa pengelola | Reservasi gedung tetap `menunggu` dan ditandai untuk `pengelola_sistem` agar menugaskan pengelola. Tidak ada yang berhak menyetujui sampai penugasan dibuat. |
| 4 | Acara kampus dan penggusuran | Hanya `pengelola_gedung`, **hanya untuk gedung yang ia kelola** **[ASUMSI, lihat bagian 8]**. `pengelola_sistem` tidak boleh. |
| 5 | Surat | Hanya ada untuk reservasi **gedung**. Dibuka pemilik reservasi dan `pengelola_gedung` yang berwenang atas gedung itu. |
| 6 | Memo | Hanya ada untuk reservasi **gedung**, terbit setelah pengelola gedung menyetujui. Dibuka pemilik dan pengelola gedung yang berwenang. Ruangan tidak punya memo. |
| 7 | Log akses dokumen | Setiap akses surat dan memo dicatat, termasuk percobaan yang ditolak. |
| 8 | `pengelola_sistem` dan dokumen | Tidak punya akses surat maupun memo, dan tidak ada mekanisme izin eksplisit. |
| 9 | Laporan ringkasan | `/laporan/ringkasan` untuk `pengelola_gedung` (hanya gedungnya) dan `pengelola_sistem` (semua lokasi, tanpa detail privat). |
| 10 | Reservasi pengganti | Mengikuti alur jenis asalnya. Karena penggusuran hanya berlaku untuk gedung (butir 4), dalam praktik pengganti selalu reservasi gedung manual dengan surat lama. |
| 11 | Handoff WhatsApp | Selalu ke Bu Eci. Nomor di tabel `pengaturan` (`wa_admin_nomor`), belum diisi (E-02). `no_hp` pengelola tidak dipakai. |
| 12 | Laporan fasilitas (B-07) | Dipertahankan. Chatbot hanya mengusulkan, pengguna wajib konfirmasi, disimpan di tabel terpisah. |
| 13 | Chatbot pembanding sidang | Dibuat terpisah dari Asisten Informasi, hanya untuk pengujian skenario sidang. |
| 14 | Port dan token | Backend `:8000`, frontend `:5173`, Sanctum **Bearer token** (bukan JWT, bukan cookie). Mengubah nilai `localhost:3000` di Catatan. |
| 15 | Akun lama | `admin` dan `pimpinan` dipetakan setelah audit dan persetujuan pemilik proyek. Bu Eci menjadi `pengelola_gedung`. Akun lain ditentukan satu per satu oleh pemilik proyek. |
| 16 | Migrasi | Hanya migrasi transisi. **Tidak ada `migrate:fresh`**, supaya data tes F-11 aman. |
| 17 | **Ruangan otomatis tanpa surat** (2026-11-08) | Semua reservasi ruangan berstatus `disetujui` otomatis begitu validasi lolos dan slot berhasil dikunci. Ruangan **tidak menerima surat**, tidak punya persetujuan manual, tidak punya pengelola, dan tidak punya memo. Role `pengelola_ruangan` dihapus. Menutup pertanyaan terbuka Catatan "apakah reservasi ruangan oleh organisasi wajib surat": **tidak**. |
| 18 | Jenis penggunaan | Pengguna **tidak lagi memilih** jenis penggunaan. Ditentukan oleh jenis lokasi: ruangan → `rutin` + `otomatis`, gedung → `event_kegiatan` + `manual`. Kolom `jenis_penggunaan` dan `alur_persetujuan` tetap disimpan (F-07, F-08) **[USULAN: tetap dipertahankan untuk riwayat dan kosakata Kontrak, walau bisa diturunkan dari jenis lokasi]**. |

---

## 5. Tabel pergantian istilah dan bagian Kontrak

Kontrak ditulis sebelum revisi role. Bagian berikut dibaca menurut kolom kanan.

| Di Kontrak | Dibaca sebagai |
|---|---|
| Tamu | Public |
| Admin (umum) | Disebut menurut konteks: `pengelola_gedung` atau `pengelola_sistem`. Tidak ada admin global. |
| Bu Eci / pengelola gedung (alur 3, K-04, F-03, P-06) | `pengelola_gedung`. Saat ini akun Bu Eci. |
| **Alur 2 "ruangan dengan surat", F-02, K-02, K-03, P-05, tabel approver baris `ruangan + Event/Kegiatan`** | **Gugur (2026-11-08).** Tidak ada ruangan dengan surat dan tidak ada persetujuan manual untuk ruangan. |
| F-01, K-01, alur 1 (ruangan tanpa surat) | **Berlaku untuk semua reservasi ruangan**, bukan hanya jenis `rutin` pilihan pengguna. |
| F-05, K-06, K-07 (`ruangan + rutin` dengan surat tidak valid) | Dibaca: **ruangan yang menerima file surat adalah input tidak valid.** Sistem menolak, tidak menyimpan reservasi, tidak mengunci slot, dan tidak mengubah apa pun otomatis. |
| F-06, K-08, D-02 (pengguna memilih jenis penggunaan) | Tidak lagi ada pilihan. Jenis ditentukan jenis lokasi (butir 18). Backend tetap menolak kombinasi yang tidak cocok. |
| F-04, K-05 (`gedung + rutin` dilarang) | Tetap, dan berlaku otomatis karena gedung selalu `event_kegiatan`. |
| F-07, F-08, K-09, K-10 | Tetap. Kolom disimpan. |
| F-09 | Kewenangan approver hanya untuk gedung: `pengelola_gedung` yang terdaftar di `pengelola_lokasi`. |
| Pimpinan, B-03, D-19 | **Dihapus.** Laporan ringkasan dilihat `pengelola_gedung` (gedungnya) dan `pengelola_sistem` (semua). Tidak ada akses dokumen privat tanpa kewenangan lokasi. |
| B-02, D-18 ("admin yang berwenang") | `pengelola_gedung` yang terdaftar di `pengelola_lokasi` untuk gedung reservasi itu. `pengelola_sistem` tidak termasuk. |
| P-12, P-18, D-11, I-03 (`peran:admin` global) | Digantikan middleware peran ditambah policy per lokasi (bagian 6). |
| D-09, D-10, I-01, I-02 (relasi pengelola–lokasi) | Dijawab oleh tabel `pengelola_lokasi` dan role `pengelola_gedung`, **hanya untuk gedung**. |
| Bagian 8, tabel hak akses menurut peran | Digantikan matriks di bagian 7 dokumen ini. |
| Bagian 7.5, route `/admin/...` | Prefix menjadi `/pengelola/...` (dan `/sistem/...`). |
| D-29 / E-02 (nomor WA) | Tetap, nomor milik Bu Eci di `pengaturan`. |
| D-28 / E-01 (format memo) | Tetap, sementara `MEMO/YYYY/0001`. |

Bagian Kontrak yang tidak disebut di atas tetap berlaku apa adanya.

---

## 6. Aturan otorisasi persetujuan (ACC)

Satu sumber kebenaran untuk backend, database, dan frontend. Sebuah persetujuan atau penolakan **sah** hanya jika **semua** syarat berikut terpenuhi:
1. `alur_persetujuan = manual`.
2. Status reservasi `menunggu`.
3. Pemanggil terdaftar di `pengelola_lokasi` untuk `lokasi_id` reservasi itu.
4. Pemanggil berperan `pengelola_gedung` dan lokasinya berjenis `gedung`.

Jika gedung belum punya pengelola, pengajuan tetap `menunggu` dan ditandai untuk `pengelola_sistem`. Reservasi otomatis (semua reservasi ruangan) tidak punya antrean ACC.

---

## 7. Matriks hak akses (pengganti Kontrak bagian 8)

| Kemampuan | Public | pengguna | pengelola_gedung | pengelola_sistem |
|---|:-:|:-:|:-:|:-:|
| Lokasi, jadwal publik, prosedur | Ya | Ya | Ya | Ya |
| Asisten Informasi | Ya | Ya | Ya | Ya |
| Mengajukan reservasi (ruangan otomatis, gedung dengan surat) | Tidak | Ya | Tidak | Tidak |
| Riwayat dan pembatalan reservasi sendiri | Tidak | Ya | Tidak | Tidak |
| Mengunggah surat (hanya untuk gedung) | Tidak | Ya | Tidak | Tidak |
| Melihat reservasi orang lain | Tidak | Tidak | Gedung yang dikelola | Tidak (hanya daftar gedung tanpa pengelola dan jumlah pengajuan menunggu) |
| Membaca surat | Tidak | Milik sendiri | Reservasi gedungnya | **Tidak** |
| Membaca memo | Tidak | Milik sendiri | Memo gedungnya | **Tidak** |
| Setuju / tolak / ubah reservasi manual | Tidak | Tidak | Gedung yang dikelola | **Tidak** |
| Mendaftarkan acara kampus dan menggusur | Tidak | Tidak | Gedung yang dikelola | **Tidak** |
| Melihat kasus penggusuran | Tidak | Milik sendiri | Gedung yang dikelola | Tidak |
| Negosiasi dengan AI Agent | Tidak | Kasus milik sendiri | Tidak | Tidak |
| Laporan ringkasan | Tidak | Tidak | Gedungnya | Semua lokasi, tanpa detail privat |
| Akun, role, penugasan, data lokasi, pengaturan | Tidak | Tidak | Tidak | Ya |
| Melihat log | Tidak | Tidak | Log agent kasus gedungnya (hanya baca) | Ya (hanya baca) |
| Laporan fasilitas | Mengirim setelah konfirmasi **[ASUMSI]** | Mengirim | Mengelola untuk gedungnya **[ASUMSI]** | Melihat semua, dan mengelola laporan ruangan **[ASUMSI]** |

---

## 8. Asumsi yang masih perlu dikoreksi

Tidak ada yang menghalangi pekerjaan. Semuanya tertulis di dokumen sehingga mudah diubah.

1. **Cakupan acara kampus dan penggusuran: hanya gedung.** Membaca jawaban pemilik proyek ("pengelola gedung, Bu Eci"). **Akibat setelah revisi 4 role:** reservasi ruangan tidak punya pihak yang berwenang menggusurnya, jadi ruangan tidak bisa digusur siapa pun. Jika ternyata ruangan juga perlu digusur, pemilik proyek perlu menentukan siapa yang berwenang (misalnya `pengelola_gedung` pada ruangan tertentu), dan pengganti `rutin` otomatis baru relevan pada kasus itu.
2. **"Ubah" berarti menyetujui dengan jadwal atau gedung yang diubah pengelola.** Pengguna diberi tahu bahwa hasilnya berbeda dari permintaan. Gedung hanya boleh dipindah ke gedung lain yang juga dikelola pemanggil.
3. **Laporan fasilitas:** pengiriman tanpa login diperbolehkan dengan rate limit. Identitas hanya disertakan jika pengguna memilihnya saat konfirmasi. `pengelola_gedung` mengelola laporan gedungnya. Karena ruangan tidak punya pengelola, **laporan ruangan dikelola `pengelola_sistem`**. Lama retensi laporan belum ditetapkan.
4. **Pengguna yang akunnya dinaikkan menjadi pengelola** tidak boleh memiliki reservasi aktif atau kasus terbuka. Kalau punya, kenaikan role ditolak.
5. **Data tes F-11** (satu reservasi ruangan berstatus `menunggu`, tanpa surat) kini tidak punya jalur keputusan, karena ruangan tidak lagi punya persetujuan manual. Sesuai F-11 data itu **tidak diubah, dihapus, disetujui, atau ditolak otomatis**. Pemilik proyek menentukan sendiri penanganannya.

---

## 9. Hal terbuka (tidak menghalangi)

| ID | Hal | Catatan |
|---|---|---|
| E-01 | Format nomor memo resmi kampus | Sementara `MEMO/YYYY/0001`. |
| E-02 | Nomor WhatsApp resmi Bu Eci | Sementara `62xxxxxxxxxxx` di `pengaturan`. |
| L-01 | Data resmi lokasi | Sembilan lokasi seeder tetap bertanda sementara. |
| L-02 | Lama retensi laporan fasilitas | Sampai ditetapkan, tidak ada penghapusan otomatis. |
| A-01 | Pemetaan akun `admin` dan `pimpinan` lama | Menunggu audit dan persetujuan pemilik proyek. |

---

## 10. Daftar dokumen

| Berkas | Keterangan |
|---|---|
| `00_PRIORITAS.md` | Dokumen ini. |
| `01_backend.md` | Endpoint, otorisasi, aturan bisnis, Asisten Informasi, AI Agent. |
| `02_database_postgresql.md` | Skema 18 tabel dan migrasi transisi. |
| `03_frontend.md` | Area, rute, formulir, dan panel per role. |
| `CATATAN_KEPUTUSAN.md` | Keputusan teknis mengikat, diperbarui dengan revisi ini. |
| `KONTRAK_DATA_BISNIS.md` | Tidak diubah. Dibaca bersama bagian 5 di atas. |

---

## 11. Riwayat revisi

| Tanggal | Perubahan |
|---|---|
| 2026-11-06 | Role `admin` dan `pimpinan` diganti lima kategori akses (Public, `pengguna`, `pengelola_gedung`, `pengelola_ruangan`, `pengelola_sistem`). Tabel `pengelola_lokasi` ditambah. |
| 2026-11-08 | Pengajuan ruangan memakai surat ditiadakan. Semua reservasi ruangan otomatis. `pengelola_ruangan` dihapus, tersisa empat kategori. F-02, K-02, K-03, P-05, dan alur 2 gugur. |
