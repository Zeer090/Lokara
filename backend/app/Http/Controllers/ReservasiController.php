<?php

namespace App\Http\Controllers;

use App\Models\Reservasi;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservasiController extends Controller
{
    public function __construct(protected ReservationService $reservationService)
    {
    }

    /**
     * POST /api/reservasi
     * Pengajuan reservasi oleh akun pengguna.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lokasi_id'       => 'required|uuid',
            'mulai'           => 'required|date',
            'selesai'         => 'required|date',
            'keperluan'       => 'required|string|max:1000',
            'jumlah_peserta'  => 'nullable|integer|min:1',
            'kebutuhan'       => 'nullable|array',
            'surat'           => 'nullable|file',
        ]);

        $fileSurat = $request->file('surat');
        $user = $request->user();

        $reservasi = $this->reservationService->ajukanReservasi($user, $data, $fileSurat);

        return response()->json([
            'pesan'     => $reservasi->status === 'disetujui'
                ? 'Reservasi ruangan berhasil disetujui otomatis.'
                : 'Pengajuan reservasi gedung berhasil dikirim dan menunggu persetujuan pengelola.',
            'reservasi' => $this->formatReservasi($reservasi),
        ], 201);
    }

    public function simpan(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * GET /api/reservasi/saya
     * Daftar reservasi milik pengguna yang sedang login.
     */
    public function saya(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Reservasi::where('pengguna_id', $user->id)
            ->with(['lokasi', 'memoAktif', 'kasusPenggusuran']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $daftar = $query->orderByDesc('dibuat_pada')->paginate(15);

        return response()->json([
            'data'         => collect($daftar->items())->map(fn (Reservasi $r) => $this->formatReservasi($r)),
            'total'        => $daftar->total(),
            'current_page' => $daftar->currentPage(),
            'last_page'    => $daftar->lastPage(),
        ]);
    }

    /**
     * GET /api/reservasi/:id
     * Detail satu reservasi. Hanya boleh diakses oleh pemilik atau pengelola_gedung yang berwenang.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $reservasi = Reservasi::with(['lokasi', 'surat', 'memoAktif', 'slotTerkunci', 'kasusPenggusuran', 'pengguna'])->find($id);

        if (! $reservasi) {
            return response()->json(['galat' => 'Reservasi tidak ditemukan.'], 404);
        }

        // Pengecekan hak akses
        $isOwner = $reservasi->pengguna_id === $user->id;
        $isManager = $user->peran === 'pengelola_gedung' && $user->mengelolaLokasi($reservasi->lokasi_id);

        if (! $isOwner && ! $isManager) {
            return response()->json(['galat' => 'Anda tidak memiliki hak akses untuk melihat reservasi ini.'], 403);
        }

        return response()->json([
            'reservasi' => $this->formatReservasi($reservasi, true),
        ]);
    }

    /**
     * POST /api/reservasi/:id/batal
     * Batalkan reservasi oleh pemiliknya.
     */
    public function batal(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $reservasi = $this->reservationService->batalkanReservasi($user, $id);

        return response()->json([
            'pesan'     => 'Reservasi berhasil dibatalkan.',
            'reservasi' => $this->formatReservasi($reservasi),
        ]);
    }

    protected function formatReservasi(Reservasi $r, bool $withDetail = false): array
    {
        $res = [
            'id'                => $r->id,
            'kode'              => $r->kode,
            'lokasi'            => [
                'id'         => $r->lokasi?->id,
                'nama'       => $r->lokasi?->nama,
                'jenis'      => $r->lokasi?->jenis,
                'kapasitas'  => $r->lokasi?->kapasitas,
                'sementara'  => $r->lokasi?->sementara,
            ],
            'mulai'             => $r->mulai->toIso8601String(),
            'selesai'           => $r->selesai->toIso8601String(),
            'status'            => $r->status,
            'jenis_penggunaan'  => $r->jenis_penggunaan,
            'alur_persetujuan'  => $r->alur_persetujuan,
            'jumlah_peserta'    => $r->jumlah_peserta,
            'keperluan'         => $r->keperluan,
            'catatan_admin'     => $r->catatan_admin,
            'has_surat'         => $r->surat_id !== null,
            'has_memo'          => $r->memoAktif !== null,
            'memo_id'           => $r->memoAktif?->id,
            'memo_nomor'        => $r->memoAktif?->nomor_memo,
            'kasus_id'          => $r->kasusPenggusuran?->id,
            'reservasi_asal_id' => $r->reservasi_asal_id,
            'dibuat_pada'       => $r->dibuat_pada->toIso8601String(),
        ];

        if ($withDetail) {
            $res['pemohon'] = [
                'id'         => $r->pengguna?->id,
                'nama'       => $r->pengguna?->nama,
                'email'      => $r->pengguna?->email,
                'organisasi' => $r->pengguna?->organisasi,
                'no_hp'      => $r->pengguna?->no_hp,
            ];
            $res['kebutuhan'] = $r->kebutuhan;
            if ($r->surat) {
                $res['surat'] = [
                    'id'          => $r->surat->id,
                    'nama_asli'   => $r->surat->nama_asli,
                    'ukuran_byte' => $r->surat->ukuran_byte,
                ];
            }
        }

        return $res;
    }
}
