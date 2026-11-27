<?php

namespace App\Http\Controllers;

use App\Models\KasusPenggusuran;
use App\Models\Pengaturan;
use App\Models\Reservasi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatAdminController extends Controller
{
    /**
     * GET /api/chat-admin/tautan
     * Buat tautan WhatsApp Bu Eci dengan teks otomatis.
     */
    public function tautan(Request $request): JsonResponse
    {
        $nomor = Pengaturan::where('kunci', 'wa_admin_nomor')->value('nilai');

        if (! $nomor || str_contains($nomor, 'x')) {
            return response()->json([
                'galat'    => 'Kontak admin WhatsApp belum tersedia atau belum dikonfigurasi.',
                'tersedia' => false,
            ], 200);
        }

        $cleanNomor = preg_replace('/[^0-9]/', '', $nomor);
        if (str_starts_with($cleanNomor, '0')) {
            $cleanNomor = '62' . substr($cleanNomor, 1);
        }
        $user = $request->user();
        $nama = $user?->nama ?? 'Pengguna SMARTSPACE';
        $organisasi = $user?->organisasi ? " dari {$user->organisasi}" : '';

        $teks = "Halo Bu Eci, saya {$nama}{$organisasi}.\n";

        if ($request->filled('kasus_id')) {
            $kasus = KasusPenggusuran::with('reservasi.lokasi')->find($request->query('kasus_id'));
            if ($kasus && $kasus->reservasi) {
                $kode = $kasus->reservasi->kode;
                $teks .= "Reservasi saya ({$kode}) mengalami penggusuran karena: {$kasus->alasan_penggusuran}.\nMohon bantuan dan arahannya untuk opsi jadwal pengganti. Terima kasih.";
            } else {
                $teks .= "Saya ingin berkonsultasi mengenai kendala reservasi kampus di SMARTSPACE. Terima kasih.";
            }
        } elseif ($request->filled('reservasi_id')) {
            $reservasi = Reservasi::with('lokasi')->find($request->query('reservasi_id'));
            if ($reservasi) {
                $mulai = Carbon::parse($reservasi->mulai)->translatedFormat('d F Y H:i');
                $teks .= "Saya ingin mengonfirmasi permohonan reservasi gedung {$reservasi->lokasi->nama} dengan kode {$reservasi->kode} untuk tanggal {$mulai}. Terima kasih.";
            } else {
                $teks .= "Saya ingin berkonsultasi mengenai reservasi fasilitas kampus di SMARTSPACE. Terima kasih.";
            }
        } else {
            $teks .= "Saya ingin berkonsultasi mengenai peminjaman gedung dan fasilitas kampus di SMARTSPACE POLINELA. Terima kasih.";
        }

        if (mb_strlen($teks) > 800) {
            $teks = mb_substr($teks, 0, 797) . '...';
        }

        $url = 'https://wa.me/' . $cleanNomor . '?text=' . rawurlencode($teks);

        return response()->json([
            'tersedia' => true,
            'tautan'   => $url,
        ]);
    }
}

