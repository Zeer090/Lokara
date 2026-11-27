<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicSchedule;
use App\Models\Lokasi;
use App\Models\Notifikasi;
use App\Models\Pengguna;
use App\Models\PengelolaLokasi;
use App\Models\Reservasi;
use App\Models\SlotTerkunci;
use App\Models\Surat;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReservationService
{
    public function __construct(protected DocumentService $documentService)
    {
    }

    /**
     * Ajukan reservasi baru:
     * - Ruangan: otomatis disetujui, tanpa surat, langsung kunci slot (Alur A1).
     * - Gedung: manual, wajib surat PDF valid, status menunggu, tanpa kunci slot (Alur A2).
     */
    public function ajukanReservasi(Pengguna $user, array $data, ?UploadedFile $fileSurat = null): Reservasi
    {
        // 1. Otorisasi peran pemesan
        if (in_array($user->peran, ['pengelola_gedung', 'pengelola_sistem'], true)) {
            throw new HttpException(403, 'Akun pengelola tidak dapat mengajukan reservasi. Gunakan akun pengguna terpisah.');
        }

        // 2. Validasi waktu
        $mulai = Carbon::parse($data['mulai']);
        $selesai = Carbon::parse($data['selesai']);

        if ($selesai->lte($mulai)) {
            throw new HttpException(422, 'Waktu selesai harus setelah waktu mulai.');
        }

        if ($mulai->isPast()) {
            throw new HttpException(422, 'Waktu pengajuan tidak boleh di masa lalu.');
        }

        // 3. Validasi lokasi
        $lokasi = Lokasi::find($data['lokasi_id']);
        if (! $lokasi || ! $lokasi->aktif) {
            throw new HttpException(404, 'Lokasi tidak ditemukan atau sedang tidak aktif.');
        }

        // 4. Validasi kapasitas
        $jumlahPeserta = isset($data['jumlah_peserta']) && $data['jumlah_peserta'] !== null
            ? (int) $data['jumlah_peserta']
            : null;

        if ($jumlahPeserta !== null && $jumlahPeserta > $lokasi->kapasitas) {
            throw new HttpException(422, "Jumlah peserta ({$jumlahPeserta}) melebihi kapasitas lokasi ({$lokasi->kapasitas}).");
        }

        // 5. Alur berdasarkan jenis lokasi
        if ($lokasi->jenis === 'ruangan') {
            return $this->prosesReservasiRuangan($user, $lokasi, $data, $mulai, $selesai, $jumlahPeserta, $fileSurat);
        } else {
            return $this->prosesReservasiGedung($user, $lokasi, $data, $mulai, $selesai, $jumlahPeserta, $fileSurat);
        }
    }

    /**
     * Alur 1: Ruangan otomatis tanpa surat.
     */
    protected function prosesReservasiRuangan(
        Pengguna $user,
        Lokasi $lokasi,
        array $data,
        Carbon $mulai,
        Carbon $selesai,
        ?int $jumlahPeserta,
        ?UploadedFile $fileSurat
    ): Reservasi {
        // NON-NEGOTIABLE: Ruangan yang menyertakan surat wajib ditolak HTTP 422!
        if ($fileSurat !== null) {
            throw new HttpException(422, 'Reservasi ruangan tidak memerlukan surat.');
        }

        return DB::transaction(function () use ($user, $lokasi, $data, $mulai, $selesai, $jumlahPeserta) {
            $kode = $this->generateKodeReservasi();

            // 1. Simpan baris reservasi berstatus 'disetujui'
            $reservasi = Reservasi::create([
                'kode'              => $kode,
                'pengguna_id'       => $user->id,
                'lokasi_id'         => $lokasi->id,
                'surat_id'          => null,
                'keperluan'         => $data['keperluan'],
                'kebutuhan'         => $data['kebutuhan'] ?? [],
                'mulai'             => $mulai,
                'selesai'           => $selesai,
                'status'            => 'disetujui',
                'jenis_penggunaan'  => 'rutin',
                'alur_persetujuan'  => 'otomatis',
                'jumlah_peserta'    => $jumlahPeserta,
            ]);

            // 2. Kunci slot secara atomik di slot_terkunci dengan GiST exclusion constraint
            // Check conflict with academic schedules before locking slot
            $this->checkAcademicScheduleConflict($lokasi->id, $mulai, $selesai);

            try {
                $mulaiStr = $mulai->toIso8601String();
                $selesaiStr = $selesai->toIso8601String();

                DB::statement(
                    "INSERT INTO slot_terkunci (lokasi_id, rentang, sumber, reservasi_id)
                     VALUES (?, tstzrange(?, ?, '[)'), 'reservasi', ?)",
                    [$lokasi->id, $mulaiStr, $selesaiStr, $reservasi->id]
                );
            } catch (QueryException $e) {
                $isConflict = ($e->errorInfo[0] ?? $e->getCode()) === '23P01';
                if ($isConflict) {
                    throw new HttpException(409, 'Jadwal yang dipilih bentrok dengan jadwal ruangan yang sudah terisi.');
                }
                throw $e;
            }

            // 3. Notifikasi persetujuan otomatis untuk pengguna
            Notifikasi::create([
                'pengguna_id' => $user->id,
                'judul'       => 'Reservasi Ruangan Berhasil Disetujui',
                'isi'         => "Pengajuan reservasi ruangan {$lokasi->nama} ({$kode}) disetujui otomatis oleh sistem.",
                'tautan'      => "/app/reservasi/{$reservasi->id}",
            ]);

            return $reservasi->load(['lokasi', 'slotTerkunci']);
        });
    }

    /**
     * Alur 2: Gedung manual dengan surat wajib.
     */
    protected function prosesReservasiGedung(
        Pengguna $user,
        Lokasi $lokasi,
        array $data,
        Carbon $mulai,
        Carbon $selesai,
        ?int $jumlahPeserta,
        ?UploadedFile $fileSurat
    ): Reservasi {
        // NON-NEGOTIABLE: Gedung wajib menyertakan surat PDF
        if ($fileSurat === null) {
            throw new HttpException(422, 'Reservasi gedung wajib mengunggah berkas surat permohonan format PDF.');
        }

        // Validasi berkas PDF
        $this->documentService->validatePdfUpload($fileSurat);

        return DB::transaction(function () use ($user, $lokasi, $data, $mulai, $selesai, $jumlahPeserta, $fileSurat) {
            // 1. Simpan berkas surat di storage privat
            $namaAsli = $fileSurat->getClientOriginalName();
            $ukuranByte = $fileSurat->getSize();
            $filename = 'surat_' . Str::uuid() . '.pdf';
            $pathBerkas = 'surat/' . date('Y') . '/' . $filename;

            $fileSurat->storeAs('private/surat/' . date('Y'), $filename, 'local');

            $surat = Surat::create([
                'pengguna_id' => $user->id,
                'nama_asli'   => $namaAsli,
                'path_berkas' => $pathBerkas,
                'ukuran_byte' => $ukuranByte,
            ]);

            // 2. Simpan reservasi dengan status 'menunggu'
            $kode = $this->generateKodeReservasi();

            $reservasi = Reservasi::create([
                'kode'              => $kode,
                'pengguna_id'       => $user->id,
                'lokasi_id'         => $lokasi->id,
                'surat_id'          => $surat->id,
                'keperluan'         => $data['keperluan'],
                'kebutuhan'         => $data['kebutuhan'] ?? [],
                'mulai'             => $mulai,
                'selesai'           => $selesai,
                'status'            => 'menunggu',
                'jenis_penggunaan'  => 'event_kegiatan',
                'alur_persetujuan'  => 'manual',
                'jumlah_peserta'    => $jumlahPeserta,
            ]);

            // 3. Notifikasi kepada pengelola gedung yang bertugas
            $pengelolaList = PengelolaLokasi::where('lokasi_id', $lokasi->id)->pluck('pengguna_id');

            if ($pengelolaList->isNotEmpty()) {
                foreach ($pengelolaList as $pengelolaId) {
                    Notifikasi::create([
                        'pengguna_id' => $pengelolaId,
                        'judul'       => 'Pengajuan Reservasi Gedung Baru',
                        'isi'         => "Ada pengajuan baru untuk {$lokasi->nama} ({$kode}) oleh {$user->nama}.",
                        'tautan'      => "/pengelola/pengajuan/{$reservasi->id}",
                    ]);
                }
            } else {
                // Jika gedung belum memiliki pengelola, beri notifikasi ke semua pengelola sistem
                $sistemList = Pengguna::where('peran', 'pengelola_sistem')->pluck('id');
                foreach ($sistemList as $sistemId) {
                    Notifikasi::create([
                        'pengguna_id' => $sistemId,
                        'judul'       => 'Reservasi Menunggu pada Gedung Tanpa Pengelola',
                        'isi'         => "Pengajuan {$kode} berada di {$lokasi->nama} yang belum memiliki pengelola gedung.",
                        'tautan'      => '/sistem/gedung-tanpa-pengelola',
                    ]);
                }
            }

            return $reservasi->load(['lokasi', 'surat']);
        });
    }

    /**
     * Batalkan reservasi oleh pemiliknya sebelum jadwal dimulai.
     */
    public function batalkanReservasi(Pengguna $user, string $reservasiId): Reservasi
    {
        $reservasi = Reservasi::with(['slotTerkunci', 'memoAktif'])->find($reservasiId);

        if (! $reservasi) {
            throw new HttpException(404, 'Reservasi tidak ditemukan.');
        }

        if ($reservasi->pengguna_id !== $user->id) {
            throw new HttpException(403, 'Anda tidak memiliki hak untuk membatalkan reservasi ini.');
        }

        if (in_array($reservasi->status, ['dibatalkan', 'ditolak', 'selesai', 'tergusur'], true)) {
            throw new HttpException(400, "Reservasi berstatus {$reservasi->status} tidak dapat dibatalkan.");
        }

        if (Carbon::parse($reservasi->mulai)->isPast()) {
            throw new HttpException(400, 'Reservasi yang sudah atau sedang berlangsung tidak dapat dibatalkan.');
        }

        return DB::transaction(function () use ($reservasi, $user) {
            // Hapus slot terkunci jika ada
            if ($reservasi->slotTerkunci) {
                $reservasi->slotTerkunci->delete();
            }

            // Cabut memo jika ada
            if ($reservasi->memoAktif) {
                $this->documentService->stampMemoTidakBerlaku(
                    $reservasi->memoAktif,
                    'Dibatalkan oleh pengguna pemesan',
                    $user->id
                );
            }

            $reservasi->update([
                'status' => 'dibatalkan',
            ]);

            Notifikasi::create([
                'pengguna_id' => $user->id,
                'judul'       => 'Reservasi Berhasil Dibatalkan',
                'isi'         => "Reservasi {$reservasi->kode} telah berhasil dibatalkan.",
                'tautan'      => "/app/reservasi/{$reservasi->id}",
            ]);

            return $reservasi;
        });
    }

    protected function generateKodeReservasi(): string
    {
        $tahun = date('Y');
        $random = strtoupper(Str::random(4));
        $counter = Reservasi::whereYear('dibuat_pada', $tahun)->count() + 1;
        $num = str_pad((string) $counter, 4, '0', STR_PAD_LEFT);

        return "RSV-{$tahun}-{$num}-{$random}";
    }

    /**
     * Memeriksa bentrok jadwal reservasi dengan jadwal akademik aktif.
     *
     * @param string $lokasiId
     * @param Carbon $mulai
     * @param Carbon $selesai
     * @throws HttpException
     */
    public function checkAcademicScheduleConflict(string $lokasiId, Carbon $mulai, Carbon $selesai): void
    {
        $weekdayMap = [
            'Monday'    => 'senin',
            'Tuesday'   => 'selasa',
            'Wednesday' => 'rabu',
            'Thursday'  => 'kamis',
            'Friday'    => 'jumat',
            'Saturday'  => 'sabtu',
            'Sunday'    => 'minggu',
        ];

        // Mulai dari awal hari kalender dari waktu mulai reservasi
        $current = $mulai->copy()->startOfDay();

        while ($current->lt($selesai)) {
            $nextDay = $current->copy()->addDay();

            // Batas interval segmen harian yang diuji
            $dayStart = $current->copy()->max($mulai);
            $dayEnd   = $nextDay->copy()->min($selesai);

            if ($dayStart->lt($dayEnd)) {
                $calendarDate = $current->toDateString();

                // 1. Cari seluruh periode akademik yang efektif pada tanggal kalender ini
                $periodIds = AcademicPeriod::whereDate('tanggal_mulai', '<=', $calendarDate)
                    ->whereDate('tanggal_selesai', '>=', $calendarDate)
                    ->pluck('id');

                if ($periodIds->isNotEmpty()) {
                    $dayNameEn = $current->englishDayOfWeek;
                    $dayNameId = $weekdayMap[$dayNameEn] ?? strtolower($dayNameEn);

                    $reachesMidnight = $dayEnd->eq($nextDay);

                    // 2. Kueri jadwal aktif untuk periode efektif, ruangan, dan hari yang sesuai
                    $conflictQuery = AcademicSchedule::whereIn('academic_period_id', $periodIds)
                        ->where('lokasi_id', $lokasiId)
                        ->where('hari', $dayNameId)
                        ->where('aktif', true);

                    if ($reachesMidnight) {
                        // Segmen berakhir tepat di pergantian hari (tengah malam).
                        // Semua jadwal pada hari tersebut memiliki jam_mulai < 24:00:00.
                        // Maka cukup pastikan jam_selesai > dayStart untuk memenuhi semantik [start, end).
                        $conflictQuery->where('jam_selesai', '>', $dayStart->format('H:i:s'));
                    } else {
                        // Segmen berakhir sebelum tengah malam: uji kedua batas interval half-open
                        $conflictQuery->where('jam_mulai', '<', $dayEnd->format('H:i:s'))
                            ->where('jam_selesai', '>', $dayStart->format('H:i:s'));
                    }

                    if ($conflictQuery->exists()) {
                        throw new HttpException(409, 'Jadwal yang dipilih bentrok dengan jadwal akademik.');
                    }
                }
            }

            // Maju ke hari kalender berikutnya (tepat 00:00:00 hari berikutnya)
            $current = $nextDay;
        }
    }
}

