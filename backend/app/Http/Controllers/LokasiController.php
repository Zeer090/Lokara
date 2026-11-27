<?php

namespace App\Http\Controllers;

use App\Models\Lokasi;
use App\Models\SlotTerkunci;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LokasiController extends Controller
{
    /**
     * GET /api/lokasi
     * Daftar lokasi publik/terotentikasi (filter jenis, kapasitas, fasilitas, pencarian).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lokasi::query();

        // Tamu dan pengguna hanya melihat lokasi aktif
        if (! $request->user() || $request->user()->peran !== 'pengelola_sistem') {
            $query->where('aktif', true);
        }

        if ($request->has('jenis')) {
            $query->where('jenis', $request->query('jenis'));
        }

        if ($request->has('kapasitas_min')) {
            $query->where('kapasitas', '>=', (int) $request->query('kapasitas_min'));
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('nama', 'ilike', "%{$q}%")
                    ->orWhere('keterangan', 'ilike', "%{$q}%");
            });
        }

        $daftar = $query->orderBy('jenis')
            ->orderBy('nama')
            ->get();

        return response()->json([
            'data' => $daftar->map(function (Lokasi $l) {
                return [
                    'id'         => $l->id,
                    'nama'       => $l->nama,
                    'jenis'      => $l->jenis,
                    'kapasitas'  => $l->kapasitas,
                    'fasilitas'  => $l->fasilitas ?? [],
                    'keterangan' => $l->keterangan,
                    'sementara'  => $l->sementara,
                    'aktif'      => $l->aktif,
                ];
            }),
        ]);
    }

    /**
     * GET /api/lokasi/:id
     * Detail satu lokasi.
     */
    public function show(string $id): JsonResponse
    {
        if (! \Illuminate\Support\Str::isUuid($id)) {
            return response()->json(['galat' => 'Lokasi tidak ditemukan.'], 404);
        }
        try {
            $lokasi = Lokasi::find($id);
        } catch (\Exception $e) {
            // Likely invalid UUID causing DB error
            return response()->json(['galat' => 'Lokasi tidak ditemukan.'], 404);
        }

        if (! $lokasi) {
            return response()->json(['galat' => 'Lokasi tidak ditemukan.'], 404);
        }

        return response()->json([
            'lokasi' => [
                'id'         => $lokasi->id,
                'nama'       => $lokasi->nama,
                'jenis'      => $lokasi->jenis,
                'kapasitas'  => $lokasi->kapasitas,
                'fasilitas'  => $lokasi->fasilitas ?? [],
                'keterangan' => $lokasi->keterangan,
                'sementara'  => $lokasi->sementara,
                'aktif'      => $lokasi->aktif,
            ],
        ]);
    }

    /**
     * GET /api/lokasi/:id/jadwal?dari=&sampai=
     * Slot terkunci pada lokasi tertentu dalam rentang tanggal.
     */
    public function jadwal(Request $request, string $id): JsonResponse
    {
        Log::info('jadwal called', ['id' => $id, 'dari' => $request->query('dari'), 'sampai' => $request->query('sampai')]);
        if (! \Illuminate\Support\Str::isUuid($id)) {
            return response()->json(['galat' => 'Lokasi tidak ditemukan.'], 404);
        }
        try {
            $lokasi = Lokasi::find($id);
        } catch (\Exception $e) {
            // Likely invalid UUID causing DB error
            return response()->json(['galat' => 'Lokasi tidak ditemukan.'], 404);
        }
        if (! $lokasi) {
            return response()->json(['galat' => 'Lokasi tidak ditemukan.'], 404);
        }

        // Retrieve query parameters
        $dariParam = $request->query('dari');
        $sampaiParam = $request->query('sampai');

        try {
            if ($dariParam && $sampaiParam) {
                $dari = Carbon::parse($dariParam);
                $sampai = Carbon::parse($sampaiParam);
            } else {
                // Defaults when missing parameters
                $dari = $dariParam ? Carbon::parse($dariParam) : now()->startOfDay();
                $sampai = $sampaiParam ? Carbon::parse($sampaiParam) : now()->addDays(30)->endOfDay();

            }
        } catch (\Exception $e) {
            return response()->json(['galat' => 'Tanggal tidak valid.'], 400);
        }

        // Ensure start date is not after end date
        if ($dari->greaterThan($sampai)) {
            return response()->json(['galat' => 'Tanggal mulai tidak boleh melebihi tanggal selesai.'], 400);
        }
        // Enforce maximum 62‑day range
        if ($dari->diffInDays($sampai) > 62) {
            return response()->json(['galat' => 'Rentang kueri jadwal maksimal 62 hari.'], 400);
        }

        Log::info('dates parsed', [
            'dari' => $dari->toDateTimeString(),
            'sampai' => $sampai->toDateTimeString(),
            'diff' => $sampai->diffInDays($dari),
        ]);

        if ($dari->diffInDays($sampai) > 62) {
            return response()->json(['galat' => 'Rentang kueri jadwal maksimal 62 hari.'], 400);
        }

        $dariStr = $dari->toIso8601String();
        $sampaiStr = $sampai->toIso8601String();

        $slots = SlotTerkunci::where('lokasi_id', $lokasi->id)
            ->whereRaw("rentang && tstzrange(?, ?, '[)')", [$dariStr, $sampaiStr])
            ->get();

        return response()->json([
            'lokasi_id' => $lokasi->id,
            'slot_terkunci' => $slots->map(function (SlotTerkunci $s) {
                // Parse PostgreSQL tstzrange string: ["2026-11-08 09:00:00+07","2026-11-08 11:00:00+07")
                preg_match('/[\[\(]\"?([^\",]+)\"?,\s*\"?([^\",\)]+)\"?[\]\)]/', $s->rentang, $matches);
                $mulai = $matches[1] ?? null;
                $selesai = $matches[2] ?? null;

                return [
                    'id' => $s->id,
                    'sumber' => $s->sumber,
                    'mulai' => $mulai ? Carbon::parse($mulai)->toIso8601String() : null,
                    'selesai' => $selesai ? Carbon::parse($selesai)->toIso8601String() : null,
                ];
            }),
        ]);
    }
}
