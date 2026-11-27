<?php

namespace App\Http\Controllers;

use App\Models\LaporanFasilitas;
use App\Models\LogAgent;
use App\Models\LogAksesDokumen;
use App\Models\Lokasi;
use App\Models\Pengaturan;
use App\Models\PengelolaLokasi;
use App\Models\Pengguna;
use App\Models\Reservasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PengelolaSistemController extends Controller
{
    /**
     * GET /api/sistem/pengguna
     * Daftar semua akun pengguna.
     */
    public function indexPengguna(Request $request): JsonResponse
    {
        $query = Pengguna::query()
            ->select(['id', 'nama', 'email', 'peran', 'organisasi', 'no_hp', 'aktif', 'dibuat_pada']);

        if ($request->filled('peran')) {
            $query->where('peran', $request->query('peran'));
        }

        if ($request->filled('aktif')) {
            $query->where('aktif', filter_var($request->query('aktif'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('cari')) {
            $cari = '%' . $request->query('cari') . '%';
            $query->where(function ($q) use ($cari) {
                $q->where('nama', 'ilike', $cari)
                    ->orWhere('email', 'ilike', $cari)
                    ->orWhere('organisasi', 'ilike', $cari);
            });
        }

        $pengguna = $query->orderBy('nama')->paginate(25);

        return response()->json($pengguna);
    }

    /**
     * POST /api/sistem/pengguna/{id}/peran
     * Ubah peran pengguna.
     */
    public function ubahPeran(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'peran' => 'required|in:pengguna,pengelola_gedung,pengelola_sistem,akademik',
        ]);

        $targetUser = Pengguna::findOrFail($id);
        $peranBaru = $request->input('peran');
        $currentUser = $request->user();

        if ($targetUser->peran === $peranBaru) {
            return response()->json([
                'pesan' => 'Peran tidak berubah.',
                'pengguna' => $targetUser,
            ]);
        }

        // Cegah pengelola sistem satu-satunya mendemosi diri sendiri
        if ($targetUser->id === $currentUser->id && $targetUser->peran === 'pengelola_sistem' && $peranBaru !== 'pengelola_sistem') {
            $sistemCount = Pengguna::where('peran', 'pengelola_sistem')->where('aktif', true)->count();
            if ($sistemCount <= 1) {
                return response()->json([
                    'galat' => 'Tidak dapat mengubah peran satu-satunya pengelola sistem aktif.',
                ], 422);
            }
        }

        // Kenaikan peran dari pengguna ke pengelola ditolak bila ada reservasi aktif atau kasus terbuka
        if ($targetUser->peran === 'pengguna' && in_array($peranBaru, ['pengelola_gedung', 'pengelola_sistem'], true)) {
            $adaReservasiAktif = Reservasi::where('pengguna_id', $targetUser->id)
                ->whereIn('status', ['menunggu', 'disetujui'])
                ->exists();

            if ($adaReservasiAktif) {
                return response()->json([
                    'galat' => 'Pengguna masih memiliki reservasi aktif (menunggu/disetujui). Selesaikan atau batalkan terlebih dahulu.',
                ], 422);
            }

            $adaKasusTerbuka = DB::table('kasus_penggusuran as k')
                ->join('reservasi as r', 'r.id', '=', 'k.reservasi_id')
                ->where('r.pengguna_id', $targetUser->id)
                ->whereIn('k.status', ['terbuka', 'diputuskan', 'diajukan_ulang'])
                ->exists();

            if ($adaKasusTerbuka) {
                return response()->json([
                    'galat' => 'Pengguna masih memiliki kasus penggusuran terbuka. Selesaikan kasus terlebih dahulu.',
                ], 422);
            }
        }

        DB::transaction(function () use ($targetUser, $peranBaru) {
            // Bila peran lama pengelola_gedung dan peran baru bukan pengelola_gedung, cabut penugasannya
            if ($targetUser->peran === 'pengelola_gedung' && $peranBaru !== 'pengelola_gedung') {
                PengelolaLokasi::where('pengguna_id', $targetUser->id)->delete();
            }

            $targetUser->update(['peran' => $peranBaru]);
        });

        return response()->json([
            'pesan' => 'Peran berhasil diperbarui.',
            'pengguna' => $targetUser->fresh(),
        ]);
    }

    /**
     * PATCH /api/sistem/pengguna/{id}/aktif
     * Aktifkan atau nonaktifkan akun pengguna.
     */
    public function ubahStatusAktif(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'aktif' => 'required|boolean',
        ]);

        $targetUser = Pengguna::findOrFail($id);
        $aktifBaru = (bool) $request->input('aktif');
        $currentUser = $request->user();

        // Cegah menonaktifkan diri sendiri bila pengelola sistem satu-satunya
        if ($targetUser->id === $currentUser->id && ! $aktifBaru && $targetUser->peran === 'pengelola_sistem') {
            $sistemCount = Pengguna::where('peran', 'pengelola_sistem')->where('aktif', true)->count();
            if ($sistemCount <= 1) {
                return response()->json([
                    'galat' => 'Tidak dapat menonaktifkan satu-satunya pengelola sistem aktif.',
                ], 422);
            }
        }

        $targetUser->update(['aktif' => $aktifBaru]);

        return response()->json([
            'pesan' => 'Status aktif pengguna berhasil diperbarui.',
            'pengguna' => $targetUser,
        ]);
    }

    /**
     * GET /api/sistem/lokasi
     * Daftar semua lokasi untuk dikelola pengelola sistem.
     */
    public function indexLokasi(Request $request): JsonResponse
    {
        $query = Lokasi::query()->with(['pengelolaLokasi.pengguna:id,nama,email']);

        if ($request->filled('jenis')) {
            $query->where('jenis', $request->query('jenis'));
        }

        if ($request->filled('aktif')) {
            $query->where('aktif', filter_var($request->query('aktif'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('sementara')) {
            $query->where('sementara', filter_var($request->query('sementara'), FILTER_VALIDATE_BOOLEAN));
        }

        $lokasi = $query->orderBy('nama')->get();

        return response()->json($lokasi);
    }

    /**
     * POST /api/sistem/lokasi
     * Tambah data lokasi baru.
     */
    public function simpanLokasi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama'      => 'required|string|max:100|unique:lokasi,nama',
            'jenis'     => 'required|in:ruangan,gedung',
            'kapasitas' => 'required|integer|min:1',
            'fasilitas' => 'nullable|array',
            'fasilitas.*' => 'string|max:100',
            'aktif'     => 'nullable|boolean',
            'sementara' => 'nullable|boolean',
        ]);

        $lokasi = Lokasi::create([
            'nama'      => $data['nama'],
            'jenis'     => $data['jenis'],
            'kapasitas' => $data['kapasitas'],
            'fasilitas' => $data['fasilitas'] ?? [],
            'aktif'     => $data['aktif'] ?? true,
            'sementara' => $data['sementara'] ?? false,
        ]);

        return response()->json([
            'pesan'  => 'Lokasi berhasil ditambahkan.',
            'lokasi' => $lokasi,
        ], 201);
    }

    /**
     * PUT/PATCH /api/sistem/lokasi/{id}
     * Perbarui data lokasi.
     */
    public function perbaruiLokasi(Request $request, string $id): JsonResponse
    {
        $lokasi = Lokasi::findOrFail($id);

        $data = $request->validate([
            'nama'      => "sometimes|required|string|max:100|unique:lokasi,nama,{$id}",
            'kapasitas' => 'sometimes|required|integer|min:1',
            'fasilitas' => 'nullable|array',
            'fasilitas.*' => 'string|max:100',
            'aktif'     => 'sometimes|required|boolean',
            'sementara' => 'sometimes|required|boolean',
        ]);

        $lokasi->update($data);

        return response()->json([
            'pesan'  => 'Lokasi berhasil diperbarui.',
            'lokasi' => $lokasi->fresh(),
        ]);
    }

    /**
     * PATCH /api/sistem/lokasi/{id}/status
     * Toggle status aktif lokasi.
     */
    public function ubahStatusLokasi(Request $request, string $id): JsonResponse
    {
        $lokasi = Lokasi::findOrFail($id);

        $request->validate([
            'aktif' => 'required|boolean',
        ]);

        $lokasi->update(['aktif' => (bool) $request->input('aktif')]);

        return response()->json([
            'pesan'  => 'Status aktif lokasi berhasil diperbarui.',
            'lokasi' => $lokasi,
        ]);
    }

    /**
     * GET /api/sistem/penugasan
     * Daftar penugasan pengelola gedung.
     */
    public function indexPenugasan(): JsonResponse
    {
        $penugasan = PengelolaLokasi::with([
            'pengguna:id,nama,email,no_hp,aktif',
            'lokasi:id,nama,jenis,kapasitas,aktif,sementara',
        ])->get();

        return response()->json($penugasan);
    }

    /**
     * POST /api/sistem/penugasan
     * Tugaskan pengelola gedung ke lokasi gedung.
     */
    public function simpanPenugasan(Request $request): JsonResponse
    {
        $request->validate([
            'pengguna_id' => 'required|uuid|exists:pengguna,id',
            'lokasi_id'   => 'required|uuid|exists:lokasi,id',
        ]);

        $pengguna = Pengguna::findOrFail($request->input('pengguna_id'));
        $lokasi = Lokasi::findOrFail($request->input('lokasi_id'));

        if ($pengguna->peran !== 'pengelola_gedung') {
            return response()->json([
                'galat' => 'Pengguna harus memiliki peran pengelola_gedung untuk ditugaskan.',
            ], 422);
        }

        if ($lokasi->jenis !== 'gedung') {
            return response()->json([
                'galat' => 'Penugasan pengelola hanya berlaku untuk gedung, bukan ruangan.',
            ], 422);
        }

        $sudahAda = PengelolaLokasi::where('pengguna_id', $pengguna->id)
            ->where('lokasi_id', $lokasi->id)
            ->exists();

        if ($sudahAda) {
            return response()->json([
                'galat' => 'Pengelola sudah ditugaskan ke gedung ini.',
            ], 409);
        }

        $penugasan = PengelolaLokasi::create([
            'pengguna_id'     => $pengguna->id,
            'lokasi_id'       => $lokasi->id,
            'ditugaskan_oleh' => $request->user()->id,
        ]);

        return response()->json([
            'pesan'     => 'Penugasan berhasil disimpan.',
            'penugasan' => $penugasan->load(['pengguna:id,nama,email', 'lokasi:id,nama,jenis']),
        ], 201);
    }

    /**
     * DELETE /api/sistem/penugasan/{id}
     * Cabut penugasan pengelola gedung.
     */
    public function hapusPenugasan(string $id): JsonResponse
    {
        $penugasan = PengelolaLokasi::findOrFail($id);
        $penugasan->delete();

        return response()->json([
            'pesan' => 'Penugasan berhasil dicabut.',
        ]);
    }

    /**
     * GET /api/sistem/gedung-tanpa-pengelola
     * Gedung tanpa pengelola dan jumlah pengajuan menunggu, tanpa detail privat.
     */
    public function gedungTanpaPengelola(): JsonResponse
    {
        $gedungDitugaskan = PengelolaLokasi::pluck('lokasi_id');

        $gedung = Lokasi::where('jenis', 'gedung')
            ->whereNotIn('id', $gedungDitugaskan)
            ->orderBy('nama')
            ->get()
            ->map(function ($lokasi) {
                $jumlahMenunggu = Reservasi::where('lokasi_id', $lokasi->id)
                    ->where('status', 'menunggu')
                    ->count();

                return [
                    'id'               => $lokasi->id,
                    'nama'             => $lokasi->nama,
                    'kapasitas'        => $lokasi->kapasitas,
                    'fasilitas'        => $lokasi->fasilitas,
                    'aktif'            => $lokasi->aktif,
                    'sementara'        => $lokasi->sementara,
                    'pengajuan_menunggu' => $jumlahMenunggu,
                ];
            });

        return response()->json($gedung);
    }

    /**
     * GET /api/sistem/pengaturan
     */
    public function indexPengaturan(): JsonResponse
    {
        $pengaturan = Pengaturan::all()->pluck('nilai', 'kunci');

        return response()->json($pengaturan);
    }

    /**
     * PUT /api/sistem/pengaturan
     */
    public function perbaruiPengaturan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wa_admin_nomor' => 'nullable|string|max:30',
        ]);

        foreach ($data as $kunci => $nilai) {
            Pengaturan::updateOrCreate(
                ['kunci' => $kunci],
                ['nilai' => $nilai]
            );
        }

        return response()->json([
            'pesan'      => 'Pengaturan berhasil diperbarui.',
            'pengaturan' => Pengaturan::all()->pluck('nilai', 'kunci'),
        ]);
    }

    /**
     * GET /api/sistem/log/agent
     */
    public function logAgent(Request $request): JsonResponse
    {
        $logs = LogAgent::orderBy('dibuat_pada', 'desc')->paginate(50);

        return response()->json($logs);
    }

    /**
     * GET /api/sistem/log/akses-dokumen
     */
    public function logAksesDokumen(Request $request): JsonResponse
    {
        $logs = LogAksesDokumen::with(['pengguna:id,nama,email,peran'])
            ->orderBy('dibuat_pada', 'desc')
            ->paginate(50);

        return response()->json($logs);
    }

    /**
     * GET /api/sistem/laporan-fasilitas
     * Semua laporan fasilitas.
     */
    public function indexLaporanFasilitas(Request $request): JsonResponse
    {
        $query = LaporanFasilitas::with(['lokasi:id,nama,jenis', 'pelapor:id,nama,email'])
            ->orderBy('dibuat_pada', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('lokasi_id')) {
            $query->where('lokasi_id', $request->query('lokasi_id'));
        }

        return response()->json($query->paginate(30));
    }

    /**
     * PATCH /api/sistem/laporan-fasilitas/{id}
     * Ubah status laporan untuk ruangan atau tanpa lokasi.
     */
    public function ubahStatusLaporanFasilitas(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status'             => 'required|in:baru,diproses,selesai,ditolak',
            'catatan_penanganan' => 'nullable|string|max:1000',
        ]);

        $laporan = LaporanFasilitas::findOrFail($id);

        $laporan->update([
            'status'             => $request->input('status'),
            'catatan_penanganan' => $request->input('catatan_penanganan', $laporan->catatan_penanganan),
        ]);

        return response()->json([
            'pesan'   => 'Status laporan fasilitas berhasil diperbarui.',
            'laporan' => $laporan->fresh(['lokasi:id,nama,jenis']),
        ]);
    }

    /**
     * GET /api/laporan/ringkasan
     * Ringkasan agregat kampus (tanpa detail privat).
     */
    public function ringkasan(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->peran === 'pengelola_gedung') {
            $gedungIds = PengelolaLokasi::where('pengguna_id', $user->id)->pluck('lokasi_id');

            $totalReservasi = Reservasi::whereIn('lokasi_id', $gedungIds)->count();
            $menunggu = Reservasi::whereIn('lokasi_id', $gedungIds)->where('status', 'menunggu')->count();
            $disetujui = Reservasi::whereIn('lokasi_id', $gedungIds)->where('status', 'disetujui')->count();
            $ditolak = Reservasi::whereIn('lokasi_id', $gedungIds)->where('status', 'ditolak')->count();
            $tergusur = Reservasi::whereIn('lokasi_id', $gedungIds)->where('status', 'tergusur')->count();

            $laporan = LaporanFasilitas::whereIn('lokasi_id', $gedungIds)->count();

            return response()->json([
                'peran'           => 'pengelola_gedung',
                'total_gedung'    => $gedungIds->count(),
                'total_reservasi' => $totalReservasi,
                'status' => [
                    'menunggu'  => $menunggu,
                    'disetujui' => $disetujui,
                    'ditolak'   => $ditolak,
                    'tergusur'  => $tergusur,
                ],
                'laporan_fasilitas' => $laporan,
            ]);
        }

        // pengelola_sistem
        $totalReservasi = Reservasi::count();
        $ruanganCount = Reservasi::whereHas('lokasi', fn ($q) => $q->where('jenis', 'ruangan'))->count();
        $gedungCount = Reservasi::whereHas('lokasi', fn ($q) => $q->where('jenis', 'gedung'))->count();

        $statusCounts = Reservasi::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalLokasi = Lokasi::count();
        $totalPengguna = Pengguna::count();
        $totalLaporan = LaporanFasilitas::count();

        return response()->json([
            'peran'            => 'pengelola_sistem',
            'total_lokasi'     => $totalLokasi,
            'total_pengguna'   => $totalPengguna,
            'total_reservasi'  => $totalReservasi,
            'reservasi_ruangan'=> $ruanganCount,
            'reservasi_gedung' => $gedungCount,
            'status'           => $statusCounts,
            'total_laporan'    => $totalLaporan,
        ]);
    }
}
