<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicSchedule;
use App\Models\SlotTerkunci;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    /**
     * GET /api/jadwal/kalender?bulan=YYYY-MM&lokasi_id=
     * Kalender publik: menggabungkan slot_terkunci (reservasi/acara)
     * dengan jadwal kuliah aktif (academic_schedules) agar langsung terintegrasi.
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

        $awalRange = $awalBulan->copy()->subDays(7);
        $akhirRange = $akhirBulan->copy()->addDays(7);

        // 1. Ambil SlotTerkunci (reservasi & acara kampus)
        $query = SlotTerkunci::with('lokasi')
            ->whereRaw("rentang && tstzrange(?, ?, '[)')", [
                $awalRange->toIso8601String(),
                $akhirRange->toIso8601String(),
            ]);

        if ($request->filled('lokasi_id')) {
            $query->where('lokasi_id', $request->query('lokasi_id'));
        }

        $events = $query->get()->map(function (SlotTerkunci $s) {
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
                'color'     => $s->sumber === 'acara_kampus' ? '#2563eb' : '#16a34a',
            ];
        })->values()->all();

        // 2. Proyeksikan AcademicSchedule aktif pada periode yang beririsan dengan bulan ini
        $periodeAktifIds = AcademicPeriod::where('tanggal_mulai', '<=', $akhirRange->toDateString())
            ->where('tanggal_selesai', '>=', $awalRange->toDateString())
            ->pluck('id');

        if ($periodeAktifIds->isNotEmpty()) {
            $jadwalQuery = AcademicSchedule::with('lokasi')
                ->whereIn('academic_period_id', $periodeAktifIds)
                ->where('aktif', true);

            if ($request->filled('lokasi_id')) {
                $jadwalQuery->where('lokasi_id', $request->query('lokasi_id'));
            }

            $jadwalList = $jadwalQuery->get();
            $dayMap = [
                Carbon::MONDAY    => 'senin',
                Carbon::TUESDAY   => 'selasa',
                Carbon::WEDNESDAY => 'rabu',
                Carbon::THURSDAY  => 'kamis',
                Carbon::FRIDAY    => 'jumat',
                Carbon::SATURDAY  => 'sabtu',
                Carbon::SUNDAY    => 'minggu',
            ];

            $cursor = $awalBulan->copy();
            while ($cursor->lte($akhirBulan)) {
                $hariIni = $dayMap[$cursor->dayOfWeek] ?? '';
                $tglStr  = $cursor->toDateString();

                foreach ($jadwalList as $j) {
                    if ($j->hari !== $hariIni) {
                        continue;
                    }
                    $startDt = Carbon::parse("{$tglStr} {$j->jam_mulai}", 'Asia/Jakarta');
                    $endDt   = Carbon::parse("{$tglStr} {$j->jam_selesai}", 'Asia/Jakarta');

                    $events[] = [
                        'id'        => "kuliah_{$j->id}_{$tglStr}",
                        'lokasi_id' => $j->lokasi_id,
                        'lokasi'    => $j->lokasi?->nama,
                        'jenis'     => $j->lokasi?->jenis ?? 'ruangan',
                        'sumber'    => 'jadwal_kuliah',
                        'title'     => "Kuliah: {$j->nama_mata_kuliah} ({$j->program_studi})",
                        'start'     => $startDt->toIso8601String(),
                        'end'       => $endDt->toIso8601String(),
                        'color'     => '#0d9488', // teal untuk jadwal kuliah
                        'detail'    => [
                            'mata_kuliah'   => $j->nama_mata_kuliah,
                            'kode_mk'       => $j->kode_mk,
                            'kelas'         => $j->kelas,
                            'program_studi' => $j->program_studi,
                            'nama_dosen'    => $j->nama_dosen,
                            'hari'          => ucfirst($j->hari),
                            'jam'           => "{$j->jam_mulai} - {$j->jam_selesai} WIB",
                        ],
                    ];
                }

                $cursor->addDay();
            }
        }

        return response()->json([
            'bulan'  => $bulanParam,
            'events' => $events,
        ]);
    }
}
