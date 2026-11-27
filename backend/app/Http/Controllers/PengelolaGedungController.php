<?php

namespace App\Http\Controllers;

use App\Models\AcaraKampus;
use App\Models\KasusPenggusuran;
use App\Models\Lokasi;
use App\Models\PengelolaLokasi;
use App\Models\Reservasi;
use App\Models\SlotTerkunci;
use App\Services\ApprovalService;
use App\Services\EvictionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengelolaGedungController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService,
        protected EvictionService $evictionService
    ) {
    }

    /**
     * GET /api/pengelola/dashboard
     * Ringkasan gedung yang dikelola, pengajuan menunggu, acara terdekat, dan kasus terbuka.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $gedungIds = PengelolaLokasi::where('pengguna_id', $user->id)->pluck('lokasi_id');

        $gedungList = Lokasi::whereIn('id', $gedungIds)->get();

        $jumlahMenunggu = Reservasi::whereIn('lokasi_id', $gedungIds)
            ->where('status', 'menunggu')
            ->count();

        $jumlahDisetujui = Reservasi::whereIn('lokasi_id', $gedungIds)
            ->where('status', 'disetujui')
            ->count();

        $kasusTerbuka = KasusPenggusuran::whereHas('reservasi', function ($q) use ($gedungIds) {
            $q->whereIn('lokasi_id', $gedungIds);
        })->whereIn('status', ['terbuka', 'diputuskan'])->count();

        $acaraTerdekat = AcaraKampus::whereIn('lokasi_id', $gedungIds)
            ->where('mulai', '>=', now())
            ->orderBy('mulai')
            ->take(5)
            ->with('lokasi')
            ->get();

        return response()->json([
            'pengelola' => [
                'nama'  => $user->nama,
                'email' => $user->email,
            ],
            'statistik' => [
                'gedung_dikelola'  => $gedungList->count(),
                'antrean_menunggu' => $jumlahMenunggu,
                'total_disetujui'  => $jumlahDisetujui,
                'kasus_terbuka'    => $kasusTerbuka,
            ],
            'gedung_dikelola' => $gedungList->map(fn (Lokasi $l) => [
                'id'        => $l->id,
                'nama'      => $l->nama,
                'kapasitas' => $l->kapasitas,
                'sementara' => $l->sementara,
            ]),
            'acara_terdekat' => $acaraTerdekat->map(fn (AcaraKampus $a) => [
                'id'     => $a->id,
                'nama'   => $a->nama,
                'gedung' => $a->lokasi?->nama,
                'mulai'  => $a->mulai->toIso8601String(),
                'selesai'=> $a->selesai->toIso8601String(),
            ]),
        ]);
    }

    /**
     * GET /api/pengelola/pengajuan
     * Daftar pengajuan reservasi gedung berstatus 'menunggu' untuk gedung yang dikelola.
     */
    public function pengajuan(Request $request): JsonResponse
    {
        $user = $request->user();
        $gedungIds = PengelolaLokasi::where('pengguna_id', $user->id)->pluck('lokasi_id');

        $query = Reservasi::whereIn('lokasi_id', $gedungIds)
            ->with(['lokasi', 'pengguna', 'surat']);

        if ($request->filled('status') && $request->query('status') !== 'semua') {
            $query->where('status', $request->query('status'));
        } elseif (!$request->has('status')) {
            // Default tanpa parameter: hanya tampilkan antrean menunggu
            $query->where('status', 'menunggu');
        }
        // Jika status === 'semua', tidak filter — tampilkan semua status

        $daftar = $query->orderBy('mulai')->paginate(15);

        return response()->json([
            'data' => collect($daftar->items())->map(function (Reservasi $r) {
                return [
                    'id'               => $r->id,
                    'kode'             => $r->kode,
                    'gedung'           => $r->lokasi?->nama,
                    'gedung_id'        => $r->lokasi?->id,
                    'pemohon'          => $r->pengguna?->nama,
                    'organisasi'       => $r->pengguna?->organisasi,
                    'mulai'            => $r->mulai->toIso8601String(),
                    'selesai'          => $r->selesai->toIso8601String(),
                    'status'           => $r->status,
                    'jumlah_peserta'   => $r->jumlah_peserta,
                    'keperluan'        => $r->keperluan,
                    'has_surat'        => $r->surat_id !== null,
                    'surat_id'         => $r->surat_id,
                    'is_pengganti'     => $r->reservasi_asal_id !== null,
                    'dibuat_pada'      => $r->dibuat_pada->toIso8601String(),
                ];
            }),
            'total'        => $daftar->total(),
            'current_page' => $daftar->currentPage(),
            'last_page'    => $daftar->lastPage(),
        ]);
    }

    /**
     * GET /api/pengelola/pengajuan/:id
     * Detail satu pengajuan untuk pemeriksaan pengelola.
     */
    public function detailPengajuan(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $reservasi = Reservasi::with(['lokasi', 'pengguna', 'surat', 'memoAktif', 'reservasiAsal'])->find($id);

        if (! $reservasi) {
            return response()->json(['galat' => 'Pengajuan reservasi tidak ditemukan.'], 404);
        }

        $this->approvalService->validasiKewenanganPengelola($user, $reservasi->lokasi_id);

        return response()->json([
            'pengajuan' => [
                'id'                => $reservasi->id,
                'kode'              => $reservasi->kode,
                'status'            => $reservasi->status,
                'gedung'            => [
                    'id'        => $reservasi->lokasi?->id,
                    'nama'      => $reservasi->lokasi?->nama,
                    'kapasitas' => $reservasi->lokasi?->kapasitas,
                ],
                'pemohon'           => [
                    'id'         => $reservasi->pengguna?->id,
                    'nama'       => $reservasi->pengguna?->nama,
                    'email'      => $reservasi->pengguna?->email,
                    'organisasi' => $reservasi->pengguna?->organisasi,
                    'no_hp'      => $reservasi->pengguna?->no_hp,
                ],
                'mulai'             => $reservasi->mulai->toIso8601String(),
                'selesai'           => $reservasi->selesai->toIso8601String(),
                'jumlah_peserta'    => $reservasi->jumlah_peserta,
                'keperluan'         => $reservasi->keperluan,
                'kebutuhan'         => $reservasi->kebutuhan,
                'catatan_admin'     => $reservasi->catatan_admin,
                'surat'             => $reservasi->surat ? [
                    'id'          => $reservasi->surat->id,
                    'nama_asli'   => $reservasi->surat->nama_asli,
                    'ukuran_byte' => $reservasi->surat->ukuran_byte,
                ] : null,
                'memo'              => $reservasi->memoAktif ? [
                    'id'          => $reservasi->memoAktif->id,
                    'nomor_memo'  => $reservasi->memoAktif->nomor_memo,
                    'status'      => $reservasi->memoAktif->status,
                ] : null,
                'is_pengganti'      => $reservasi->reservasi_asal_id !== null,
                'reservasi_asal'    => $reservasi->reservasiAsal ? [
                    'kode'  => $reservasi->reservasiAsal->kode,
                    'mulai' => $reservasi->reservasiAsal->mulai->toIso8601String(),
                ] : null,
                'dibuat_pada'       => $reservasi->dibuat_pada->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/pengelola/pengajuan/:id/setuju
     */
    public function setuju(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $reservasi = $this->approvalService->setujui($user, $id);

        return response()->json([
            'pesan'     => 'Reservasi gedung berhasil disetujui dan memo resmi diterbitkan.',
            'reservasi' => $reservasi,
        ]);
    }

    /**
     * POST /api/pengelola/pengajuan/:id/tolak
     */
    public function tolak(Request $request, string $id): JsonResponse
    {
        $request->validate(['alasan' => 'required|string|min:3|max:1000']);
        $user = $request->user();
        $reservasi = $this->approvalService->tolak($user, $id, $request->input('alasan'));

        return response()->json([
            'pesan'     => 'Pengajuan reservasi telah ditolak.',
            'reservasi' => $reservasi,
        ]);
    }

    /**
     * POST /api/pengelola/pengajuan/:id/ubah
     */
    public function ubah(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'lokasi_id' => 'nullable|uuid',
            'mulai'     => 'required|date',
            'selesai'   => 'required|date',
            'alasan'    => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $reservasi = $this->approvalService->ubah($user, $id, $data);

        return response()->json([
            'pesan'     => 'Reservasi berhasil disetujui dengan penyesuaian jadwal.',
            'reservasi' => $reservasi,
        ]);
    }

    /**
     * GET /api/pengelola/kalender?bulan=YYYY-MM
     * Kalender gabungan untuk gedung yang dikelola:
     * Hijau (Disetujui), Biru (Acara Kampus), Merah (Tergusur), Abu-abu (Menunggu)
     */
    public function kalender(Request $request): JsonResponse
    {
        $user = $request->user();
        $gedungIds = PengelolaLokasi::where('pengguna_id', $user->id)->pluck('lokasi_id');

        $bulanParam = $request->query('bulan', now()->format('Y-m'));
        try {
            $awalBulan = Carbon::createFromFormat('Y-m', $bulanParam)->startOfMonth()->subDays(7);
            $akhirBulan = Carbon::createFromFormat('Y-m', $bulanParam)->endOfMonth()->addDays(7);
        } catch (\Throwable) {
            $awalBulan = now()->startOfMonth()->subDays(7);
            $akhirBulan = now()->endOfMonth()->addDays(7);
        }

        $awalStr = $awalBulan->toIso8601String();
        $akhirStr = $akhirBulan->toIso8601String();

        // 1. Slot terkunci (Disetujui & Acara Kampus)
        $slots = SlotTerkunci::whereIn('lokasi_id', $gedungIds)
            ->whereRaw("rentang && tstzrange(?, ?, '[)')", [$awalStr, $akhirStr])
            ->with(['lokasi', 'reservasi.pengguna', 'acara'])
            ->get();

        $events = [];

        foreach ($slots as $s) {
            preg_match('/[\[\(]"?([^",]+)"?,\s*"?([^",\)]+)"?[\]\)]/', $s->rentang, $matches);
            $mulai = $matches[1] ?? null;
            $selesai = $matches[2] ?? null;

            if ($s->sumber === 'acara_kampus') {
                $events[] = [
                    'id'        => $s->id,
                    'title'     => 'Acara: ' . ($s->acara?->nama ?? 'Acara Kampus'),
                    'start'     => $mulai ? Carbon::parse($mulai)->toIso8601String() : null,
                    'end'       => $selesai ? Carbon::parse($selesai)->toIso8601String() : null,
                    'color'     => '#2563eb', // biru
                    'jenis'     => 'acara_kampus',
                    'lokasi'    => $s->lokasi?->nama,
                ];
            } else {
                $events[] = [
                    'id'        => $s->id,
                    'title'     => ($s->reservasi?->pengguna?->nama ?? 'Pengguna') . ' (' . $s->reservasi?->kode . ')',
                    'start'     => $mulai ? Carbon::parse($mulai)->toIso8601String() : null,
                    'end'       => $selesai ? Carbon::parse($selesai)->toIso8601String() : null,
                    'color'     => '#16a34a', // hijau
                    'jenis'     => 'reservasi_disetujui',
                    'lokasi'    => $s->lokasi?->nama,
                ];
            }
        }

        // 2. Reservasi menunggu (abu-abu, informasional, tidak mengunci)
        $menunggu = Reservasi::whereIn('lokasi_id', $gedungIds)
            ->where('status', 'menunggu')
            ->whereBetween('mulai', [$awalBulan, $akhirBulan])
            ->with(['lokasi', 'pengguna'])
            ->get();

        foreach ($menunggu as $m) {
            $events[] = [
                'id'     => 'menunggu_' . $m->id,
                'title'  => '[Menunggu] ' . ($m->pengguna?->nama ?? 'Pengguna'),
                'start'  => $m->mulai->toIso8601String(),
                'end'    => $m->selesai->toIso8601String(),
                'color'  => '#9ca3af', // abu-abu
                'jenis'  => 'menunggu',
                'lokasi' => $m->lokasi?->nama,
            ];
        }

        // 3. Reservasi tergusur (merah)
        $tergusur = Reservasi::whereIn('lokasi_id', $gedungIds)
            ->where('status', 'tergusur')
            ->whereBetween('mulai', [$awalBulan, $akhirBulan])
            ->with(['lokasi', 'pengguna'])
            ->get();

        foreach ($tergusur as $t) {
            $events[] = [
                'id'     => 'tergusur_' . $t->id,
                'title'  => '[Tergusur] ' . ($t->pengguna?->nama ?? 'Pengguna'),
                'start'  => $t->mulai->toIso8601String(),
                'end'    => $t->selesai->toIso8601String(),
                'color'  => '#dc2626', // merah
                'jenis'  => 'tergusur',
                'lokasi' => $t->lokasi?->nama,
            ];
        }

        return response()->json([
            'bulan'  => $bulanParam,
            'events' => $events,
        ]);
    }

    /**
     * POST /api/pengelola/acara-kampus/pratinjau
     */
    public function pratinjauAcara(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lokasi_id' => 'required|uuid',
            'mulai'     => 'required|date',
            'selesai'   => 'required|date',
        ]);

        $hasil = $this->evictionService->pratinjauAcara($request->user(), $data);

        return response()->json($hasil);
    }

    /**
     * POST /api/pengelola/acara-kampus
     */
    public function daftarkanAcara(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama'      => 'required|string|max:200',
            'lokasi_id' => 'required|uuid',
            'mulai'     => 'required|date',
            'selesai'   => 'required|date',
            'alasan'    => 'required|string|min:5|max:1000',
        ]);

        $hasil = $this->evictionService->daftarkanAcara($request->user(), $data);

        return response()->json($hasil, 201);
    }

    /**
     * POST /api/pengelola/reservasi/:id/gusur
     */
    public function batalkanManual(Request $request, string $id): JsonResponse
    {
        $request->validate(['alasan' => 'required|string|min:5|max:1000']);
        $kasus = $this->evictionService->gusurReservasiManual($request->user(), $id, $request->input('alasan'));

        return response()->json([
            'pesan' => 'Reservasi berhasil dibatalkan dan kasus penggusuran telah dibuka.',
            'kasus' => $kasus,
        ]);
    }

    /**
     * GET /api/pengelola/kasus
     * Daftar kasus penggusuran pada gedung yang dikelola.
     */
    public function kasus(Request $request): JsonResponse
    {
        $user = $request->user();
        $gedungIds = PengelolaLokasi::where('pengguna_id', $user->id)->pluck('lokasi_id');

        $query = KasusPenggusuran::whereHas('reservasi', function ($q) use ($gedungIds) {
            $q->whereIn('lokasi_id', $gedungIds);
        })->with(['reservasi.pengguna', 'reservasi.lokasi', 'acara']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $daftar = $query->orderByDesc('dibuat_pada')->paginate(15);

        return response()->json([
            'data' => collect($daftar->items())->map(function (KasusPenggusuran $k) {
                return [
                    'id'               => $k->id,
                    'status'           => $k->status,
                    'alasan'           => $k->alasan,
                    'acara_nama'       => $k->acara?->nama,
                    'kode_reservasi'   => $k->reservasi?->kode,
                    'gedung'           => $k->reservasi?->lokasi?->nama,
                    'pemohon'          => $k->reservasi?->pengguna?->nama,
                    'organisasi'       => $k->reservasi?->pengguna?->organisasi,
                    'mulai_tergusur'   => $k->reservasi?->mulai?->toIso8601String(),
                    'dibuat_pada'      => $k->dibuat_pada->toIso8601String(),
                ];
            }),
            'total'        => $daftar->total(),
            'current_page' => $daftar->currentPage(),
            'last_page'    => $daftar->lastPage(),
        ]);
    }

    /**
     * GET /api/pengelola/kasus/:id
     * Detail kasus untuk peninjauan pengelola (hanya baca).
     */
    public function detailKasus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $kasus = KasusPenggusuran::with([
            'reservasi.lokasi', 'reservasi.pengguna', 'reservasi.surat',
            'acara', 'pesanNegosiasi', 'usulanAgent.lokasi', 'logAgent',
            'reservasiPengganti.lokasi',
        ])->find($id);

        if (! $kasus) {
            return response()->json(['galat' => 'Kasus tidak ditemukan.'], 404);
        }

        $this->approvalService->validasiKewenanganPengelola($user, $kasus->reservasi->lokasi_id);

        return response()->json([
            'kasus' => $kasus,
        ]);
    }

    /**
     * GET /api/pengelola/laporan
     * Laporan ringkasan untuk gedung yang dikelola.
     */
    public function laporan(Request $request): JsonResponse
    {
        $user = $request->user();
        $gedungIds = PengelolaLokasi::where('pengguna_id', $user->id)->pluck('lokasi_id');

        $totalReservasi = Reservasi::whereIn('lokasi_id', $gedungIds)->count();
        $totalDisetujui = Reservasi::whereIn('lokasi_id', $gedungIds)->where('status', 'disetujui')->count();
        $totalTergusur = Reservasi::whereIn('lokasi_id', $gedungIds)->where('status', 'tergusur')->count();
        $totalDitolak = Reservasi::whereIn('lokasi_id', $gedungIds)->where('status', 'ditolak')->count();
        $totalAcara = AcaraKampus::whereIn('lokasi_id', $gedungIds)->count();

        return response()->json([
            'cakupan' => 'Gedung yang Dikelola',
            'ringkasan' => [
                'total_reservasi' => $totalReservasi,
                'disetujui'       => $totalDisetujui,
                'tergusur'        => $totalTergusur,
                'ditolak'         => $totalDitolak,
                'acara_kampus'    => $totalAcara,
            ],
        ]);
    }

    public function lokasiDikelola(Request $request): JsonResponse
    {
        return $this->dashboard($request);
    }

    public function daftarReservasi(Request $request): JsonResponse
    {
        return $this->pengajuan($request);
    }

    public function detailReservasi(Request $request, string $id): JsonResponse
    {
        return $this->detailPengajuan($request, $id);
    }

    public function setujuiReservasi(Request $request, string $id): JsonResponse
    {
        return $this->setuju($request, $id);
    }

    public function tolakReservasi(Request $request, string $id): JsonResponse
    {
        return $this->tolak($request, $id);
    }

    public function ubahReservasi(Request $request, string $id): JsonResponse
    {
        return $this->ubah($request, $id);
    }

    public function gusurReservasi(Request $request, string $id): JsonResponse
    {
        return $this->batalkanManual($request, $id);
    }

    public function pratinjauAcaraKampus(Request $request): JsonResponse
    {
        return $this->pratinjauAcara($request);
    }

    public function buatAcaraKampus(Request $request): JsonResponse
    {
        return $this->daftarkanAcara($request);
    }

    public function daftarKasus(Request $request): JsonResponse
    {
        return $this->kasus($request);
    }
}
