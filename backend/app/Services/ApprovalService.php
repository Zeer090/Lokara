<?php

namespace App\Services;

use App\Models\Lokasi;
use App\Models\Memo;
use App\Models\MemoRiwayat;
use App\Models\Notifikasi;
use App\Models\Pengguna;
use App\Models\PengelolaLokasi;
use App\Models\Reservasi;
use App\Models\SlotTerkunci;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApprovalService
{
    public function __construct(protected DocumentService $documentService)
    {
    }

    /**
     * Setujui pengajuan reservasi gedung berstatus 'menunggu'.
     * Syarat sah persetujuan:
     * 1. alur_persetujuan = manual
     * 2. status = menunggu
     * 3. Pemanggil terdaftar di pengelola_lokasi untuk gedung itu
     * 4. Pemanggil berperan pengelola_gedung
     */
    public function setujui(Pengguna $approver, string $reservasiId): Reservasi
    {
        $reservasi = Reservasi::with(['lokasi', 'pengguna', 'surat'])->find($reservasiId);

        if (! $reservasi) {
            throw new HttpException(404, 'Reservasi tidak ditemukan.');
        }

        $this->validasiKewenanganPengelola($approver, $reservasi->lokasi_id);

        if ($reservasi->status !== 'menunggu') {
            throw new HttpException(400, "Hanya reservasi berstatus menunggu yang dapat disetujui (saat ini: {$reservasi->status}).");
        }

        if ($reservasi->alur_persetujuan !== 'manual' || $reservasi->lokasi->jenis !== 'gedung') {
            throw new HttpException(400, 'Hanya reservasi gedung dengan alur manual yang memerlukan persetujuan pengelola.');
        }

        return DB::transaction(function () use ($approver, $reservasi) {
            $mulaiStr = Carbon::parse($reservasi->mulai)->toIso8601String();
            $selesaiStr = Carbon::parse($reservasi->selesai)->toIso8601String();

            // 1. Kunci slot secara atomik di slot_terkunci
            try {
                DB::statement(
                    "INSERT INTO slot_terkunci (lokasi_id, rentang, sumber, reservasi_id)
                     VALUES (?, tstzrange(?, ?, '[)'), 'reservasi', ?)",
                    [$reservasi->lokasi_id, $mulaiStr, $selesaiStr, $reservasi->id]
                );
            } catch (QueryException $e) {
                $isConflict = ($e->errorInfo[0] ?? $e->getCode()) === '23P01';
                if ($isConflict) {
                    throw new HttpException(409, 'Persetujuan gagal: jadwal bentrok dengan jadwal yang sudah terkunci.');
                }
                throw $e;
            }

            // 2. Terbitkan nomor memo resmi berurutan (MEMO/YYYY/XXXX)
            $tahun = date('Y');
            $counter = Memo::whereYear('diterbitkan_pada', $tahun)->count() + 1;
            $nomorMemo = sprintf('MEMO/%s/%04d', $tahun, $counter);

            // 3. Buat berkas PDF memo
            $pathBerkas = $this->documentService->generateMemoPdf($reservasi, $nomorMemo);

            // 4. Simpan baris memo
            $memo = Memo::create([
                'reservasi_id'     => $reservasi->id,
                'nomor_memo'       => $nomorMemo,
                'path_berkas'      => $pathBerkas,
                'status'           => 'berlaku',
                'diterbitkan_pada' => now(),
            ]);

            // 5. Catat riwayat penerbitan memo
            MemoRiwayat::create([
                'memo_id'     => $memo->id,
                'aksi'        => 'terbit',
                'oleh'        => $approver->id,
                'alasan'      => 'Persetujuan resmi pengelola gedung',
            ]);

            // 6. Ubah status reservasi menjadi 'disetujui'
            $reservasi->update([
                'status' => 'disetujui',
            ]);

            // 7. Jika ini adalah reservasi pengganti, selesaikan kasus penggusuran terkait
            if ($reservasi->reservasi_asal_id) {
                DB::table('kasus_penggusuran')
                    ->where('reservasi_pengganti_id', $reservasi->id)
                    ->update([
                        'status'       => 'selesai',
                        'selesai_pada' => now(),
                    ]);
            }

            // 8. Kirim notifikasi persetujuan kepada pengguna
            Notifikasi::create([
                'pengguna_id' => $reservasi->pengguna_id,
                'judul'       => 'Reservasi Gedung Disetujui',
                'isi'         => "Pengajuan gedung {$reservasi->lokasi->nama} ({$reservasi->kode}) telah disetujui oleh {$approver->nama}. Memo resmi telah diterbitkan.",
                'tautan'      => "/app/reservasi/{$reservasi->id}",
            ]);

            return $reservasi->fresh(['lokasi', 'memoAktif', 'slotTerkunci']);
        });
    }

    /**
     * Tolak pengajuan reservasi gedung dengan alasan wajib.
     */
    public function tolak(Pengguna $approver, string $reservasiId, string $alasan): Reservasi
    {
        $alasan = trim($alasan);
        if (empty($alasan)) {
            throw new HttpException(422, 'Alasan penolakan wajib diisi.');
        }

        $reservasi = Reservasi::with('lokasi')->find($reservasiId);

        if (! $reservasi) {
            throw new HttpException(404, 'Reservasi tidak ditemukan.');
        }

        $this->validasiKewenanganPengelola($approver, $reservasi->lokasi_id);

        if ($reservasi->status !== 'menunggu') {
            throw new HttpException(400, "Hanya reservasi berstatus menunggu yang dapat ditolak (saat ini: {$reservasi->status}).");
        }

        $reservasi->update([
            'status'        => 'ditolak',
            'catatan_admin' => $alasan,
        ]);

        Notifikasi::create([
            'pengguna_id' => $reservasi->pengguna_id,
            'judul'       => 'Pengajuan Reservasi Ditolak',
            'isi'         => "Pengajuan {$reservasi->kode} di {$reservasi->lokasi->nama} ditolak. Alasan: {$alasan}",
            'tautan'      => "/app/reservasi/{$reservasi->id}",
        ]);

        return $reservasi;
    }

    /**
     * Ubah jadwal atau lokasi gedung oleh pengelola gedung saat persetujuan ("Ubah").
     * Gedung tujuan harus merupakan gedung yang juga dikelola oleh pengelola pemanggil.
     */
    public function ubah(Pengguna $approver, string $reservasiId, array $data): Reservasi
    {
        $reservasi = Reservasi::with(['lokasi', 'pengguna'])->find($reservasiId);

        if (! $reservasi) {
            throw new HttpException(404, 'Reservasi tidak ditemukan.');
        }

        $this->validasiKewenanganPengelola($approver, $reservasi->lokasi_id);

        if ($reservasi->status !== 'menunggu') {
            throw new HttpException(400, "Hanya reservasi berstatus menunggu yang dapat diubah dan disetujui.");
        }

        $targetLokasiId = $data['lokasi_id'] ?? $reservasi->lokasi_id;
        $this->validasiKewenanganPengelola($approver, $targetLokasiId);

        $mulai = Carbon::parse($data['mulai'] ?? $reservasi->mulai);
        $selesai = Carbon::parse($data['selesai'] ?? $reservasi->selesai);
        $alasanPerubahan = trim($data['alasan'] ?? 'Penyesuaian jadwal oleh pengelola gedung.');

        if ($selesai->lte($mulai)) {
            throw new HttpException(422, 'Waktu selesai harus setelah waktu mulai.');
        }

        return DB::transaction(function () use ($approver, $reservasi, $targetLokasiId, $mulai, $selesai, $alasanPerubahan) {
            $mulaiStr = $mulai->toIso8601String();
            $selesaiStr = $selesai->toIso8601String();

            // 1. Kunci slot baru
            try {
                DB::statement(
                    "INSERT INTO slot_terkunci (lokasi_id, rentang, sumber, reservasi_id)
                     VALUES (?, tstzrange(?, ?, '[)'), 'reservasi', ?)",
                    [$targetLokasiId, $mulaiStr, $selesaiStr, $reservasi->id]
                );
            } catch (QueryException $e) {
                $isConflict = ($e->errorInfo[0] ?? $e->getCode()) === '23P01';
                if ($isConflict) {
                    throw new HttpException(409, 'Persetujuan perubahan gagal: jadwal baru bentrok dengan jadwal lain.');
                }
                throw $e;
            }

            // 2. Perbarui reservasi
            $reservasi->update([
                'lokasi_id'     => $targetLokasiId,
                'mulai'         => $mulai,
                'selesai'       => $selesai,
                'status'        => 'disetujui',
                'catatan_admin' => 'Disetujui dengan perubahan: ' . $alasanPerubahan,
            ]);

            // 3. Terbitkan memo
            $tahun = date('Y');
            $counter = Memo::whereYear('diterbitkan_pada', $tahun)->count() + 1;
            $nomorMemo = sprintf('MEMO/%s/%04d', $tahun, $counter);
            $pathBerkas = $this->documentService->generateMemoPdf($reservasi->fresh(['lokasi', 'pengguna']), $nomorMemo);

            $memo = Memo::create([
                'reservasi_id'     => $reservasi->id,
                'nomor_memo'       => $nomorMemo,
                'path_berkas'      => $pathBerkas,
                'status'           => 'berlaku',
                'diterbitkan_pada' => now(),
            ]);

            MemoRiwayat::create([
                'memo_id'     => $memo->id,
                'aksi'        => 'terbit',
                'oleh'        => $approver->id,
                'alasan'      => 'Disetujui dengan penyesuaian: ' . $alasanPerubahan,
            ]);

            Notifikasi::create([
                'pengguna_id' => $reservasi->pengguna_id,
                'judul'       => 'Reservasi Disetujui dengan Penyesuaian Jadwal',
                'isi'         => "Pengajuan {$reservasi->kode} disetujui dengan penyesuaian oleh pengelola. Catatan: {$alasanPerubahan}",
                'tautan'      => "/app/reservasi/{$reservasi->id}",
            ]);

            return $reservasi->fresh(['lokasi', 'memoAktif', 'slotTerkunci']);
        });
    }

    /**
     * Verifikasi bahwa pemanggil adalah pengelola_gedung yang terdaftar pada gedung tersebut.
     */
    public function validasiKewenanganPengelola(Pengguna $user, string $lokasiId): void
    {
        if ($user->peran !== 'pengelola_gedung') {
            throw new HttpException(403, 'Akses ditolak: hanya pengelola_gedung yang berwenang atas pengajuan ini.');
        }

        $lokasi = Lokasi::find($lokasiId);
        if (! $lokasi || $lokasi->jenis !== 'gedung') {
            throw new HttpException(400, 'Hanya lokasi berjenis gedung yang memiliki pengelola.');
        }

        $terdaftar = PengelolaLokasi::where('pengguna_id', $user->id)
            ->where('lokasi_id', $lokasiId)
            ->exists();

        if (! $terdaftar) {
            throw new HttpException(403, 'Anda tidak memiliki penugasan untuk mengelola gedung ini.');
        }
    }
}

