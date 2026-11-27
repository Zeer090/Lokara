# SMARTSPACE AI: Rancangan Frontend

**Status:** diselaraskan dengan `00_PRIORITAS.md` (revisi 4 role, 2026-11-08). Bekerja berpasangan dengan `01_backend.md` (endpoint) dan `02_database_postgresql.md` (data).
**Urutan prioritas:** `CATATAN_KEPUTUSAN.md` > `00_PRIORITAS.md` > `KONTRAK_DATA_BISNIS.md` > dokumen ini > kode aktual.

> **Prinsip utama:** frontend **bukan lapisan keamanan**. Menyembunyikan tombol hanya soal kenyamanan. Semua otorisasi ditegakkan backend, dan frontend harus menangani 403 dengan baik.

> **Catatan:** pilihan teknologi di bawah adalah **[ASUMSI]**. Jika tim memakai kerangka lain, daftar halaman, komponen, dan perilaku antarmuka tetap berlaku.

---

## 1. Teknologi **[ASUMSI]**

| Kebutuhan | Pilihan |
|---|---|
| Kerangka | React 18 + Vite + TypeScript |
| Rute | React Router |
| Pengambilan data | TanStack Query (cache + polling) |
| Formulir | React Hook Form + zod |
| Gaya | Tailwind CSS |
| Kalender | FullCalendar |
| HTTP | axios, dengan interceptor **Bearer token** |
| Tanggal | date-fns (zona waktu Asia/Jakarta, bahasa Indonesia) |

---

## 2. Struktur Folder

```
frontend/
├── src/
│   ├── main.tsx
│   ├── App.tsx                  # definisi rute
│   ├── api/
│   │   ├── klien.ts             # axios + header Authorization + penanganan 401/403
│   │   ├── reservasi.ts
│   │   ├── kasus.ts
│   │   ├── jadwal.ts
│   │   ├── notifikasi.ts
│   │   ├── pengelola.ts
│   │   └── sistem.ts
│   ├── auth/
│   │   ├── AuthProvider.tsx
│   │   └── RuteTerlindungi.tsx  # pembatas peran (kenyamanan, bukan keamanan)
│   ├── halaman/
│   │   ├── publik/              # Beranda, Jadwal, Lokasi, Masuk, Daftar
│   │   ├── pengguna/            # Dashboard, Ajukan, Riwayat, Negosiasi
│   │   ├── pengelola/           # Dashboard, Pengajuan, AturJadwal, Kasus, LaporanFasilitas, Laporan
│   │   ├── sistem/              # Dashboard, Pengguna, Lokasi, Penugasan, GedungTanpaPengelola, Pengaturan, Log, LaporanFasilitas, Laporan
│   │   └── uji/                 # Pembanding (hanya bila diaktifkan)
│   ├── komponen/
│   │   ├── umum/                # Tombol, Kartu, Modal, Lencana, Kosong, Memuat
│   │   ├── reservasi/           # FormReservasi, UnggahPdf, KartuReservasi
│   │   ├── kalender/            # KalenderJadwal
│   │   ├── negosiasi/           # ChatNegosiasi, KartuUsulan, PanelAlasan
│   │   ├── pengelola/           # PanelPersetujuan, DaftarBelumDiputuskan
│   │   ├── notifikasi/          # LonceNotifikasi
│   │   └── TombolChatAdmin.tsx
│   ├── asisten/AsistenInfo.tsx  # Asisten Informasi (chatbot informasi)
│   └── util/                    # format tanggal, status, tautan WA
└── .env.example                 # VITE_API_URL
```

---

## 3. Peta Rute dan Halaman

Ada **empat kategori akses**: Public, `pengguna`, `pengelola_gedung`, `pengelola_sistem`. Tidak ada `/admin`, `/pimpinan`, maupun halaman `pengelola_ruangan`.

### 3.1 Public (tanpa login)

| Rute | Halaman | Isi |
|---|---|---|
| `/` | Beranda | Penjelasan singkat, cari ketersediaan cepat |
| `/jadwal` | Jadwal | Kalender publik + filter lokasi. **Hanya interval terisi**, tanpa nama pemesan atau keperluan |
| `/lokasi` | Daftar lokasi | Kapasitas, fasilitas. Lokasi `sementara` diberi lencana **"Data contoh"** |
| `/masuk`, `/daftar` | Login, registrasi | Registrasi selalu menghasilkan akun `pengguna` |
| (semua halaman) | Asisten Informasi | Tombol mengambang |

### 3.2 Pengguna (`/app/...`)

| Rute | Halaman | Alur |
|---|---|---|
| `/app` | Dashboard | Ringkasan reservasi, **banner kasus tergusur** |
| `/app/ajukan` | Ajukan reservasi | Lihat 4.1 |
| `/app/reservasi` | Riwayat reservasi | Daftar + filter status |
| `/app/reservasi/:id` | Detail reservasi | Status, surat dan memo sendiri, tombol batal, Chat Admin |
| `/app/negosiasi/:kasusId` | **Halaman negosiasi** | Lihat 4.3 |
| `/app/notifikasi` | Notifikasi | Daftar lengkap |

### 3.3 Pengelola gedung (`/pengelola/...`)

Hanya `pengelola_gedung`. Semua data **terbatas pada gedung yang ia kelola**. Saat ini akun Bu Eci.

| Rute | Halaman | Isi |
|---|---|---|
| `/pengelola` | Dashboard | Pengajuan gedung `menunggu`, kasus belum diputuskan, acara terdekat, gedung yang dikelola |
| `/pengelola/pengajuan` | Pengajuan | Reservasi gedungnya, filter status |
| `/pengelola/pengajuan/:id` | Pemeriksaan | Surat PDF + jadwal + **Setuju / Tolak / Ubah** |
| `/pengelola/atur-jadwal` | **Atur Jadwal** | Lihat 4.4 |
| `/pengelola/kasus` | Daftar kasus | Termasuk "belum diputuskan" |
| `/pengelola/kasus/:id` | Detail kasus | Usulan, pesan, log agent (hanya baca) |
| `/pengelola/laporan-fasilitas` | Laporan fasilitas | Laporan untuk gedungnya **[ASUMSI]** |
| `/pengelola/laporan` | Laporan ringkasan | Statistik gedungnya |

**Reservasi ruangan tidak muncul di panel ini.** Ruangan disetujui otomatis dan tidak punya pengelola. Tombol Setuju/Tolak/Ubah hanya tampil pada reservasi gedung berstatus `menunggu`.

### 3.4 Pengelola sistem (`/sistem/...`)

Hanya `pengelola_sistem`. **Tidak ada** halaman reservasi, surat, memo, kasus, atau negosiasi.

| Rute | Halaman | Isi |
|---|---|---|
| `/sistem` | Dashboard | Jumlah gedung tanpa pengelola, akun yang belum dipetakan |
| `/sistem/pengguna` | Akun dan role | Ubah role, aktif/nonaktif. Kenaikan role ditolak bila ada reservasi aktif atau kasus terbuka |
| `/sistem/lokasi` | Data lokasi | Tambah, ubah, aktif/nonaktif, penanda `sementara` |
| `/sistem/penugasan` | Penugasan | Tautkan `pengelola_gedung` ke gedung. **Ruangan tidak punya penugasan** |
| `/sistem/gedung-tanpa-pengelola` | Gedung tanpa pengelola | Daftar gedung dan **jumlah** pengajuan menunggu, tanpa detail privat |
| `/sistem/pengaturan` | Pengaturan | Termasuk `wa_admin_nomor` |
| `/sistem/log` | Log | Log agent dan log akses dokumen (hanya baca) |
| `/sistem/laporan-fasilitas` | Laporan fasilitas | Semua laporan, dan mengelola laporan ruangan **[ASUMSI]** |
| `/sistem/laporan` | Laporan ringkasan | Semua lokasi, tanpa detail privat |

### 3.5 Pengujian sidang

| Rute | Halaman |
|---|---|
| `/uji/pembanding` | Chatbot pembanding sidang. Hanya muncul bila fitur diaktifkan di backend. **Terpisah** dari Asisten Informasi dan dari Agen AI **[USULAN]** |

### 3.6 Perlindungan rute dan arah setelah login

`RuteTerlindungi` memeriksa role dari `/auth/saya`. Peran tidak cocok diarahkan ke halaman utama perannya.

| Role | Halaman utama |
|---|---|
| `pengguna` | `/app` |
| `pengelola_gedung` | `/pengelola` |
| `pengelola_sistem` | `/sistem` |
| Akun lama (`perlu_pemetaan: true`) | `/perlu-pemetaan`: "Akun ini belum dipetakan ke role baru. Hubungi pemilik proyek." Tidak ada menu lain |

`pengelola_gedung` dan `pengelola_sistem` **tidak melihat menu "Ajukan reservasi"**. Jika membuka `/app/ajukan`, tampil pesan: *"Akun pengelola tidak dapat mengajukan reservasi. Gunakan akun pengguna terpisah."*

---

## 4. Halaman Kunci

> **Status implementasi:** uraian di bagian ini adalah target perilaku UI. Fitur yang belum ada dicatat sebagai usulan, bukan diklaim sudah berjalan.

### 4.1 Ajukan Reservasi

**Pengguna tidak memilih jenis penggunaan.** Formulir menyesuaikan diri begitu lokasi dipilih (PRIORITAS butir 18). Tidak ada kolom "jenis penggunaan" di formulir. Hari dalam minggu tidak menjadi dasar apa pun.

Urutan: pilih lokasi lebih dulu, lalu formulir berubah.

#### Ruangan: disetujui otomatis, tanpa surat

- Isian: ruangan, tanggal, waktu mulai/selesai, jumlah peserta (opsional), tujuan penggunaan.
- **Kolom unggah surat tidak ditampilkan.**
- Tampilkan keterangan: *"Reservasi ruangan disetujui otomatis jika jadwal tersedia."*
- Tampilkan indikator ketersediaan (hijau/merah) dari `/lokasi/:id/jadwal`.
- Setelah berhasil, tampilkan status **Disetujui** dan detailnya.
- Jika bentrok atau validasi gagal (409/422), **jangan tampilkan sukses**. Tampilkan alasan dan tombol **Lihat lokasi lain**.

#### Gedung: persetujuan manual dengan surat

- Isian: gedung, tanggal, waktu mulai/selesai, jumlah peserta (opsional), tujuan kegiatan.
- `UnggahPdf` **wajib**: menolak non-PDF atau berkas lebih dari 5 MB sebelum dikirim.
- Setelah dikirim, tampilkan **Menunggu persetujuan pengelola gedung**.
- Bila gedung belum punya pengelola, tampilkan keterangan netral: *"Pengajuan diterima dan menunggu pengelola gedung."* Tidak perlu membuka detail penugasan.
- Setelah disetujui, tampilkan unduh memo PDF. Memo dapat berstatus **TIDAK BERLAKU** jika reservasi kemudian tergusur.

#### Perilaku umum

- Jika server membalas 409 (bentrok), tampil pesan jelas dan tombol **Lihat lokasi lain**.
- Tombol yang mengirim atau mengubah data dinonaktifkan selama permintaan berjalan.

### 4.2 Notifikasi dan banner penggusuran

- `LonceNotifikasi` di header, **polling tiap 30 detik**.
- Notifikasi penggusuran menampilkan:
  - Alasan dari pengelola gedung **persis seperti ditulis** (kotak kutipan, tanpa diubah).
  - Tombol **"Cari pengganti"** menuju `/app/negosiasi/:kasusId`.
  - Tombol **Chat Admin** (WA Bu Eci).
- Dashboard pengguna menampilkan banner merah selama ada kasus `terbuka` atau `diputuskan`.

### 4.3 Halaman Negosiasi (inti fitur agent)

Hanya pengguna pemilik kasus. Tata letak dua kolom (di ponsel: satu kolom, tab).

**Kolom kiri: `PanelAlasan` + ringkasan reservasi asal**
- Reservasi yang tergusur (gedung, tanggal, jam).
- Alasan dari pengelola gedung, dalam kotak kutipan.
- Label status kasus.
- Memo lama diberi lencana **TIDAK BERLAKU**.

**Kolom kanan: `ChatNegosiasi`**
- Gelembung pesan: pengguna, agent (label jelas **"Agen AI"**), dan sistem.
- Saat dibuka pertama kali tanpa pesan, frontend memanggil `/kasus/:id/pesan` dengan pesan awal otomatis agar agent langsung memberi rekomendasi.
- Indikator "Agen sedang mencari jadwal…".
- Kolom ketik dibatasi 1.000 karakter. **Tidak ada batas jumlah percakapan** dan tidak ada hitung mundur.
- Penutup pesan agent selalu terlihat: *"Usulan ini belum disetujui. Persetujuan akhir hanya dari pengelola gedung."*

**`KartuUsulan`** (muncul saat agent menyimpan usulan):
- Gedung, tanggal, jam, kapasitas, fasilitas.
- Lencana "Sudah dicek bentrok" (dari `bentrok_dicek`).
- Tombol **"Pilih ini"** dan **"Minta opsi lain"** (mengirim pesan otomatis ke agent).

**Alur setelah memilih:**
1. "Pilih ini" membuka modal konfirmasi berisi ringkasan.
2. Frontend memanggil `/kasus/:id/pilih-usulan`.
3. Tampil **layar langkah berikutnya**, tiga langkah berurutan:
   - Langkah 1: **"Minta ACC lewat WhatsApp"** (membuka tautan WA Bu Eci dengan ringkasan terisi).
   - Langkah 2: **"Ajukan reservasi"** (memanggil `/kasus/:id/ajukan-ulang`; **tanpa unggah surat**, tampil "Memakai surat yang sama").
   - Langkah 3: status menunggu ACC.
4. Penjelasan jujur: *"Pesan ke Bu Eci tidak terkirim otomatis. Tekan tombol di atas untuk membuka WhatsApp."*

**Jika agent menyerahkan ke manusia** (`status = diserahkan`):
- Kolom ketik dinonaktifkan.
- Tampil kartu besar: alasan penyerahan + tombol **Chat Admin**.

### 4.4 Atur Jadwal (`pengelola_gedung`)

Semua data **terbatas pada gedung yang dikelola**. Pemilih lokasi hanya menampilkan gedung tersebut, dan **tidak menampilkan ruangan**.

Tab:
1. **Kalender gabungan:** FullCalendar, warna per jenis: hijau (Disetujui), biru (acara kampus), merah (Tergusur), abu-abu (Menunggu, hanya penanda dan tidak mengunci).
2. **Daftarkan acara kampus:** nama, gedung, tanggal, jam, **alasan (teks bebas, wajib)**.
   - Sebelum disimpan, tampil **pratinjau dampak** dari `/pengelola/acara-kampus/pratinjau`: *"Acara ini akan menggusur N reservasi: …"*. Pengelola menekan konfirmasi.
   - Jika bentrok dengan acara kampus lain, tampil galat dan tidak ada yang berubah.
3. **Batalkan reservasi:** pilih reservasi, alasan wajib, konfirmasi.
4. **Belum diputuskan pengguna:** `DaftarBelumDiputuskan`, urut dari **acara terdekat**:
   - Kolom: organisasi, reservasi asal, tanggal acara, sisa hari, lama menunggu.
   - Baris dengan acara ≤ 3 hari disorot (angka 3 hanya awalan tampilan, bukan aturan sistem) **[ASUMSI]**.
   - Tombol **"Hubungi via WA"** membuka WA pengguna dengan teks pengingat.
5. **Usulan dan log agent:** per kasus, lihat usulan, percakapan, dan log langkah (hanya baca).

### 4.5 Persetujuan Reservasi Gedung (antarmuka sangat sederhana)

`/pengelola/pengajuan/:id` dan `/pengelola/kasus/:id` memakai satu panel dengan **tiga tombol besar**. Tombol hanya aktif pada reservasi gedung berstatus `menunggu`.

| Tombol | Perilaku |
|---|---|
| **Setuju** | Konfirmasi singkat, lalu `/pengelola/reservasi/:id/setuju` |
| **Tolak** | Kolom alasan wajib muncul, lalu `/tolak` |
| **Ubah** | Pemilih tanggal/jam, dan gedung **hanya dari gedung yang dikelola**, cek ketersediaan langsung, lalu `/ubah`. Pengguna akan diberi tahu bahwa hasilnya berbeda dari permintaan |

Di atas tombol: ringkasan organisasi, gedung, jadwal, **surat PDF yang bisa dibuka**, catatan keputusan dari agent, dan indikator bentrok. Untuk pengganti, tampil lencana **"Pengganti dari RSV-xxxx (tergusur)"**.

Setiap pembukaan surat dicatat oleh backend. Jika backend membalas 403, tampilkan *"Anda tidak berwenang atas reservasi ini"* dan **jangan** mencoba ulang.

### 4.6 Panel Pengelola Sistem

- **Penugasan:** pilih `pengelola_gedung`, pilih gedung, simpan. Daftar pilihan gedung hanya `jenis = gedung`. Ruangan tidak muncul.
- **Gedung tanpa pengelola:** tampil nama gedung, lencana "Data contoh" bila `sementara`, dan angka pengajuan menunggu. **Tidak ada** nama pemesan, keperluan, atau surat.
- **Ubah role:** tampilkan galat apa adanya bila ditolak karena reservasi aktif atau kasus terbuka.
- **Log:** dua tab (agent dan akses dokumen), hanya baca.
- Panel ini **tidak punya** tautan ke reservasi, surat, atau memo.

### 4.7 Chat Admin (WhatsApp)

Komponen `TombolChatAdmin`:
- Tombol hijau "Chat Bu Eci via WhatsApp". **Tujuan selalu Bu Eci** (PRIORITAS butir 11).
- Mengambil tautan dari `/chat-admin/tautan` (teks otomatis berisi kode reservasi, organisasi, ringkasan).
- Dibuka di tab baru (`target="_blank"`, `rel="noopener noreferrer"`).
- Ditempatkan di: notifikasi, halaman negosiasi, detail reservasi.
- Bila `wa_admin_nomor` belum diisi (E-02), tombol menampilkan *"Kontak admin belum tersedia"* dan tidak membuka tautan.

### 4.8 Asisten Informasi dan laporan fasilitas

- Tombol mengambang di semua halaman, memanggil `/asisten-info/pesan`.
- Berlabel jelas **"Asisten Informasi"** dan **berbeda tampilan** dari "Agen AI" di halaman negosiasi.
- Dapat dipakai tanpa login. Konteks pribadi hanya tersedia setelah login. Tindakan reservasi mengarahkan pengguna ke halaman Ajukan.
- Percakapan publik tersimpan **maksimal 30 hari**. Tampilkan catatan singkat tentang ini di bawah kolom ketik.

**Usulan laporan fasilitas (B-07):**
- Bila Asisten Informasi mengusulkan laporan, tampil **kartu konfirmasi** berisi ringkasan, lokasi, dan isi, dengan tombol **Kirim laporan** dan **Batal**.
- Pengguna yang login diberi **kotak centang** *"Sertakan identitas saya"*, **tidak tercentang** secara bawaan.
- **Tidak ada laporan tersimpan sebelum tombol Kirim ditekan.** Setelah dikirim, tampilkan konfirmasi sukses.

---

## 5. Status dan Lencana

| Status | Warna | Teks |
|---|---|---|
| menunggu | kuning | Menunggu persetujuan pengelola gedung |
| disetujui | hijau | Disetujui (otomatis untuk ruangan, oleh pengelola gedung untuk gedung) |
| tergusur | merah | Tergusur |
| selesai | abu-abu | Selesai |
| ditolak | merah tua | Ditolak |
| dibatalkan | abu-abu | Dibatalkan |
| memo `tidak_berlaku` | merah, dicoret | TIDAK BERLAKU |

Status kasus: `terbuka` = "Belum diputuskan", `diputuskan` = "Menunggu pengajuan ulang", `diajukan_ulang` = "Menunggu ACC", `selesai` = "Selesai", `diserahkan` = "Ditangani Bu Eci".

Status tidak boleh hanya diwakili warna. Selalu ada teks.

---

## 6. Pengelolaan Data dan Status

- **Token Bearer Sanctum** (bukan JWT, bukan cookie, PRIORITAS butir 14), dikirim lewat header `Authorization`. **[USULAN]** disimpan di `sessionStorage` dan dihapus saat keluar, saat 401, atau saat tab ditutup. Masa berlaku 8 jam.
- **TanStack Query** untuk semua data server. Kunci query: `['reservasi', id]`, `['kasus', id]`, `['notifikasi']`, dan seterusnya.
- **Polling:** notifikasi tiap 30 detik; halaman negosiasi tidak perlu polling karena balasan datang dari respons `POST /kasus/:id/pesan`; daftar "belum diputuskan" pengelola tiap 60 detik.
- **Optimistic UI** hanya untuk menampilkan pesan pengguna di chat. Data lain menunggu respons server.

---

## 7. Penanganan Galat dan Umpan Balik

| Situasi | Tampilan |
|---|---|
| 401 | Token dihapus, diarahkan ke `/masuk` dengan pesan "Sesi berakhir" |
| 403 | Halaman "Tidak punya akses", tanpa mencoba ulang. Untuk akun lama: `/perlu-pemetaan` |
| 409 bentrok | Pesan jelas + saran lokasi lain |
| 413 PDF terlalu besar | Pesan di bawah kolom unggah |
| 422 ruangan dengan surat | "Reservasi ruangan tidak memerlukan surat." Berkas dibuang dari formulir |
| Agent gagal/terlambat | "Agen sedang sibuk. Silakan kirim ulang, atau Chat Admin." + tombol Chat Admin |
| Jaringan putus | Banner "Koneksi terputus", pesan yang belum terkirim dipertahankan di kolom ketik |

---

## 8. Tampilan dan Aksesibilitas

- Bahasa antarmuka: **Indonesia**. Format tanggal: `Jumat, 6 November 2026, 15.00 WIB`.
- Mobile-first, karena pengguna kemungkinan membuka dari ponsel (apalagi tautan WA).
- Kontras warna cukup, status tidak hanya warna.
- Tombol aksi pengelola berukuran besar, karena antarmuka persetujuan harus sangat sederhana.
- Navigasi keyboard dan label formulir untuk pembaca layar.
- Lokasi `sementara` diberi lencana "Data contoh" sampai data resmi masuk (L-01).

---

## 9. Variabel Lingkungan (`.env.example`)

```
VITE_API_URL=http://localhost:8000/api
```

Frontend berjalan di `http://localhost:5173`. Alamat itulah yang diizinkan CORS di backend (`CORS_ORIGIN`).

---

## 10. Pengujian Frontend

- Uji komponen: `UnggahPdf` (tolak non-PDF/ukuran), `KartuUsulan`, `PanelPersetujuan`, kartu konfirmasi laporan fasilitas.
- Uji alur (Playwright **[ASUMSI]**):
  1. Pengguna memesan **ruangan**: langsung **Disetujui**, tanpa kolom surat.
  2. Pengguna memesan **gedung** dengan surat, pengelola gedung menyetujui, memo muncul.
  3. Pengelola gedung mendaftarkan acara bentrok: pengguna menerima notifikasi dengan alasan persis, memo lama bertanda TIDAK BERLAKU.
  4. Pengguna bernegosiasi, memilih usulan, tombol WA membuka tautan benar, mengajukan ulang **tanpa unggah surat**.
  5. Pengguna A tidak bisa membuka negosiasi atau reservasi pengguna B.
  6. Agent menyerahkan ke manusia: kolom ketik nonaktif dan tombol Chat Admin tampil.
  7. `pengelola_gedung` A tidak melihat reservasi gedung B. Pemilih lokasi acara kampus tidak menampilkan ruangan.
  8. `pengelola_sistem` tidak punya menu atau rute reservasi, surat, memo, dan kasus. Penugasan tidak menawarkan ruangan.
  9. Akun pengelola tidak melihat menu "Ajukan reservasi".
  10. Akun lama tanpa pemetaan hanya melihat `/perlu-pemetaan`.
  11. Laporan fasilitas tidak tersimpan sebelum tombol Kirim ditekan.

---

## 11. Urutan Pengerjaan yang Disarankan

1. Kerangka, rute, auth Bearer, perlindungan peran, dan `/perlu-pemetaan`.
2. Halaman Public (lokasi, jadwal).
3. Panel pengelola sistem (lokasi, penugasan, gedung tanpa pengelola).
4. Formulir ajukan reservasi (ruangan otomatis, gedung dengan unggah PDF).
5. Panel pengelola gedung: pengajuan dan Setuju/Tolak/Ubah.
6. Notifikasi dan `TombolChatAdmin`.
7. Atur Jadwal (kalender, acara kampus, daftar belum diputuskan).
8. Halaman negosiasi (chat agent, kartu usulan, layar langkah berikutnya).
9. Asisten Informasi dan laporan fasilitas.
10. Laporan ringkasan, halaman uji pembanding, dan pemolesan tampilan.

---

## 12. Hal yang Masih Perlu Dikonfirmasi

- Kerangka frontend yang sebenarnya dipakai tim.
- Penyimpanan token (`sessionStorage` adalah **[USULAN]**).
- Batas sorotan "acara dekat" di daftar belum diputuskan (angka 3 hari hanya awalan).
- Identitas visual kampus (warna dan logo).
- Nomor WA Bu Eci (E-02) dan data resmi lokasi (L-01).

> Role Pimpinan sudah **diputuskan dihapus**, dan role `pengelola_ruangan` juga **dihapus** (PRIORITAS butir 17). Keduanya tidak lagi pertanyaan terbuka.
