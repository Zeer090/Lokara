<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Memeriksa bahwa skema PostgreSQL 18 tabel benar-benar terpasang dan aturan
 * pencegah bentrok bekerja. Seluruh data uji dibuat di dalam satu transaksi
 * lalu di-rollback, sehingga database tidak berubah.
 *
 * Pemakaian: php artisan smartspace:verifikasi
 */
class VerifikasiSkema extends Command
{
    protected $signature = 'smartspace:verifikasi';

    protected $description = 'Verifikasi 18 tabel, enum, exclusion constraint, trigger, dan cascade skema SMARTSPACE';

    private int $gagal = 0;

    public function handle(): int
    {
        $this->info('== Verifikasi skema database SMARTSPACE AI (18 Tabel) ==');

        $this->periksaTabel();
        $this->periksaEnum();
        $this->periksaEkstensi();
        $this->periksaConstraint();
        $this->periksaPerilaku();

        $this->newLine();
        if ($this->gagal > 0) {
            $this->error("SELESAI DENGAN {$this->gagal} KEGAGALAN.");

            return self::FAILURE;
        }

        $this->info('SEMUA PEMERIKSAAN LULUS.');

        return self::SUCCESS;
    }

    private function lulus(string $pesan): void
    {
        $this->line("  [OK]    {$pesan}");
    }

    private function gagal(string $pesan): void
    {
        $this->gagal++;
        $this->line("  [GAGAL] {$pesan}");
    }

    private function periksaTabel(): void
    {
        $this->line('Tabel (18 tabel):');

        $wajib = [
            'pengguna', 'lokasi', 'surat', 'reservasi', 'acara_kampus',
            'slot_terkunci', 'memo', 'kasus_penggusuran', 'pesan_negosiasi',
            'usulan_agent', 'log_agent', 'notifikasi', 'pengaturan',
            'percakapan_info', 'pengelola_lokasi', 'memo_riwayat',
            'log_akses_dokumen', 'laporan_fasilitas',
        ];

        $ada = DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->pluck('table_name')
            ->all();

        foreach ($wajib as $tabel) {
            in_array($tabel, $ada, true)
                ? $this->lulus("tabel {$tabel}")
                : $this->gagal("tabel {$tabel} tidak ditemukan");
        }
    }

    private function periksaEnum(): void
    {
        $this->line('Tipe enum:');

        $wajib = [
            'peran_pengguna', 'jenis_lokasi', 'status_reservasi', 'status_memo',
            'status_kasus', 'pengirim_pesan', 'status_usulan',
            'jenis_penggunaan', 'alur_persetujuan', 'status_laporan',
        ];

        $ada = DB::table('pg_type')->where('typtype', 'e')->pluck('typname')->all();

        foreach ($wajib as $tipe) {
            in_array($tipe, $ada, true)
                ? $this->lulus("tipe {$tipe}")
                : $this->gagal("tipe {$tipe} tidak ditemukan");
        }
    }

    private function periksaEkstensi(): void
    {
        $this->line('Ekstensi:');

        $ada = DB::table('pg_extension')->pluck('extname')->all();

        foreach (['btree_gist', 'pgcrypto'] as $ekstensi) {
            in_array($ekstensi, $ada, true)
                ? $this->lulus("ekstensi {$ekstensi}")
                : $this->gagal("ekstensi {$ekstensi} tidak ditemukan");
        }
    }

    private function periksaConstraint(): void
    {
        $this->line('Constraint, indeks, dan trigger:');

        $exclusion = DB::table('pg_constraint')
            ->where('conname', 'tidak_boleh_bentrok')
            ->where('contype', 'x')
            ->exists();

        $exclusion
            ? $this->lulus('exclusion constraint tidak_boleh_bentrok')
            : $this->gagal('exclusion constraint tidak_boleh_bentrok tidak ditemukan');

        foreach (['uq_slot_reservasi', 'uq_slot_acara', 'uq_memo_aktif'] as $indeks) {
            DB::table('pg_indexes')->where('indexname', $indeks)->exists()
                ? $this->lulus("indeks unik parsial {$indeks}")
                : $this->gagal("indeks {$indeks} tidak ditemukan");
        }

        foreach (['trg_log_agent_hanya_tambah', 'trg_lad_hanya_tambah', 'trg_memo_riwayat_hanya_tambah', 'trg_reservasi_validasi', 'ctrg_status_slot'] as $trg) {
            DB::table('pg_trigger')->where('tgname', $trg)->exists()
                ? $this->lulus("trigger {$trg}")
                : $this->gagal("trigger {$trg} tidak ditemukan");
        }
    }

    private function periksaPerilaku(): void
    {
        $this->line('Perilaku (di dalam transaksi, otomatis di-rollback):');

        DB::beginTransaction();

        try {
            $pengguna = DB::selectOne(
                "INSERT INTO pengguna (nama, email, password_hash, peran)
                 VALUES ('Verifikasi User', 'user_verif@contoh.test', 'x', 'pengguna')
                 RETURNING id"
            )->id;

            $pengelola = DB::selectOne(
                "INSERT INTO pengguna (nama, email, password_hash, peran)
                 VALUES ('Verifikasi Pengelola', 'pengelola_verif@contoh.test', 'x', 'pengelola_gedung')
                 RETURNING id"
            )->id;

            $lokasi = DB::selectOne(
                "INSERT INTO lokasi (nama, jenis, kapasitas)
                 VALUES ('Ruang Verifikasi', 'ruangan', 10)
                 RETURNING id"
            )->id;

            $reservasi = DB::selectOne(
                "INSERT INTO reservasi (kode, pengguna_id, lokasi_id, keperluan, mulai, selesai, status, jenis_penggunaan, alur_persetujuan)
                 VALUES ('VERIF-1', ?, ?, 'Uji', '2026-11-06 09:00+07', '2026-11-06 11:00+07', 'disetujui', 'rutin', 'otomatis')
                 RETURNING id",
                [$pengguna, $lokasi]
            )->id;

            DB::insert(
                "INSERT INTO slot_terkunci (lokasi_id, rentang, sumber, reservasi_id)
                 VALUES (?, tstzrange('2026-11-06 09:00+07', '2026-11-06 11:00+07', '[)'), 'reservasi', ?)",
                [$lokasi, $reservasi]
            );

            $this->lulus('slot pertama berhasil dikunci');

            $ditolak = false;

            try {
                DB::transaction(function () use ($pengguna, $lokasi) {
                    $kedua = DB::selectOne(
                        "INSERT INTO reservasi (kode, pengguna_id, lokasi_id, keperluan, mulai, selesai, status, jenis_penggunaan, alur_persetujuan)
                         VALUES ('VERIF-2', ?, ?, 'Uji bentrok', '2026-11-06 10:00+07', '2026-11-06 12:00+07', 'disetujui', 'rutin', 'otomatis')
                         RETURNING id",
                        [$pengguna, $lokasi]
                    )->id;

                    DB::insert(
                        "INSERT INTO slot_terkunci (lokasi_id, rentang, sumber, reservasi_id)
                         VALUES (?, tstzrange('2026-11-06 10:00+07', '2026-11-06 12:00+07', '[)'), 'reservasi', ?)",
                        [$lokasi, $kedua]
                    );
                });
            } catch (QueryException $e) {
                $ditolak = ($e->errorInfo[0] ?? $e->getCode()) === '23P01';
            }

            $ditolak
                ? $this->lulus('jadwal tumpang tindih ditolak database (SQLSTATE 23P01)')
                : $this->gagal('jadwal tumpang tindih TIDAK ditolak database');

            $kasus = DB::selectOne(
                "INSERT INTO kasus_penggusuran (reservasi_id, alasan, dibuat_oleh)
                 VALUES (?, 'Uji verifikasi', ?)
                 RETURNING id",
                [$reservasi, $pengelola]
            )->id;

            $log = DB::selectOne(
                "INSERT INTO log_agent (kasus_id, langkah, jenis, catatan)
                 VALUES (?, 1, 'pikiran', 'Uji')
                 RETURNING id",
                [$kasus]
            )->id;

            $logTerkunci = false;

            try {
                DB::transaction(function () use ($log) {
                    DB::update("UPDATE log_agent SET catatan = 'diubah' WHERE id = ?", [$log]);
                });
            } catch (QueryException) {
                $logTerkunci = true;
            }

            $logTerkunci
                ? $this->lulus('log_agent menolak UPDATE')
                : $this->gagal('log_agent masih bisa diubah');

            // Reservasi terpisah agar tidak terhalang FK kasus_penggusuran.
            $reservasiHapus = DB::selectOne(
                "INSERT INTO reservasi (kode, pengguna_id, lokasi_id, keperluan, mulai, selesai, status, jenis_penggunaan, alur_persetujuan)
                 VALUES ('VERIF-3', ?, ?, 'Uji cascade', '2026-11-07 09:00+07', '2026-11-07 10:00+07', 'disetujui', 'rutin', 'otomatis')
                 RETURNING id",
                [$pengguna, $lokasi]
            )->id;

            DB::insert(
                "INSERT INTO slot_terkunci (lokasi_id, rentang, sumber, reservasi_id)
                 VALUES (?, tstzrange('2026-11-07 09:00+07', '2026-11-07 10:00+07', '[)'), 'reservasi', ?)",
                [$lokasi, $reservasiHapus]
            );

            DB::delete('DELETE FROM reservasi WHERE id = ?', [$reservasiHapus]);

            $sisaSlot = DB::table('slot_terkunci')->where('reservasi_id', $reservasiHapus)->count();

            $sisaSlot === 0
                ? $this->lulus('slot ikut terhapus saat reservasi dihapus (ON DELETE CASCADE)')
                : $this->gagal('slot tertinggal setelah reservasi dihapus');
        } finally {
            DB::rollBack();
        }
    }
}
