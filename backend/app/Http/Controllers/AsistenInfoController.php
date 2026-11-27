<?php

namespace App\Http\Controllers;

use App\Models\Lokasi;
use App\Models\PercakapanInfo;
use App\Models\SlotTerkunci;
use App\Services\LayananLlmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AsistenInfoController extends Controller
{
    public function __construct(
        protected LayananLlmService $llm
    ) {}

    /**
     * POST /api/asisten-info/pesan
     * Asisten Informasi Publik kampus POLINELA (tanpa hak tulis ke reservasi).
     * Mendukung model AI gratis (Gemini / Groq / OpenRouter) + fallback lokal cerdas.
     */
    public function pesan(Request $request): JsonResponse
    {
        $request->validate([
            'pesan' => 'required|string|max:1000',
            'sesi'  => 'nullable|string|max:64',
        ]);

        $pesan = trim($request->input('pesan'));
        $sesi  = $request->input('sesi') ?: (string) Str::uuid();
        $user  = $request->user();

        // 1. Ambil riwayat singkat sesi ini (maks 6 pesan terakhir) sebelum simpan pesan baru
        $riwayat = PercakapanInfo::where('sesi', $sesi)
            ->orderBy('dibuat_pada', 'desc')
            ->limit(6)
            ->get()
            ->reverse()
            ->values();

        // 2. Simpan pesan pengguna ke percakapan_info
        PercakapanInfo::create([
            'pengguna_id' => $user?->id,
            'sesi'        => $sesi,
            'peran'       => 'pengguna',
            'isi'         => $pesan,
            'layanan'     => 'asisten_info',
        ]);

        // 3. Deteksi laporan kerusakan fasilitas (selalu butuh konfirmasi pengguna, tidak langsung tulis)
        $lowerPesan = mb_strtolower($pesan);
        $lokasiSemua = Lokasi::where('aktif', true)->orderBy('jenis')->orderBy('nama')->get();

        $usulanLaporan = $this->deteksiLaporanKerusakan($lowerPesan, $pesan, $lokasiSemua);
        $jawaban = null;

        if ($usulanLaporan !== null) {
            $jawaban = "Laporan terdeteksi untuk **{$usulanLaporan['lokasi_nama']}** ({$usulanLaporan['kategori']}).\n"
                . "Klik **Konfirmasi Kirim Laporan** di kartu bawah agar diteruskan ke pengelola.";
        } else {
            // 4. Coba jawab via LLM Gratis dengan konteks DB real-time
            if ($this->llm->tersedia()) {
                $jawaban = $this->jawabDenganLlm($pesan, $riwayat, $lokasiSemua);
            }

            // 5. Fallback lokal cerdas & ringkas jika API key belum diisi atau gagal
            if (!$jawaban) {
                $jawaban = $this->jawabLokalCerdas($lowerPesan, $lokasiSemua);
            }
        }

        // 6. Simpan jawaban asisten ke percakapan_info
        PercakapanInfo::create([
            'pengguna_id' => $user?->id,
            'sesi'        => $sesi,
            'peran'       => 'asisten',
            'isi'         => $jawaban,
            'layanan'     => 'asisten_info',
        ]);

        return response()->json([
            'sesi'           => $sesi,
            'jawaban'        => $jawaban,
            'usulan_laporan' => $usulanLaporan,
            'mode_ai'        => $this->llm->tentukanProvider() ?? 'lokal',
        ]);
    }

    /**
     * Deteksi keluhan / laporan kerusakan fasilitas.
     */
    protected function deteksiLaporanKerusakan(string $lowerPesan, string $pesanAsli, $lokasiSemua): ?array
    {
        $kataKerusakan = ['rusak', 'mati', 'tidak menyala', 'bocor', 'pecah', 'kotor', 'macet', 'kursi patah', 'lapor kerusakan'];
        $isLaporan = false;
        foreach ($kataKerusakan as $kata) {
            if (str_contains($lowerPesan, $kata)) {
                $isLaporan = true;
                break;
            }
        }

        if (!$isLaporan) {
            return null;
        }

        $lokasiDitemukan = null;
        foreach ($lokasiSemua as $lok) {
            if (str_contains($lowerPesan, mb_strtolower($lok->nama))) {
                $lokasiDitemukan = $lok;
                break;
            }
        }

        $kategori = 'fasilitas_rusak';
        if (str_contains($lowerPesan, 'ac') || str_contains($lowerPesan, 'proyektor') || str_contains($lowerPesan, 'sound') || str_contains($lowerPesan, 'alat')) {
            $kategori = 'kerusakan_alat';
        } elseif (str_contains($lowerPesan, 'kotor') || str_contains($lowerPesan, 'sampah') || str_contains($lowerPesan, 'bau')) {
            $kategori = 'kebersihan';
        }

        return [
            'lokasi_id'           => $lokasiDitemukan?->id,
            'lokasi_nama'         => $lokasiDitemukan?->nama ?? 'Lokasi belum spesifik',
            'kategori'            => $kategori,
            'deskripsi_kerusakan' => $pesanAsli,
        ];
    }

    /**
     * Memanggil model AI gratis (Gemini/Groq/OpenRouter) dengan konteks lokasi & jadwal real-time.
     */
    protected function jawabDenganLlm(string $pesan, $riwayat, $lokasiSemua): ?string
    {
        $sekarang = Carbon::now('Asia/Jakarta');
        $batas    = $sekarang->copy()->addDays(7);

        // Ringkas daftar lokasi
        $ringkasLokasi = $lokasiSemua->map(function ($l) {
            $fas = $this->formatFasilitasRingkas($l->fasilitas);
            return "- {$l->nama} ({$l->jenis}, kap: {$l->kapasitas}" . ($fas ? ", {$fas}" : '') . ")";
        })->implode("\n");

        // Ringkas slot terkunci 7 hari ke depan (maks 25 slot agar hemat token)
        $slotTerisi = SlotTerkunci::with('lokasi:id,nama')
            ->selectRaw('lokasi_id, sumber, lower(rentang) as mulai, upper(rentang) as selesai')
            ->whereRaw('rentang && tstzrange(?, ?, \'[)\')', [$sekarang->toIso8601String(), $batas->toIso8601String()])
            ->orderByRaw('lower(rentang) asc')
            ->limit(25)
            ->get();

        $ringkasJadwal = $slotTerisi->isEmpty()
            ? 'Belum ada jadwal terisi dalam 7 hari ke depan.'
            : $slotTerisi->map(function ($s) {
                $nama = $s->lokasi?->nama ?? 'Lokasi';
                $m = Carbon::parse($s->mulai)->timezone('Asia/Jakarta')->format('d/m H:i');
                $e = Carbon::parse($s->selesai)->timezone('Asia/Jakarta')->format('H:i');
                return "- {$nama}: {$m}-{$e} ({$s->sumber})";
            })->implode("\n");

        $systemPrompt = <<<PROMPT
Kamu adalah Asisten Informasi SMARTSPACE AI (sistem peminjaman gedung & ruangan kampus POLINELA).
Waktu saat ini: {$sekarang->locale('id')->translatedFormat('l, d F Y H:i')} WIB.

ATURAN JAWABAN (WAJIB DIPATUHI):
1. Jawab SINGKAT, PADAT, dan LANGSUNG KE INTI (maksimal 3-4 kalimat atau 4 poin singkat). Hindari basa-basi panjang.
2. Gunakan Bahasa Indonesia yang sopan dan jelas.
3. Hanya gunakan data lokasi & jadwal di bawah ini. Jangan mengarang nama gedung, kapasitas, atau jadwal kosong yang tidak ada di data.
4. Aturan baku sistem:
   - Ruangan (kelas/lab/rapat): persetujuan otomatis instan jika tidak bentrok, DILARANG pakai surat.
   - Gedung (GSG/Aula/Gedung pertemuan): wajib surat PDF maks 5 MB, diverifikasi manual oleh Bu Eci (Pengelola Gedung, WA 0895336819200).
   - Asisten Informasi hanya memberi info, TIDAK bisa menyetujui/mengubah reservasi.

DATA LOKASI AKTIF:
{$ringkasLokasi}

JADWAL TERISI (7 HARI KE DEPAN):
{$ringkasJadwal}
PROMPT;

        $messages = [];
        foreach ($riwayat as $r) {
            $messages[] = [
                'role'    => $r->peran === 'asisten' ? 'assistant' : 'user',
                'content' => $r->isi,
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $pesan];

        return $this->llm->chat($systemPrompt, $messages, [
            'temperature' => 0.3,
            'max_tokens'  => 350,
        ]);
    }

    /**
     * Fallback lokal yang lebih cerdas dan hemat teks jika API key LLM belum diisi.
     */
    protected function jawabLokalCerdas(string $lowerPesan, $lokasiSemua): string
    {
        // 1. Cek apakah user menyebut nama lokasi spesifik
        $lokasiCocok = $lokasiSemua->filter(function ($l) use ($lowerPesan) {
            $nama = mb_strtolower($l->nama);
            return str_contains($lowerPesan, $nama)
                || ($nama === 'gedung serba guna (gsg)' && str_contains($lowerPesan, 'gsg'));
        });

        if ($lokasiCocok->isNotEmpty()) {
            $baris = $lokasiCocok->take(3)->map(function ($l) {
                $fas = $this->formatFasilitasRingkas($l->fasilitas);
                $izin = $l->jenis === 'gedung' ? 'Manual (wajib surat PDF, ACC Bu Eci)' : 'Otomatis tanpa surat';
                return "• **{$l->nama}**: kap. {$l->kapasitas} orang | {$izin}" . ($fas ? " | Fasilitas: {$fas}" : '');
            })->implode("\n");

            return $baris;
        }

        // 2. Pertanyaan jadwal / kosong hari ini atau besok
        if (str_contains($lowerPesan, 'kosong') || str_contains($lowerPesan, 'jadwal') || str_contains($lowerPesan, 'tersedia') || str_contains($lowerPesan, 'besok') || str_contains($lowerPesan, 'hari ini')) {
            $target = str_contains($lowerPesan, 'besok')
                ? Carbon::tomorrow('Asia/Jakarta')
                : Carbon::today('Asia/Jakarta');

            $terisiIds = SlotTerkunci::whereRaw(
                'rentang && tstzrange(?, ?, \'[)\')',
                [$target->copy()->startOfDay()->toIso8601String(), $target->copy()->endOfDay()->toIso8601String()]
            )->pluck('lokasi_id')->unique()->toArray();

            $kosong = $lokasiSemua->whereNotIn('id', $terisiIds)->take(6);
            $labelTgl = $target->locale('id')->translatedFormat('d M Y');

            if ($kosong->isEmpty()) {
                return "Semua lokasi tercatat terisi pada {$labelTgl}. Cek detail jamnya di menu **Jadwal Publik**.";
            }

            $list = $kosong->map(fn ($l) => "• {$l->nama} (kap. {$l->kapasitas})")->implode("\n");
            return "Lokasi kosong seharian pada **{$labelTgl}** (sebagian):\n{$list}";
        }

        // 3. Pertanyaan aturan / cara / syarat / surat
        if (str_contains($lowerPesan, 'aturan') || str_contains($lowerPesan, 'cara') || str_contains($lowerPesan, 'syarat') || str_contains($lowerPesan, 'surat')) {
            return "**Aturan Singkat Peminjaman:**\n"
                . "• **Ruangan:** ACC otomatis jika kosong, tanpa surat.\n"
                . "• **Gedung:** Wajib surat PDF (maks 5 MB), ACC manual oleh Bu Eci.";
        }

        // 4. Pertanyaan daftar gedung / ruangan / kapasitas umum
        if (str_contains($lowerPesan, 'gedung') || str_contains($lowerPesan, 'ruangan') || str_contains($lowerPesan, 'fasilitas') || str_contains($lowerPesan, 'kapasitas')) {
            $gedung = $lokasiSemua->where('jenis', 'gedung')->take(4)->map(fn ($g) => "• {$g->nama} ({$g->kapasitas} org)")->implode("\n");
            $ruang  = $lokasiSemua->where('jenis', 'ruangan')->take(4)->map(fn ($r) => "• {$r->nama} ({$r->kapasitas} org)")->implode("\n");
            return "**Gedung:**\n{$gedung}\n**Ruangan (sampel):**\n{$ruang}\nSebut nama gedung/ruangan untuk detail spesifik.";
        }

        // 5. Pertanyaan kontak / Bu Eci / WA
        if (str_contains($lowerPesan, 'eci') || str_contains($lowerPesan, 'wa') || str_contains($lowerPesan, 'whatsapp') || str_contains($lowerPesan, 'admin') || str_contains($lowerPesan, 'kontak')) {
            return "Pengelola Gedung kampus ditangani oleh **Bu Eci** (WA: 0895336819200). Klik tombol hijau di bawah chat ini untuk langsung terhubung.";
        }

        // 6. Default ringkas
        return "Saya bisa bantu info **kapasitas gedung/ruangan**, **jadwal kosong**, **syarat surat**, atau **lapor kerusakan**.\n"
            . "Contoh: _\"Kapasitas GSG berapa?\"_ atau _\"Besok ruangan apa yang kosong?\"_";
    }

    protected function formatFasilitasRingkas($fasilitas): string
    {
        if (empty($fasilitas)) {
            return '';
        }
        if (is_string($fasilitas)) {
            return $fasilitas;
        }
        if (is_array($fasilitas)) {
            $items = [];
            foreach ($fasilitas as $k => $v) {
                if (is_int($k)) {
                    if ($v) {
                        $items[] = $v;
                    }
                } elseif ($v) {
                    $items[] = is_bool($v) ? strtoupper($k) : strtoupper($k) . " ({$v})";
                }
            }
            return implode(', ', $items);
        }
        return '';
    }
}
