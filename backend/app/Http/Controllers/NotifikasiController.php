<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    /**
     * GET /api/notifikasi
     * Daftar notifikasi milik pengguna yang sedang masuk.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifikasi = Notifikasi::where('pengguna_id', $user->id)
            ->orderBy('dibuat_pada', 'desc')
            ->paginate(30);

        $belumDibaca = Notifikasi::where('pengguna_id', $user->id)
            ->where('sudah_dibaca', false)
            ->count();

        return response()->json([
            'belum_dibaca' => $belumDibaca,
            'data'         => $notifikasi,
        ]);
    }

    /**
     * POST /api/notifikasi/{id}/baca
     * Tandai sebuah notifikasi telah dibaca.
     */
    public function tandaiDibaca(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notifikasi = Notifikasi::where('pengguna_id', $user->id)->findOrFail($id);
        $notifikasi->update(['sudah_dibaca' => true]);

        return response()->json([
            'pesan'      => 'Notifikasi ditandai dibaca.',
            'notifikasi' => $notifikasi,
        ]);
    }

    /**
     * POST /api/notifikasi/baca-semua
     * Tandai seluruh notifikasi belum dibaca milik pengguna menjadi sudah dibaca.
     */
    public function tandaiSemuaDibaca(Request $request): JsonResponse
    {
        $user = $request->user();

        Notifikasi::where('pengguna_id', $user->id)
            ->where('sudah_dibaca', false)
            ->update(['sudah_dibaca' => true]);

        return response()->json([
            'pesan' => 'Semua notifikasi telah ditandai dibaca.',
        ]);
    }
}
