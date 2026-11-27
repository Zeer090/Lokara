<?php

namespace App\Http\Controllers;

use App\Models\AcaraKampus;
use App\Models\Reservasi;
use App\Models\SlotTerkunci;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    /**
     * GET /api/jadwal/kalender?bulan=YYYY-MM&lokasi_id=
     * Kalender publik: HANYA interval terisi.
     * Tidak membocorkan nama pemesan, keperluan acara, atau data privat.
     */
    public function kalenderPublik(Request $request): JsonResponse
    {
        $bulanParam = $request->query('bulan', now()->format('Y-m'));

        try {
            $awalBulan = Carbon::createFromFormat('Y-m', $bulanParam)->startOfMonth();
            $akhirBulan = Carbon::createFromFormat('Y-m', $bulanParam)->endOfMonth();
        } catch (\Throwable) {
            $awalBulan = now()->startOfMonth();
            $akhirBulan = now()->endOfMonth();
        }

        $awalStr = $awalBulan->subDays(7)->toIso8601String();
        $akhirStr = $akhirBulan->addDays(7)->toIso8601String();

        $query = SlotTerkunci::with('lokasi')
            ->whereRaw("rentang && tstzrange(?, ?, '[)')", [$awalStr, $akhirStr]);

        if ($request->filled('lokasi_id')) {
            $query->where('lokasi_id', $request->query('lokasi_id'));
        }

        $slots = $query->get();

        $events = $slots->map(function (SlotTerkunci $s) {
            preg_match('/[\[\(]"?([^",]+)"?,\s*"?([^",\)]+)"?[\]\)]/', $s->rentang, $matches);
            $mulai = $matches[1] ?? null;
            $selesai = $matches[2] ?? null;

            return [
                'id'        => $s->id,
                'lokasi_id' => $s->lokasi_id,
                'lokasi'    => $s->lokasi?->nama,
                'jenis'     => $s->lokasi?->jenis,
                'sumber'    => $s->sumber,
                'title'     => $s->sumber === 'acara_kampus' ? 'Kegiatan Resmi Kampus' : 'Terjadwal / Digunakan',
                'start'     => $mulai ? Carbon::parse($mulai)->toIso8601String() : null,
                'end'       => $selesai ? Carbon::parse($selesai)->toIso8601String() : null,
                'color'     => $s->sumber === 'acara_kampus' ? '#2563eb' : '#16a34a', // biru / hijau
            ];
        });

        return response()->json([
            'bulan'  => $bulanParam,
            'events' => $events,
        ]);
    }
}

