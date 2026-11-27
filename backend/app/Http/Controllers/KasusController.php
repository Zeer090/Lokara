<?php

namespace App\Http\Controllers;

use App\Models\KasusPenggusuran;
use App\Models\Lokasi;
use App\Models\Notifikasi;
use App\Models\Pengaturan;
use App\Models\PengelolaLokasi;
use App\Models\Pengguna;
use App\Models\PesanNegosiasi;
use App\Models\Reservasi;
use App\Models\SlotTerkunci;
use App\Models\UsulanAgent;
use App\Services\AgenPenjadwalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KasusController extends Controller
{
    /**
     * GET /api/kasus/saya
     * Daftar kasus penggusuran milik pengguna yang login.
     */
    public function saya(Request $request): JsonResponse
    {
        $user = $request->user();

        $kasus = KasusPenggusuran::with([
            'reservasi.lokasi:id,nama,jenis',
            'acaraKampus.lokasi:id,nama,jenis',
        ])
            ->whereHas('reservasi', fn ($q) => $q->where('pengguna_id', $user->id))
            ->orderBy('dibuat_pada', 'desc')
            ->get();

        return response()->json($kasus);
    }

    /**
     * GET /api/kasus/{id}
     * Detail kasus penggusuran.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $kasus = KasusPenggusuran::with([
            'reservasi.lokasi:id,nama,jenis,kapasitas',
            'acaraKampus.lokasi:id,nama,jenis',
            'pesanNegosiasi' => fn ($q) => $q->orderBy('dibuat_pada', 'asc'),
            'usulanAgent'    => fn ($q) => $q->with('lokasi:id,nama,jenis,kapasitas')->orderBy('dibuat_pada', 'asc'),
        ])->findOrFail($id);

        // Hanya pemilik reservasi atau pengelola gedung berwenang yang boleh melihat detail kasus
        $diizinkan = false;
        if ($user->peran === 'pengguna' && $kasus->reservasi->pengguna_id === $user->id) {
            $diizinkan = true;
        } elseif ($user->peran === 'pengelola_gedung') {
            $diizinkan = PengelolaLokasi::where('pengguna_id', $user->id)
                ->where('lokasi_id', $kasus->reservasi->lokasi_id)
                ->exists();
        }

        if (! $diizinkan) {
            return response()->json([
                'galat' => 'Anda tidak memiliki hak akses terhadap kasus penggusuran ini.',
            ], 403);
        }

        $tautanWa = $this->bangunTautanWa($kasus);

        return response()->json([
            'kasus'     => $kasus,
            'tautan_wa' => $tautanWa,
        ]);
    }

    /**
     * Alias untuk route POST /api/kasus/{id}/pesan
     */
    public function pesan(Request $request, string $id): JsonResponse
    {
        return $this->kirimPesan($request, $id);
    }

    /**
     * POST /api/kasus/{id}/pesan
     * Kirim pesan dalam negosiasi kasus.
     */
    public function kirimPesan(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'pesan' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        $kasus = KasusPenggusuran::with('reservasi')->findOrFail($id);

        if ($kasus->reservasi->pengguna_id !== $user->id) {
            return response()->json([
                'galat' => 'Hanya pemilik kasus yang dapat mengirim pesan negosiasi.',
            ], 403);
        }

        if (in_array($kasus->status, ['selesai', 'diserahkan'], true)) {
            return response()->json([
                'galat' => 'Kasus ini telah selesai atau diserahkan ke pengelola. Silakan gunakan kontak WhatsApp Bu Eci.',
            ], 422);
        }

        $pesanPengguna = PesanNegosiasi::create([
            'kasus_id'    => $kasus->id,
            'pengirim'    => 'pengguna',
            'isi'         => trim($request->input('pesan')),
            'dibuat_pada' => now(),
        ]);

        // Jalankan giliran AI Agent Penjadwal Ulang (AgenPenjadwalService)
        $service = app(AgenPenjadwalService::class);
        $hasilAgent = $service->prosesGiliran($kasus, $pesanPengguna->isi);

        return response()->json([
            'pesan_pengguna' => $pesanPengguna,
            'tanggapan'      => $hasilAgent['tanggapan'],
            'usulan_baru'    => $hasilAgent['usulan_baru'],
            'tautan_wa'      => $this->bangunTautanWa($kasus->fresh()),
        ]);
    }

    /**
     * POST /api/kasus/{id}/pilih-usulan
     * Pengguna memutuskan pilihan usulan alternatif.
     */
    public function pilihUsulan(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'usulan_id'       => 'required|uuid|exists:usulan_agent,id',
            'alasan_pilihan'  => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $kasus = KasusPenggusuran::with('reservasi')->findOrFail($id);

        if ($kasus->reservasi->pengguna_id !== $user->id) {
            return response()->json([
                'galat' => 'Anda bukan pemilik kasus ini.',
            ], 403);
        }

        $usulan = UsulanAgent::where('kasus_id', $kasus->id)
            ->where('id', $request->input('usulan_id'))
            ->firstOrFail();

        // Cek bentrok ulang dengan slot terkunci
        $adaBentrok = SlotTerkunci::where('lokasi_id', $usulan->lokasi_id)
            ->whereRaw('rentang && tstzrange(?, ?, \'[)\')', [
                $usulan->mulai->toIso8601String(),
                $usulan->selesai->toIso8601String(),
            ])
            ->exists();

        if ($adaBentrok) {
            return response()->json([
                'galat' => 'Slot usulan yang dipilih saat ini sudah terisi oleh jadwal lain. Silakan pilih alternatif lainnya.',
            ], 409);
        }

        DB::transaction(function () use ($kasus, $usulan, $request) {
            // Tandai usulan terpilih
            $usulan->update(['status' => 'dipilih']);

            // Simpan catatan keputusan
            $kasus->update([
                'status'            => 'diputuskan',
                'catatan_keputusan' => [
                    'usulan_id'       => $usulan->id,
                    'lokasi_id'       => $usulan->lokasi_id,
                    'lokasi_nama'     => $usulan->lokasi?->nama,
                    'mulai'           => $usulan->mulai->toIso8601String(),
                    'selesai'         => $usulan->selesai->toIso8601String(),
                    'alasan_pilihan'  => $request->input('alasan_pilihan'),
                    'diputuskan_pada' => now()->toIso8601String(),
                ],
            ]);
        });

        return response()->json([
            'pesan'     => 'Usulan berhasil dipilih. Anda dapat mengajukan ulang reservasi atau menghubungi Bu Eci untuk ACC.',
            'kasus'     => $kasus->fresh(['usulanAgent']),
            'tautan_wa' => $this->bangunTautanWa($kasus->fresh()),
        ]);
    }

    /**
     * POST /api/kasus/{id}/ajukan-ulang
     * Ajukan reservasi pengganti menggunakan surat lama tanpa perlu unggah ulang (6.6).
     */
    public function ajukanUlang(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $kasus = KasusPenggusuran::with(['reservasi.lokasi'])->findOrFail($id);

        if ($kasus->reservasi->pengguna_id !== $user->id) {
            return response()->json([
                'galat' => 'Anda bukan pemilik kasus ini.',
            ], 403);
        }

        if ($kasus->status !== 'diputuskan') {
            return response()->json([
                'galat' => 'Kasus harus berstatus diputuskan sebelum dapat diajukan ulang.',
            ], 422);
        }

        $catatan = $kasus->catatan_keputusan;
        if (empty($catatan) || empty($catatan['lokasi_id']) || empty($catatan['mulai']) || empty($catatan['selesai'])) {
            return response()->json([
                'galat' => 'Data jadwal pengganti tidak lengkap pada catatan keputusan.',
            ], 422);
        }

        $lokasiId = $catatan['lokasi_id'];
        $mulai = Carbon::parse($catatan['mulai']);
        $selesai = Carbon::parse($catatan['selesai']);

        // Cek bentrok slot
        $adaBentrok = SlotTerkunci::where('lokasi_id', $lokasiId)
            ->whereRaw('rentang && tstzrange(?, ?, \'[)\')', [
                $mulai->toIso8601String(),
                $selesai->toIso8601String(),
            ])
            ->exists();

        if ($adaBentrok) {
            return response()->json([
                'galat' => 'Jadwal pengganti bentrok dengan slot terkunci lain.',
            ], 409);
        }

        $reservasiAsal = $kasus->reservasi;

        $reservasiBaru = DB::transaction(function () use ($user, $reservasiAsal, $kasus, $lokasiId, $mulai, $selesai) {
            $kode = 'RES-' . strtoupper(Str::random(8));

            $baru = Reservasi::create([
                'kode'              => $kode,
                'pengguna_id'       => $user->id,
                'lokasi_id'         => $lokasiId,
                'surat_id'          => $reservasiAsal->surat_id, // Gunakan surat lama tanpa minta unggah lagi!
                'reservasi_asal_id' => $reservasiAsal->id,
                'keperluan'         => "Pengganti reservasi {$reservasiAsal->kode}: " . $reservasiAsal->keperluan,
                'kebutuhan'         => $reservasiAsal->kebutuhan,
                'jumlah_peserta'    => $reservasiAsal->jumlah_peserta,
                'mulai'             => $mulai,
                'selesai'           => $selesai,
                'jenis_penggunaan'  => 'event_kegiatan',
                'alur_persetujuan'  => 'manual',
                'status'            => 'menunggu',
            ]);

            // Ubah status kasus menjadi 'diajukan_ulang'
            $kasus->update(['status' => 'diajukan_ulang']);

            // Beritahu pengelola gedung tujuan
            $pengelolaIds = PengelolaLokasi::where('lokasi_id', $lokasiId)->pluck('pengguna_id');
            if ($pengelolaIds->isNotEmpty()) {
                foreach ($pengelolaIds as $pengelolaId) {
                    Notifikasi::create([
                        'pengguna_id'  => $pengelolaId,
                        'judul'        => 'Pengajuan Ulang Reservasi Pengganti',
                        'isi'          => "Reservasi pengganti {$baru->kode} (dari kasus {$reservasiAsal->kode}) membutuhkan persetujuan Anda.",
                        'tautan'       => "/pengelola/reservasi/{$baru->id}",
                    ]);
                }
            } else {
                // Jika gedung belum punya pengelola, notifikasi ke pengelola sistem
                $sistemIds = Pengguna::where('peran', 'pengelola_sistem')->where('aktif', true)->pluck('id');
                foreach ($sistemIds as $sistemId) {
                    Notifikasi::create([
                        'pengguna_id'  => $sistemId,
                        'judul'        => 'Reservasi pada Gedung Tanpa Pengelola',
                        'isi'          => "Reservasi pengganti {$baru->kode} masuk ke gedung yang belum memiliki pengelola.",
                        'tautan'       => '/sistem/gedung-tanpa-pengelola',
                    ]);
                }
            }

            return $baru;
        });

        return response()->json([
            'pesan'          => 'Reservasi pengganti berhasil diajukan dengan surat lama dan sedang menunggu persetujuan pengelola gedung.',
            'reservasi_baru' => $reservasiBaru->load('lokasi:id,nama,jenis'),
            'kasus'          => $kasus->fresh(),
        ], 201);
    }

    /**
     * GET /api/kasus/{id}/tautan-wa
     * Dapatkan tautan WhatsApp Bu Eci dengan teks otomatis.
     */
    public function tautanWa(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $kasus = KasusPenggusuran::with('reservasi')->findOrFail($id);

        if ($kasus->reservasi->pengguna_id !== $user->id) {
            return response()->json([
                'galat' => 'Anda bukan pemilik kasus ini.',
            ], 403);
        }

        $tautan = $this->bangunTautanWa($kasus);

        return response()->json([
            'tautan'   => $tautan,
            'tersedia' => ! empty($tautan),
        ]);
    }

    /**
     * Format tautan WA Bu Eci sesuai spesifikasi Bagian 8:
     * Halo Bu Eci, saya [nama] dari [organisasi].
     * Reservasi [kode] tergusur. Saya sudah memilih pengganti:
     * [lokasi], [tanggal], [jam].
     * Alasan pilihan: [alasan_pilihan].
     * Mohon ACC-nya. Terima kasih.
     */
    protected function bangunTautanWa(KasusPenggusuran $kasus): ?string
    {
        $nomor = Pengaturan::where('kunci', 'wa_admin_nomor')->value('nilai');

        if (! $nomor || str_contains($nomor, 'x')) {
            return null;
        }

        $reservasi = $kasus->reservasi;
        $pemilik = $reservasi?->pengguna;
        $catatan = $kasus->catatan_keputusan;

        $nama = $pemilik?->nama ?? 'Pengguna SMARTSPACE';
        $organisasi = $pemilik?->organisasi ? " dari {$pemilik->organisasi}" : '';
        $kode = $reservasi?->kode ?? '-';

        $penggantiTeks = "Belum memilih jadwal pengganti.";
        if (! empty($catatan) && ! empty($catatan['mulai'])) {
            $mulai = Carbon::parse($catatan['mulai'])->translatedFormat('d F Y, H:i');
            $selesai = Carbon::parse($catatan['selesai'])->translatedFormat('H:i WIB');
            $lokasiNama = $catatan['lokasi_nama'] ?? 'Gedung Pengganti';
            $alasan = $catatan['alasan_pilihan'] ?? 'Jadwal dan fasilitas sesuai kebutuhan';

            $penggantiTeks = "Saya sudah memilih pengganti:\n{$lokasiNama}, {$mulai} - {$selesai}.\nAlasan pilihan: {$alasan}.";
        }

        $pesan = "Halo Bu Eci, saya {$nama}{$organisasi}.\n"
            . "Reservasi {$kode} tergusur karena: {$kasus->alasan_penggusuran}.\n"
            . "{$penggantiTeks}\n"
            . "Mohon arahannya / ACC-nya. Terima kasih.";

        // Batasi panjang agar URL tidak terpotong
        if (mb_strlen($pesan) > 800) {
            $pesan = mb_substr($pesan, 0, 797) . '...';
        }

        $cleanNomor = preg_replace('/[^0-9]/', '', $nomor);
        if (str_starts_with($cleanNomor, '0')) {
            $cleanNomor = '62' . substr($cleanNomor, 1);
        }

        return 'https://wa.me/' . $cleanNomor . '?text=' . rawurlencode($pesan);
    }
}
