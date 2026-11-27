<?php

namespace App\Services;

use App\Models\AcaraKampus;
use App\Models\KasusPenggusuran;
use App\Models\Notifikasi;
use App\Models\Pengaturan;
use App\Models\Pengguna;
use App\Models\PengelolaLokasi;
use App\Models\Reservasi;
use App\Models\SlotTerkunci;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EvictionService
{
    public function __construct(protected DocumentService $documentService)
    {
    }

    /**
     * Pratinjau dampak penggusuran sebelum acara kampus didaftarkan.
     */
    public function pratinjauAcara(Pengguna $manager, array $data): array
    {
        $this->validasiPengelolaGedung($manager, $data['lokasi_id']);

        $mulai = Carbon::parse($data['mulai'])->toIso8601String();
        $selesai = Carbon::parse($data['selesai'])->toIso8601String();

        // Cari reservasi berstatus disetujui yang beririsan
        $reservasiTerdampak = Reservasi::where('lokasi_id', $data['lokasi_id'])
            ->where('status', 'disetujui')
            ->whereExists(function ($query) use ($data, $mulai, $selesai) {
                $query->select(DB::raw(1))
                    ->from('slot_terkunci')
                    ->whereColumn('slot_terkunci.reservasi_id', 'reservasi.id')
                    ->whereRaw("slot_terkunci.rentang && tstzrange(?, ?, '[)')", [$mulai, $selesai]);
            })
            ->with('pengguna')
            ->get();

        return [
            'jumlah_terdampak' => $reservasiTerdampak->count(),
            'reservasi' => $reservasiTerdampak->map(function ($r) {
                return [
                    'id'         => $r->id,
                    'kode'       => $r->kode,
                    'pemohon'    => $r->pengguna->nama,
                    'organisasi' => $r->pengguna->organisasi,
                    'mulai'      => $r->mulai->toIso8601String(),
                    'selesai'    => $r->selesai->toIso8601String(),
                    'keperluan'  => $r->keperluan,
                ];
            }),
        ];
    }

    /**
     * Daftarkan acara kampus secara transaksional:
     * - Menggusur semua reservasi disetujui yang bentrok
     * - Mencabut memo dan membuka kasus_penggusuran
     * - Kunci slot untuk acara kampus
     * - Rollback semuanya jika bentrok dengan acara kampus lain
     */
    public function daftarkanAcara(Pengguna $manager, array $data): array
    {
        $this->validasiPengelolaGedung($manager, $data['lokasi_id']);

        $alasan = trim($data['alasan'] ?? '');
        if (empty($alasan)) {
            throw new HttpException(422, 'Alasan acara kampus dan penggusuran wajib diisi.');
        }

        $mulai = Carbon::parse($data['mulai']);
        $selesai = Carbon::parse($data['selesai']);

        if ($selesai->lte($mulai)) {
            throw new HttpException(422, 'Waktu selesai harus setelah waktu mulai.');
        }

        return DB::transaction(function () use ($manager, $data, $mulai, $selesai, $alasan) {
            $mulaiStr = $mulai->toIso8601String();
            $selesaiStr = $selesai->toIso8601String();

            // 1. Simpan baris acara kampus
            $acara = AcaraKampus::create([
                'nama'             => $data['nama'],
                'lokasi_id'        => $data['lokasi_id'],
                'mulai'            => $mulai,
                'selesai'          => $selesai,
                'alasan'           => $alasan,
                'didaftarkan_oleh' => $manager->id,
            ]);

            // 2. Temukan semua reservasi disetujui yang beririsan
            $reservasiTergusur = Reservasi::where('lokasi_id', $data['lokasi_id'])
                ->where('status', 'disetujui')
                ->whereExists(function ($query) use ($mulaiStr, $selesaiStr) {
                    $query->select(DB::raw(1))
                        ->from('slot_terkunci')
                        ->whereColumn('slot_terkunci.reservasi_id', 'reservasi.id')
                        ->whereRaw("slot_terkunci.rentang && tstzrange(?, ?, '[)')", [$mulaiStr, $selesaiStr]);
                })
                ->lockForUpdate()
                ->get();

            $nomorWa = Pengaturan::where('kunci', 'wa_admin_nomor')->value('nilai') ?? '62xxxxxxxxxxx';

            foreach ($reservasiTergusur as $r) {
                // Hapus slot reservasi
                SlotTerkunci::where('reservasi_id', $r->id)->delete();

                // Cabut memo resmi jika ada
                $memoAktif = $r->memoAktif;
                if ($memoAktif) {
                    $this->documentService->stampMemoTidakBerlaku(
                        $memoAktif,
                        'Jadwal dialihkan untuk acara kampus: ' . $acara->nama . ' (' . $alasan . ')',
                        $manager->id
                    );
                }

                // Ubah status reservasi menjadi 'tergusur'
                $r->update(['status' => 'tergusur']);

                // Buat kasus penggusuran dengan alasan apa adanya
                $kasus = KasusPenggusuran::create([
                    'reservasi_id' => $r->id,
                    'acara_id'     => $acara->id,
                    'alasan'       => $alasan,
                    'alasan_penggusuran' => $alasan,
                    'dibuat_oleh'  => $manager->id,
                    'status'       => 'terbuka',
                ]);

                // Kirim notifikasi penggusuran kepada pengguna
                Notifikasi::create([
                    'pengguna_id' => $r->pengguna_id,
                    'judul'       => 'Pemberitahuan: Jadwal Reservasi Digusur Acara Kampus',
                    'isi'         => "Reservasi {$r->kode} dialihkan karena acara kampus '{$acara->nama}'. Alasan: \"{$alasan}\". Silakan buka tautan untuk mencari jadwal pengganti.",
                    'tautan'      => "/app/negosiasi/{$kasus->id}",
                ]);
            }

            // 3. Kunci slot untuk acara kampus. Jika bentrok dengan acara kampus lain, rollback semuanya!
            try {
                DB::statement(
                    "INSERT INTO slot_terkunci (lokasi_id, rentang, sumber, acara_id)
                     VALUES (?, tstzrange(?, ?, '[)'), 'acara_kampus', ?)",
                    [$data['lokasi_id'], $mulaiStr, $selesaiStr, $acara->id]
                );
            } catch (QueryException $e) {
                $isConflict = ($e->errorInfo[0] ?? $e->getCode()) === '23P01';
                if ($isConflict) {
                    throw new HttpException(409, 'Pendaftaran acara gagal: jadwal bentrok dengan acara kampus lain.');
                }
                throw $e;
            }

            return [
                'acara'             => $acara,
                'jumlah_tergusur'   => $reservasiTergusur->count(),
                'pesan'             => "Acara kampus berhasil didaftarkan. {$reservasiTergusur->count()} reservasi telah digusur secara transaksional.",
            ];
        });
    }

    /**
     * Batalkan manual satu reservasi oleh pengelola gedung (Alur D: batalkan reservasi).
     */
    public function gusurReservasiManual(Pengguna $manager, string $reservasiId, string $alasan): KasusPenggusuran
    {
        $alasan = trim($alasan);
        if (empty($alasan)) {
            throw new HttpException(422, 'Alasan pembatalan/penggusuran wajib diisi.');
        }

        $reservasi = Reservasi::with(['lokasi', 'memoAktif', 'slotTerkunci'])->find($reservasiId);

        if (! $reservasi) {
            throw new HttpException(404, 'Reservasi tidak ditemukan.');
        }

        $this->validasiPengelolaGedung($manager, $reservasi->lokasi_id);

        if ($reservasi->status !== 'disetujui') {
            throw new HttpException(400, 'Hanya reservasi yang sudah disetujui yang dapat digusur.');
        }

        return DB::transaction(function () use ($manager, $reservasi, $alasan) {
            // Hapus slot
            if ($reservasi->slotTerkunci) {
                $reservasi->slotTerkunci->delete();
            }

            // Cabut memo
            if ($reservasi->memoAktif) {
                $this->documentService->stampMemoTidakBerlaku(
                    $reservasi->memoAktif,
                    'Dibatalkan oleh pengelola: ' . $alasan,
                    $manager->id
                );
            }

            $reservasi->update(['status' => 'tergusur']);

            $kasus = KasusPenggusuran::create([
                'reservasi_id' => $reservasi->id,
                'acara_id'     => null,
                'alasan'       => $alasan,
                'alasan_penggusuran' => $alasan,
                'dibuat_oleh'  => $manager->id,
                'status'       => 'terbuka',
            ]);

            Notifikasi::create([
                'pengguna_id' => $reservasi->pengguna_id,
                'judul'       => 'Jadwal Reservasi Dibatalkan oleh Pengelola',
                'isi'         => "Reservasi {$reservasi->kode} dibatalkan oleh pengelola. Alasan: \"{$alasan}\". Silakan cari jadwal pengganti.",
                'tautan'      => "/app/negosiasi/{$kasus->id}",
            ]);

            return $kasus;
        });
    }

    /**
     * Validasi bahwa pemanggil adalah pengelola_gedung yang terdaftar pada gedung.
     */
    public function validasiPengelolaGedung(Pengguna $user, string $lokasiId): void
    {
        if ($user->peran !== 'pengelola_gedung') {
            throw new HttpException(403, 'Hanya pengelola_gedung yang dapat mendaftarkan acara kampus atau menggusur reservasi.');
        }

        $terdaftar = PengelolaLokasi::where('pengguna_id', $user->id)
            ->where('lokasi_id', $lokasiId)
            ->exists();

        if (! $terdaftar) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki penugasan untuk gedung ini.');
        }
    }
}

