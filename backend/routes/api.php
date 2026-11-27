<?php

use App\Http\Controllers\AkademikController;
use App\Http\Controllers\AsistenInfoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatAdminController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\KasusController;
use App\Http\Controllers\LaporanFasilitasController;
use App\Http\Controllers\LokasiController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PengelolaGedungController;
use App\Http\Controllers\PengelolaSistemController;
use App\Http\Controllers\ReservasiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SMARTSPACE AI — API Routes
|--------------------------------------------------------------------------
|
| Semua rute otomatis berawalan /api dari bootstrap/app.php.
| Format error seragam: { "galat": "..." }.
|
*/

// ── 1. Auth ──────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('daftar', [AuthController::class, 'daftar'])
        ->middleware('throttle:auth');

    Route::post('masuk', [AuthController::class, 'masuk'])
        ->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('saya',   [AuthController::class, 'saya']);
        Route::post('keluar', [AuthController::class, 'keluar']);
    });
});

// ── 2. Lokasi & Jadwal Publik (Public) ──────────────────────────────────────
Route::get('lokasi',                 [LokasiController::class, 'index']);
Route::get('lokasi/{id}',            [LokasiController::class, 'show']);
Route::get('lokasi/{id}/jadwal',     [LokasiController::class, 'jadwal']);
Route::get('jadwal/kalender',        [JadwalController::class, 'kalenderPublik']);

// ── 3. Asisten Info & Laporan Fasilitas (Public / Terbuka) ──────────────────
Route::post('asisten-info/pesan',    [AsistenInfoController::class, 'pesan'])
    ->middleware('throttle:api');

Route::post('laporan-fasilitas',     [LaporanFasilitasController::class, 'simpan'])
    ->middleware('throttle:api');

// ── 4. Dokumen (Surat & Memo) ───────────────────────────────────────────────
// Otorisasi dan audit logging diperiksa ketat di DokumenController.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('surat/{id}/unduh', [DokumenController::class, 'unduhSurat']);
    Route::get('memo/{id}/unduh',  [DokumenController::class, 'unduhMemo']);
});

// ── 5. Notifikasi (Semua pengguna login) ─────────────────────────────────────
Route::middleware('auth:sanctum')->prefix('notifikasi')->group(function () {
    Route::get('/',             [NotifikasiController::class, 'index']);
    Route::post('baca-semua',   [NotifikasiController::class, 'tandaiSemuaDibaca']);
    Route::post('{id}/baca',    [NotifikasiController::class, 'tandaiDibaca']);
});

// ── 6. Kontak WhatsApp Admin (Bu Eci) ────────────────────────────────────────
Route::middleware('auth:sanctum')->get('chat-admin/tautan', [ChatAdminController::class, 'tautan']);

// ── 7. Reservasi & Kasus Penggusuran (Pengguna) ──────────────────────────────
Route::middleware(['auth:sanctum', 'peran:pengguna'])->group(function () {
    // Reservasi
    Route::post('reservasi',            [ReservasiController::class, 'simpan']);
    Route::get('reservasi/saya',        [ReservasiController::class, 'saya']);
    Route::get('reservasi/{id}',        [ReservasiController::class, 'show']);
    Route::post('reservasi/{id}/batal', [ReservasiController::class, 'batal']);

    // Kasus Penggusuran & Negosiasi
    Route::get('kasus/saya',            [KasusController::class, 'saya']);
    Route::get('kasus/{id}',            [KasusController::class, 'show']);
    Route::post('kasus/{id}/pesan',      [KasusController::class, 'pesan']);
    Route::post('kasus/{id}/pilih-usulan', [KasusController::class, 'pilihUsulan']);
    Route::post('kasus/{id}/ajukan-ulang', [KasusController::class, 'ajukanUlang']);
    Route::get('kasus/{id}/tautan-wa',   [KasusController::class, 'tautanWa']);
});

// ── 8. Pengelola Gedung (pengelola_gedung) ──────────────────────────────────
Route::middleware(['auth:sanctum', 'peran:pengelola_gedung'])->prefix('pengelola')->group(function () {
    Route::get('lokasi',                     [PengelolaGedungController::class, 'lokasiDikelola']);
    Route::get('reservasi',                  [PengelolaGedungController::class, 'daftarReservasi']);
    Route::get('reservasi/{id}',             [PengelolaGedungController::class, 'detailReservasi']);
    Route::post('reservasi/{id}/setuju',     [PengelolaGedungController::class, 'setujuiReservasi']);
    Route::post('reservasi/{id}/tolak',      [PengelolaGedungController::class, 'tolakReservasi']);
    Route::post('reservasi/{id}/ubah',       [PengelolaGedungController::class, 'ubahReservasi']);
    Route::post('reservasi/{id}/gusur',      [PengelolaGedungController::class, 'gusurReservasi']);

    Route::get('jadwal/kalender',            [PengelolaGedungController::class, 'kalender']);

    // Acara Kampus & Penggusuran
    Route::post('acara-kampus/pratinjau',    [PengelolaGedungController::class, 'pratinjauAcaraKampus']);
    Route::post('acara-kampus',              [PengelolaGedungController::class, 'buatAcaraKampus']);

    // Kasus Penggusuran
    Route::get('kasus',                      [PengelolaGedungController::class, 'daftarKasus']);
    Route::get('kasus/{id}',                 [PengelolaGedungController::class, 'detailKasus']);

    // Laporan Fasilitas Gedungnya
    Route::get('laporan-fasilitas',          [LaporanFasilitasController::class, 'indexPengelola']);
    Route::patch('laporan-fasilitas/{id}',   [LaporanFasilitasController::class, 'updatePengelola']);
});

// ── 9. Pengelola Sistem (pengelola_sistem) ──────────────────────────────────
Route::middleware(['auth:sanctum', 'peran:pengelola_sistem'])->prefix('sistem')->group(function () {
    // Pengguna
    Route::get('pengguna',                   [PengelolaSistemController::class, 'indexPengguna']);
    Route::post('pengguna/{id}/peran',       [PengelolaSistemController::class, 'ubahPeran']);
    Route::patch('pengguna/{id}/aktif',      [PengelolaSistemController::class, 'ubahStatusAktif']);

    // Lokasi
    Route::get('lokasi',                     [PengelolaSistemController::class, 'indexLokasi']);
    Route::post('lokasi',                    [PengelolaSistemController::class, 'simpanLokasi']);
    Route::put('lokasi/{id}',                [PengelolaSistemController::class, 'perbaruiLokasi']);
    Route::patch('lokasi/{id}',              [PengelolaSistemController::class, 'perbaruiLokasi']);
    Route::patch('lokasi/{id}/status',       [PengelolaSistemController::class, 'ubahStatusLokasi']);

    // Penugasan Pengelola ke Gedung
    Route::get('penugasan',                  [PengelolaSistemController::class, 'indexPenugasan']);
    Route::post('penugasan',                 [PengelolaSistemController::class, 'simpanPenugasan']);
    Route::delete('penugasan/{id}',          [PengelolaSistemController::class, 'hapusPenugasan']);

    // Gedung Tanpa Pengelola (Zero private details)
    Route::get('gedung-tanpa-pengelola',     [PengelolaSistemController::class, 'gedungTanpaPengelola']);

    // Pengaturan Sistem
    Route::get('pengaturan',                 [PengelolaSistemController::class, 'indexPengaturan']);
    Route::put('pengaturan',                 [PengelolaSistemController::class, 'perbaruiPengaturan']);

    // Log & Audit (Read-only)
    Route::get('log/agent',                  [PengelolaSistemController::class, 'logAgent']);
    Route::get('log/akses-dokumen',          [PengelolaSistemController::class, 'logAksesDokumen']);

    // Laporan Fasilitas
    Route::get('laporan-fasilitas',          [PengelolaSistemController::class, 'indexLaporanFasilitas']);
    Route::patch('laporan-fasilitas/{id}',   [PengelolaSistemController::class, 'ubahStatusLaporanFasilitas']);
});

// ── 10. Laporan Ringkasan (Agregat) ──────────────────────────────────────────
Route::middleware(['auth:sanctum', 'peran:pengelola_gedung,pengelola_sistem'])
    ->get('laporan/ringkasan', [PengelolaSistemController::class, 'ringkasan']);

// ── 11. Akademik ─────────────────────────────────────────────────────────────
Route::prefix('akademik')->middleware(['auth:sanctum', 'peran:akademik'])->group(function () {
    Route::post('upload',                [AkademikController::class, 'upload']);
    Route::post('scan',                  [AkademikController::class, 'scan']);
    Route::post('aktivasi-scan',         [AkademikController::class, 'aktivasiScan']);
    Route::get('upload',                 [AkademikController::class, 'listUploads']);
    Route::get('periods',                [AkademikController::class, 'listPeriods']);
    Route::get('schedule/{periodId}',    [AkademikController::class, 'activeSchedule']);
    Route::get('upload/{uploadId}/errors',[AkademikController::class, 'validationErrors']);
});

