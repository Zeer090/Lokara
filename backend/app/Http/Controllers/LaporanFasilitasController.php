<?php

namespace App\Http\Controllers;

use App\Models\LaporanFasilitas;
use App\Models\Lokasi;
use App\Models\PengelolaLokasi;
use App\Services\IntegrasiFasilitasAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaporanFasilitasController extends Controller
{
    public function __construct(
        protected IntegrasiFasilitasAiService $integrasiAi
    ) {}

    /**
     * POST /api/laporan-fasilitas
     * Kirim laporan kerusakan fasilitas (dengan foto kamera opsional) setelah dikonfirmasi pengguna (6.10).
     * Terbuka untuk umum (guest) maupun pengguna terdaftar.
     */
    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lokasi_id'  => 'nullable|uuid|exists:lokasi,id',
            'ringkasan'  => 'required|string|max:200',
            'isi'        => 'required|string|max:2000',
            'konfirmasi' => 'required',
            'anonim'     => 'nullable',
            'foto'       => 'nullable|image|max:5120',
        ]);

        if (! $request->boolean('konfirmasi')) {
            return response()->json([
                'galat' => 'Laporan harus dikonfirmasi terlebih dahulu sebelum disimpan ke sistem.',
            ], 422);
        }

        $user = $request->user();
        $isAnonim = $request->boolean('anonim');
        $pelaporId = ($user && ! $isAnonim) ? $user->id : null;

        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('laporan_fasilitas', 'public');
        }

        $laporan = LaporanFasilitas::create([
            'lokasi_id'          => $data['lokasi_id'] ?? null,
            'pelapor_id'         => $pelaporId,
            'ringkasan'          => trim($data['ringkasan']),
            'isi'                => trim($data['isi']),
            'foto_path'          => $fotoPath,
            'status'             => 'baru',
            'dikonfirmasi_pada'  => now(),
        ]);

        // Jalankan AI Agent untuk menganalisis foto/keterangan & memperbarui deskripsi fasilitas lokasi
        $analisisAi = $this->integrasiAi->prosesLaporan($laporan);

        return response()->json([
            'pesan'       => 'Laporan kerusakan fasilitas berhasil dikirim dan status fasilitas lokasi telah diperbarui oleh AI.',
            'laporan'     => $laporan->fresh(['lokasi:id,nama,jenis,fasilitas']),
            'analisis_ai' => $analisisAi,
        ], 201);
    }

    /**
     * GET /api/pengelola/laporan-fasilitas
     * Daftar laporan fasilitas gedung yang dikelola oleh pengelola gedung.
     */
    public function indexPengelola(Request $request): JsonResponse
    {
        $user = $request->user();
        $gedungIds = PengelolaLokasi::where('pengguna_id', $user->id)->pluck('lokasi_id');

        $query = LaporanFasilitas::with(['lokasi:id,nama,jenis', 'pelapor:id,nama,email'])
            ->whereIn('lokasi_id', $gedungIds)
            ->orderBy('dibuat_pada', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(25));
    }

    /**
     * PATCH /api/pengelola/laporan-fasilitas/{id}
     * Ubah status laporan gedung oleh pengelola gedung yang berwenang.
     */
    public function updatePengelola(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $gedungIds = PengelolaLokasi::where('pengguna_id', $user->id)->pluck('lokasi_id');

        $laporan = LaporanFasilitas::whereIn('lokasi_id', $gedungIds)->findOrFail($id);

        $request->validate([
            'status'             => 'required|in:baru,diproses,selesai,ditolak',
            'catatan_penanganan' => 'nullable|string|max:1000',
        ]);

        $laporan->update([
            'status'             => $request->input('status'),
            'catatan_penanganan' => $request->input('catatan_penanganan', $laporan->catatan_penanganan),
        ]);

        if (in_array($request->input('status'), ['selesai', 'ditolak'], true)) {
            $this->integrasiAi->pulihkanFasilitasSetelahSelesai($laporan);
        }

        return response()->json([
            'pesan'   => 'Status laporan fasilitas gedung berhasil diperbarui.',
            'laporan' => $laporan->fresh(['lokasi:id,nama,jenis,fasilitas']),
        ]);
    }
}
