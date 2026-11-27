<?php

namespace App\Services;

use App\Models\KasusPenggusuran;
use App\Models\LogAgent;
use App\Models\Lokasi;
use App\Models\PesanNegosiasi;
use App\Models\Reservasi;
use App\Models\SlotTerkunci;
use App\Models\UsulanAgent;
use Carbon\Carbon;

/**
 * AgenPenjadwalService
 *
 * Implementasi AI Agent Penjadwal Ulang SMARTSPACE AI (Fase 3 & B-06).
 * Mendukung model AI gratis (Gemini / Groq / OpenRouter) via LayananLlmService
 * dengan penegakan pagar keamanan deterministik di kode PHP.
 */
class AgenPenjadwalService
{
    protected const MAKS_LANGKAH_PER_GILIRAN = 8;
    protected const PENUTUP_WAJIB = "\n\nUsulan ini belum disetujui. Persetujuan akhir hanya dari pengelola gedung.";

    public function __construct(
        protected LayananLlmService $llm
    ) {}

    /**
     * Memproses satu giliran pesan dari pengguna dan menjalankan alat AI.
     */
    public function prosesGiliran(KasusPenggusuran $kasus, string $pesanPengguna): array
    {
        $kasus->loadMissing(['reservasi.lokasi', 'acaraKampus']);
        $reservasiAsal = $kasus->reservasi;

        $langkahTerakhir = LogAgent::where('kasus_id', $kasus->id)->max('langkah') ?? 0;
        $langkah = $langkahTerakhir + 1;

        $lowerPesan = mb_strtolower(trim($pesanPengguna));

        // ── PAGAR 1: Permintaan Ilegal / Di Luar Wewenang (Penegakan Kode) ────
        $kataIlegal = ['setujui sekarang', 'langsung acc', 'bypass', 'abaikan aturan', 'saya pejabat', 'saya dosen', 'acc paksa'];
        foreach ($kataIlegal as $kata) {
            if (str_contains($lowerPesan, $kata)) {
                $this->catatLog(
                    $kasus,
                    $langkah++,
                    'pagar_penolakan',
                    'pagar_wewenang',
                    ['pesan' => $pesanPengguna],
                    ['diterima' => false],
                    'Pengguna meminta bypass/persetujuan langsung yang dilarang.'
                );

                $pesanBalas = "Maaf, AI Agen tidak berwenang menyetujui reservasi secara langsung. Persetujuan akhir tetap oleh **Bu Eci** (Pengelola Gedung).\n"
                    . "Saya bisa bantu carikan jadwal pengganti yang kosong untuk Anda ajukan ulang.";

                $tanggapanAgent = $this->simpanPesanAgent($kasus, $pesanBalas);

                return [
                    'tanggapan'    => $tanggapanAgent,
                    'usulan_baru'  => [],
                    'kasus_status' => $kasus->status,
                ];
            }
        }

        // ── PAGAR 2: Pengguna Ingin Langsung Bicara dengan Bu Eci ─────────────
        $kataSerahkan = ['hubungi bu eci', 'bicara bu eci', 'chat wa', 'whatsapp', 'langsung ke bu eci', 'serahkan ke pengelola', 'pasrah', 'bicara pengelola'];
        foreach ($kataSerahkan as $kata) {
            if (str_contains($lowerPesan, $kata)) {
                $this->serahkanKePengelola($kasus, 'Pengguna meminta penanganan langsung oleh Bu Eci.', $langkah);

                $pesanBalas = "Kasus dialihkan ke **Bu Eci**. Silakan klik tombol **Chat Bu Eci via WhatsApp** untuk koordinasi langsung.";
                $tanggapanAgent = $this->simpanPesanAgent($kasus, $pesanBalas);

                return [
                    'tanggapan'    => $tanggapanAgent,
                    'usulan_baru'  => [],
                    'kasus_status' => 'diserahkan',
                ];
            }
        }

        // ── ANALISIS NIAT & PREFERENSI (LLM Gratis + Fallback Regex) ──────────
        $analisis = $this->analisisNiatPengguna($kasus, $pesanPengguna);

        // Jika pengguna hanya bertanya info (mis. tanya kapasitas/alasan/prosedur) & tidak minta cari jadwal
        if (($analisis['tujuan'] ?? 'cari_usulan') === 'tanya_info') {
            $jawabanInfo = $this->jawabPertanyaanKasus($kasus, $pesanPengguna);
            $tanggapanAgent = $this->simpanPesanAgent($kasus, $jawabanInfo);

            return [
                'tanggapan'    => $tanggapanAgent,
                'usulan_baru'  => [],
                'kasus_status' => $kasus->status,
            ];
        }

        // ── ALAT: Cari Gedung Kosong & Tawarkan Usulan ─────────────────────────
        $kandidatSlot = $this->cariGedungKosong($kasus, $analisis, $langkah);

        $usulanTersimpan = [];
        foreach ($kandidatSlot as $kandidat) {
            if (count($usulanTersimpan) >= 2) {
                break;
            }

            $bentrokCheck = $this->cekBentrok(
                $kandidat['lokasi_id'],
                $kandidat['mulai'],
                $kandidat['selesai'],
                $langkah,
                $kasus
            );

            if ($bentrokCheck['aman']) {
                $usulan = $this->simpanUsulan(
                    $kasus,
                    $kandidat['lokasi_id'],
                    $kandidat['mulai'],
                    $kandidat['selesai'],
                    $kandidat['alasan_usulan'],
                    $langkah
                );

                if ($usulan) {
                    $usulanTersimpan[] = $usulan->load('lokasi:id,nama,jenis,kapasitas');
                }
            }
        }

        // ── Buat Pesan Tanggapan Ringkas & Jelas ───────────────────────────────
        if (count($usulanTersimpan) > 0) {
            $pesanBalas = $this->susunBalasanUsulanRingkas($kasus, $pesanPengguna, $usulanTersimpan);
        } else {
            $this->serahkanKePengelola($kasus, 'Tidak ditemukan gedung kosong pada rentang waktu yang sesuai.', $langkah);
            $pesanBalas = "Semua gedung terisi pada rentang waktu tersebut. Kasus dialihkan ke **Bu Eci** — silakan klik tombol **Chat Bu Eci via WhatsApp**.";
        }

        $tanggapanAgent = $this->simpanPesanAgent($kasus, $pesanBalas);

        return [
            'tanggapan'    => $tanggapanAgent,
            'usulan_baru'  => $usulanTersimpan,
            'kasus_status' => $kasus->fresh()->status,
        ];
    }

    /**
     * Memahami niat dan preferensi dari kalimat bebas pengguna.
     */
    protected function analisisNiatPengguna(KasusPenggusuran $kasus, string $pesan): array
    {
        $fallback = $this->ekstrakPreferensi($pesan, $kasus->reservasi);

        if (!$this->llm->tersedia()) {
            return $fallback;
        }

        $systemPrompt = <<<PROMPT
Ekstrak niat pengguna pada negosiasi jadwal pengganti reservasi gedung kampus menjadi JSON valid dengan key:
- "tujuan": "cari_usulan" (jika minta jadwal pengganti, opsi lain, geser hari/jam, atau menyebut lokasi/waktu) ATAU "tanya_info" (jika HANYA bertanya informasi seperti kapasitas, alasan digusur, aturan, cara pengajuan ulang, tanpa minta carikan jadwal).
- "geser_hari": integer >= 0 (misal besok=1, lusa=2, minggu depan=7, tidak disebut=0).
- "shift": "pagi" | "siang" | "sore" | "asal".
- "gedung_berbeda": boolean.
- "minta_opsi_lain": boolean.
- "min_kapasitas": integer >= 0 (jika user menyebut jumlah peserta/orang, selain itu 0).
- "nama_gedung_diminta": string kosong atau nama gedung yang diminta (mis. "GSG", "Gedung A").
PROMPT;

        $json = $this->llm->chatJson($systemPrompt, [
            ['role' => 'user', 'content' => $pesan],
        ], ['temperature' => 0.1, 'max_tokens' => 180]);

        if (!is_array($json)) {
            return $fallback;
        }

        return [
            'tujuan'              => in_array($json['tujuan'] ?? '', ['cari_usulan', 'tanya_info'], true) ? $json['tujuan'] : 'cari_usulan',
            'geser_hari'          => max(0, (int) ($json['geser_hari'] ?? $fallback['geser_hari'])),
            'shift'               => in_array($json['shift'] ?? '', ['pagi', 'siang', 'sore', 'asal'], true) ? $json['shift'] : $fallback['shift'],
            'gedung_berbeda'      => (bool) ($json['gedung_berbeda'] ?? $fallback['gedung_berbeda']),
            'minta_opsi_lain'     => (bool) ($json['minta_opsi_lain'] ?? $fallback['minta_opsi_lain']),
            'min_kapasitas'       => max(0, (int) ($json['min_kapasitas'] ?? 0)),
            'nama_gedung_diminta' => trim((string) ($json['nama_gedung_diminta'] ?? '')),
        ];
    }

    /**
     * Menjawab pertanyaan umum di dalam ruang negosiasi tanpa membuat usulan jadwal.
     */
    protected function jawabPertanyaanKasus(KasusPenggusuran $kasus, string $pesan): string
    {
        $gedungList = Lokasi::where('jenis', 'gedung')->where('aktif', true)->get();
        $infoGedung = $gedungList->map(fn ($g) => "- {$g->nama}: kapasitas {$g->kapasitas} orang")->implode("\n");
        $asal = $kasus->reservasi;
        $lokasiAsal = $asal?->lokasi?->nama ?? '-';
        $alasan = $kasus->alasan_penggusuran ?: 'acara kampus mendesak';

        if ($this->llm->tersedia()) {
            $riwayat = PesanNegosiasi::where('kasus_id', $kasus->id)
                ->orderBy('dibuat_pada', 'desc')
                ->limit(6)
                ->get()
                ->reverse()
                ->map(fn ($p) => [
                    'role'    => $p->pengirim === 'pengguna' ? 'user' : 'assistant',
                    'content' => $p->isi,
                ])
                ->values()
                ->all();

            $systemPrompt = <<<PROMPT
Kamu adalah Agen Negosiasi Jadwal SMARTSPACE POLINELA.
ATURAN:
1. Jawab SANGAT SINGKAT (maksimal 2-3 kalimat), sopan, dan langsung ke inti.
2. DILARANG menjanjikan persetujuan/ACC otomatis (ACC akhir hanya oleh Bu Eci).
3. Konteks kasus saat ini:
   - Reservasi tergusur: {$lokasiAsal} ({$asal?->mulai} s.d. {$asal?->selesai})
   - Alasan penggusuran: {$alasan}
   - Alur setelah memilih usulan: klik "Pilih ini" -> chat WA Bu Eci -> klik "Ajukan Reservasi" (memakai surat lama).
Daftar gedung kampus:
{$infoGedung}
PROMPT;

            $balas = $this->llm->chat($systemPrompt, $riwayat, [
                'temperature' => 0.3,
                'max_tokens'  => 220,
            ]);

            if ($balas) {
                return $balas;
            }
        }

        // Fallback lokal
        return "Reservasi Anda di **{$lokasiAsal}** tergusur karena: _{$alasan}_.\n"
            . "Jika ingin saya carikan jadwal pengganti, cukup ketik misal: _\"Carikan jadwal pengganti minggu depan\"_.";
    }

    /**
     * Menyusun balasan ringkas setelah usulan berhasil disimpan (tidak mengulang-ulang isi kartu).
     */
    protected function susunBalasanUsulanRingkas(KasusPenggusuran $kasus, string $pesanPengguna, array $usulanTersimpan): string
    {
        $jumlah = count($usulanTersimpan);
        $ringkasOpsi = collect($usulanTersimpan)->map(function ($u) {
            $m = $u->mulai->locale('id')->translatedFormat('d M, H:i');
            $s = $u->selesai->locale('id')->translatedFormat('H:i');
            return "{$u->lokasi->nama} ({$m}-{$s} WIB)";
        })->implode(' & ');

        if ($this->llm->tersedia()) {
            $systemPrompt = "Kamu adalah Agen Negosiasi SMARTSPACE. Buat 1-2 kalimat pengantar yang ramah, singkat, dan padat untuk menyampaikan bahwa ada {$jumlah} opsi jadwal bebas bentrok ({$ringkasOpsi}) yang sudah muncul di Kartu Usulan di bawah chat. JANGAN mengulang rincian panjang. JANGAN menjanjikan ACC pasti.";
            $teks = $this->llm->chat($systemPrompt, [
                ['role' => 'user', 'content' => $pesanPengguna],
            ], ['temperature' => 0.3, 'max_tokens' => 140]);

            if ($teks) {
                return $teks;
            }
        }

        return "Ditemukan **{$jumlah} opsi jadwal kosong** bebas bentrok. Silakan klik **Pilih ini** pada Kartu Usulan di bawah, atau minta opsi waktu lain.";
    }

    /**
     * Ekstrak preferensi dari kalimat pengguna (fallback offline).
     */
    protected function ekstrakPreferensi(string $pesan, ?Reservasi $reservasiAsal): array
    {
        $lower = mb_strtolower($pesan);
        $tujuan = 'cari_usulan';

        $kataTanya = ['kenapa', 'mengapa', 'berapa kapasitas', 'syarat', 'bagaimana cara', 'alur'];
        foreach ($kataTanya as $kt) {
            if (str_contains($lower, $kt) && !str_contains($lower, 'cari') && !str_contains($lower, 'jadwal') && !str_contains($lower, 'opsi')) {
                $tujuan = 'tanya_info';
                break;
            }
        }

        $preferensi = [
            'tujuan'              => $tujuan,
            'geser_hari'          => 0,
            'shift'               => 'asal',
            'gedung_berbeda'      => false,
            'minta_opsi_lain'     => false,
            'min_kapasitas'       => 0,
            'nama_gedung_diminta' => '',
        ];

        if (str_contains($lower, 'lain') || str_contains($lower, 'berbeda') || str_contains($lower, 'opsi lain')) {
            $preferensi['minta_opsi_lain'] = true;
        }

        if (str_contains($lower, 'besok')) {
            $preferensi['geser_hari'] = 1;
        } elseif (str_contains($lower, 'lusa')) {
            $preferensi['geser_hari'] = 2;
        } elseif (str_contains($lower, 'minggu depan')) {
            $preferensi['geser_hari'] = 7;
        }

        if (str_contains($lower, 'pagi')) {
            $preferensi['shift'] = 'pagi';
        } elseif (str_contains($lower, 'siang')) {
            $preferensi['shift'] = 'siang';
        } elseif (str_contains($lower, 'sore')) {
            $preferensi['shift'] = 'sore';
        }

        if (str_contains($lower, 'gedung lain') || str_contains($lower, 'gsg') || str_contains($lower, 'gedung a') || str_contains($lower, 'gedung b')) {
            $preferensi['gedung_berbeda'] = true;
        }

        return $preferensi;
    }

    /**
     * Alat: cari_gedung_kosong
     */
    public function cariGedungKosong(KasusPenggusuran $kasus, array $preferensi, int &$langkah): array
    {
        $reservasiAsal = $kasus->reservasi;
        $mulaiAsal = Carbon::parse($reservasiAsal->mulai);
        $selesaiAsal = Carbon::parse($reservasiAsal->selesai);
        $durasiMenit = max(60, abs($mulaiAsal->diffInMinutes($selesaiAsal)));

        $queryGedung = Lokasi::where('jenis', 'gedung')->where('aktif', true);
        if (!empty($preferensi['min_kapasitas'])) {
            $queryGedung->where('kapasitas', '>=', (int) $preferensi['min_kapasitas']);
        }
        $gedungList = $queryGedung->orderBy('kapasitas', 'desc')->get();

        // Jika filter kapasitas terlalu ketat hingga kosong, fallback ke semua gedung aktif
        if ($gedungList->isEmpty()) {
            $gedungList = Lokasi::where('jenis', 'gedung')->where('aktif', true)->orderBy('kapasitas', 'desc')->get();
        }

        // Prioritaskan gedung yang disebut user jika ada
        $namaDiminta = mb_strtolower($preferensi['nama_gedung_diminta'] ?? '');
        if ($namaDiminta !== '') {
            $gedungList = $gedungList->sortByDesc(function ($g) use ($namaDiminta) {
                return str_contains(mb_strtolower($g->nama), $namaDiminta) ? 1 : 0;
            })->values();
        }

        $kandidat = [];
        $usulanLama = UsulanAgent::where('kasus_id', $kasus->id)->get();

        $hariOffset = ($preferensi['geser_hari'] ?? 0) > 0
            ? [$preferensi['geser_hari'], $preferensi['geser_hari'] + 1]
            : [1, 2, 7, 3];

        if (!empty($preferensi['minta_opsi_lain'])) {
            $hariOffset = [2, 3, 7, 5];
        }

        $kandidatMap = [];

        foreach ($hariOffset as $offset) {
            $mulaiBaru = $mulaiAsal->copy()->addDays($offset);
            $selesaiBaru = $mulaiBaru->copy()->addMinutes($durasiMenit);

            if (($preferensi['shift'] ?? 'asal') === 'pagi') {
                $mulaiBaru->setTime(8, 0, 0);
                $selesaiBaru = $mulaiBaru->copy()->addMinutes($durasiMenit);
            } elseif (($preferensi['shift'] ?? 'asal') === 'siang') {
                $mulaiBaru->setTime(13, 0, 0);
                $selesaiBaru = $mulaiBaru->copy()->addMinutes($durasiMenit);
            } elseif (($preferensi['shift'] ?? 'asal') === 'sore') {
                $mulaiBaru->setTime(16, 0, 0);
                $selesaiBaru = $mulaiBaru->copy()->addMinutes($durasiMenit);
            }

            if (empty($preferensi['gedung_berbeda']) && $namaDiminta === '') {
                $lokasiId = $reservasiAsal->lokasi_id;
                $kunciUnikAsal = "{$lokasiId}_{$mulaiBaru->timestamp}";
                if (!$this->sudahPernahDiusulkan($usulanLama, $lokasiId, $mulaiBaru, $selesaiBaru) && !isset($kandidatMap[$kunciUnikAsal])) {
                    $kandidatMap[$kunciUnikAsal] = true;
                    $kandidat[] = [
                        'lokasi_id'     => $lokasiId,
                        'mulai'         => $mulaiBaru,
                        'selesai'       => $selesaiBaru,
                        'alasan_usulan' => "Gedung sama ({$reservasiAsal->lokasi->nama}), digeser +{$offset} hari.",
                    ];
                }
            }

            foreach ($gedungList as $g) {
                if ($g->id === $reservasiAsal->lokasi_id && empty($preferensi['gedung_berbeda'])) {
                    continue;
                }

                $kunciUnik = "{$g->id}_{$mulaiBaru->timestamp}";
                if (!$this->sudahPernahDiusulkan($usulanLama, $g->id, $mulaiBaru, $selesaiBaru) && !isset($kandidatMap[$kunciUnik])) {
                    $kandidatMap[$kunciUnik] = true;
                    $kandidat[] = [
                        'lokasi_id'     => $g->id,
                        'mulai'         => $mulaiBaru,
                        'selesai'       => $selesaiBaru,
                        'alasan_usulan' => "Gedung {$g->nama} (kap. {$g->kapasitas} orang), +{$offset} hari.",
                    ];
                }
            }
        }

        $this->catatLog(
            $kasus,
            $langkah++,
            'alat',
            'cari_gedung_kosong',
            [
                'lokasi_asal_id' => $reservasiAsal->lokasi_id,
                'durasi_menit'   => $durasiMenit,
                'preferensi'     => $preferensi,
            ],
            [
                'jumlah_kandidat' => count($kandidat),
            ],
            'Menemukan ' . count($kandidat) . ' kandidat slot gedung untuk diverifikasi bentrok.'
        );

        return $kandidat;
    }

    protected function sudahPernahDiusulkan($usulanLama, string $lokasiId, Carbon $mulai, Carbon $selesai): bool
    {
        foreach ($usulanLama as $u) {
            if ($u->lokasi_id === $lokasiId && $u->mulai->equalTo($mulai) && $u->selesai->equalTo($selesai)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Alat: cek_bentrok
     */
    public function cekBentrok(string $lokasiId, Carbon $mulai, Carbon $selesai, int &$langkah, KasusPenggusuran $kasus): array
    {
        $mulaiIso = $mulai->toIso8601String();
        $selesaiIso = $selesai->toIso8601String();

        $bentrokTerkunci = SlotTerkunci::where('lokasi_id', $lokasiId)
            ->whereRaw('rentang && tstzrange(?, ?, \'[)\')', [$mulaiIso, $selesaiIso])
            ->exists();

        $antreanMenunggu = Reservasi::where('lokasi_id', $lokasiId)
            ->where('status', 'menunggu')
            ->where('id', '!=', $kasus->reservasi_id)
            ->where(function ($q) use ($mulai, $selesai) {
                $q->whereBetween('mulai', [$mulai, $selesai])
                  ->orWhereBetween('selesai', [$mulai, $selesai])
                  ->orWhere(function ($sub) use ($mulai, $selesai) {
                      $sub->where('mulai', '<=', $mulai)->where('selesai', '>=', $selesai);
                  });
            })
            ->count();

        $aman = (! $bentrokTerkunci);

        $this->catatLog(
            $kasus,
            $langkah++,
            'alat',
            'cek_bentrok',
            [
                'lokasi_id' => $lokasiId,
                'mulai'     => $mulaiIso,
                'selesai'   => $selesaiIso,
            ],
            [
                'aman'             => $aman,
                'bentrok_terkunci' => $bentrokTerkunci,
                'antrean_menunggu' => $antreanMenunggu,
            ],
            $aman ? 'Slot aman dari bentrok jadwal.' : 'Slot bentrok dengan jadwal lain.'
        );

        return [
            'aman'             => $aman,
            'bentrok_terkunci' => $bentrokTerkunci,
            'antrean_menunggu' => $antreanMenunggu,
        ];
    }

    /**
     * Alat: simpan_usulan
     */
    public function simpanUsulan(KasusPenggusuran $kasus, string $lokasiId, Carbon $mulai, Carbon $selesai, string $alasan, int &$langkah): ?UsulanAgent
    {
        $mulaiIso = $mulai->toIso8601String();
        $selesaiIso = $selesai->toIso8601String();

        $adaBentrok = SlotTerkunci::where('lokasi_id', $lokasiId)
            ->whereRaw('rentang && tstzrange(?, ?, \'[)\')', [$mulaiIso, $selesaiIso])
            ->exists();

        if ($adaBentrok) {
            $this->catatLog(
                $kasus,
                $langkah++,
                'pagar_penolakan',
                'simpan_usulan',
                ['lokasi_id' => $lokasiId, 'mulai' => $mulaiIso, 'selesai' => $selesaiIso],
                ['disimpan' => false],
                'Pagar menolak simpan_usulan karena slot bentrok dengan slot_terkunci.'
            );
            return null;
        }

        $usulan = UsulanAgent::create([
            'kasus_id'      => $kasus->id,
            'lokasi_id'     => $lokasiId,
            'mulai'         => $mulai,
            'selesai'       => $selesai,
            'alasan_usulan' => $alasan,
            'status'        => 'diusulkan',
            'bentrok_dicek' => true,
            'dibuat_pada'   => now(),
        ]);

        $this->catatLog(
            $kasus,
            $langkah++,
            'alat',
            'simpan_usulan',
            [
                'usulan_id' => $usulan->id,
                'lokasi_id' => $lokasiId,
                'mulai'     => $mulaiIso,
                'selesai'   => $selesaiIso,
            ],
            [
                'disimpan' => true,
            ],
            'Usulan jadwal alternatif berhasil disimpan ke tabel usulan_agent.'
        );

        return $usulan;
    }

    /**
     * Alat: serahkan_ke_pengelola
     */
    public function serahkanKePengelola(KasusPenggusuran $kasus, string $alasan, int &$langkah): void
    {
        $kasus->update([
            'status' => 'diserahkan',
        ]);

        $this->catatLog(
            $kasus,
            $langkah++,
            'alat',
            'serahkan_ke_pengelola',
            ['alasan' => $alasan],
            ['status_baru' => 'diserahkan'],
            'Kasus diserahkan ke pengelola gedung (Bu Eci via WhatsApp).'
        );
    }

    /**
     * Simpan pesan tanggapan agent dengan menerapkan Pagar Janji & Penutup Wajib.
     */
    protected function simpanPesanAgent(KasusPenggusuran $kasus, string $teks): PesanNegosiasi
    {
        $kataJanji = ['sudah pasti', 'dijamin lolos', 'pasti acc', 'pasti disetujui'];
        foreach ($kataJanji as $janji) {
            $teks = str_ireplace($janji, 'berpotensi disetujui', $teks);
        }

        if (!str_contains($teks, 'Usulan ini belum disetujui')) {
            $teks .= self::PENUTUP_WAJIB;
        }

        return PesanNegosiasi::create([
            'kasus_id'    => $kasus->id,
            'pengirim'    => 'agent',
            'isi'         => trim($teks),
            'dibuat_pada' => now(),
        ]);
    }

    protected function catatLog(KasusPenggusuran $kasus, int $langkah, string $jenis, ?string $namaFungsi, array $masukan, array $hasil, ?string $catatan): void
    {
        try {
            LogAgent::create([
                'kasus_id'    => $kasus->id,
                'langkah'     => $langkah,
                'jenis'       => $jenis,
                'nama_fungsi' => $namaFungsi,
                'masukan'     => $masukan,
                'hasil'       => $hasil,
                'catatan'     => $catatan,
                'dibuat_pada' => now(),
            ]);
        } catch (\Throwable $e) {
            logger()->warning('Gagal mencatat log_agent: ' . $e->getMessage());
        }
    }
}
