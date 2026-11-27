<?php

use App\Models\PercakapanInfo;
use App\Models\Reservasi;
use App\Models\SlotTerkunci;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Membersihkan percakapan_info > 30 hari, menutup pengajuan menunggu yang kadaluarsa,
 * dan membersihkan slot_terkunci yang sudah lewat > 90 hari.
 */
Artisan::command('smartspace:bersihkan-data', function () {
    $sekarang = now('Asia/Jakarta');

    // 1. Hapus percakapan_info yang lebih dari 30 hari (aturan retensi privasi)
    $hapusChat = PercakapanInfo::where('dibuat_pada', '<', $sekarang->copy()->subDays(30))->delete();

    // 2. Tutup otomatis pengajuan reservasi 'menunggu' yang jadwal selesainya sudah lewat
    $tutupMenunggu = Reservasi::where('status', 'menunggu')
        ->where('selesai', '<', $sekarang)
        ->update([
            'status'        => 'ditolak',
            'catatan_admin' => 'Ditutup otomatis oleh sistem karena waktu kegiatan telah terlewat.',
        ]);

    // 3. Bersihkan slot_terkunci yang sudah lewat > 90 hari agar index tetap ringan
    $batasSlot = $sekarang->copy()->subDays(90)->toIso8601String();
    $hapusSlot = SlotTerkunci::whereRaw('upper(rentang) < ?::timestamptz', [$batasSlot])->delete();

    $this->info("Pembersihan selesai: {$hapusChat} percakapan (>30h) dihapus, {$tutupMenunggu} reservasi kadaluarsa ditutup, {$hapusSlot} slot lama (>90h) dibersihkan.");
})->purpose('Membersihkan percakapan publik > 30 hari serta menutup reservasi dan slot kadaluarsa.');

// Jadwalkan otomatis setiap hari pukul 01:00 WIB
Schedule::command('smartspace:bersihkan-data')
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta');
