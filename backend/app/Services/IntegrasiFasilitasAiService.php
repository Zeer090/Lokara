<?php

namespace App\Services;

use App\Models\LaporanFasilitas;
use App\Models\Lokasi;
use Illuminate\Support\Facades\Storage;

/**
 * IntegrasiFasilitasAiService
 *
 * Menganalisis laporan kerusakan mahasiswa (keterangan + foto kamera opsional)
 * menggunakan Gemini AI / fallback lokal, lalu langsung mengintegrasikan
 * status kerusakan ke deskripsi `fasilitas` (JSONB) pada ruangan/gedung terkait.
 */
class IntegrasiFasilitasAiService
{
    public function __construct(
        protected LayananLlmService $llm
    ) {}

    /**
     * Menganalisis laporan yang baru dikirim & memperbarui deskripsi fasilitas lokasi.
     */
    public function prosesLaporan(LaporanFasilitas $laporan): array
    {
        $laporan->loadMissing('lokasi');
        $lokasi = $laporan->lokasi;

        $analisis = $this->analisisDenganAi($laporan, $lokasi);

        $laporan->update([
            'hasil_analisis_ai' => $analisis,
        ]);

        // Integrasikan langsung ke deskripsi `fasilitas` pada tabel `lokasi`
        if ($lokasi && !empty($analisis['item_rusak'])) {
            $fasilitasBaru = is_array($lokasi->fasilitas) ? $lokasi->fasilitas : [];
            foreach ($analisis['item_rusak'] as $item) {
                $kunci = $this->normalisasiKunci($item['nama'] ?? '');
                if ($kunci === '') {
                    continue;
                }
                $statusLabel = trim((string) ($item['status'] ?? 'Rusak (Dilaporkan)'));
                $fasilitasBaru[$kunci] = "⚠️ {$statusLabel}";
            }

            $lokasi->update([
                'fasilitas' => $fasilitasBaru,
            ]);
        }

        return $analisis;
    }

    /**
     * Memulihkan status fasilitas di lokasi ketika laporan ditandai selesai / ditolak.
     */
    public function pulihkanFasilitasSetelahSelesai(LaporanFasilitas $laporan): void
    {
        $laporan->loadMissing('lokasi');
        $lokasi = $laporan->lokasi;
        if (!$lokasi) {
            return;
        }

        $analisis = $laporan->hasil_analisis_ai;
        if (!is_array($analisis) || empty($analisis['item_rusak'])) {
            return;
        }

        $fasilitas = is_array($lokasi->fasilitas) ? $lokasi->fasilitas : [];
        $berubah = false;

        foreach ($analisis['item_rusak'] as $item) {
            $kunci = $this->normalisasiKunci($item['nama'] ?? '');
            if ($kunci !== '' && array_key_exists($kunci, $fasilitas) && is_string($fasilitas[$kunci]) && str_contains($fasilitas[$kunci], '⚠️')) {
                // Kembalikan ke boolean true (Normal)
                $fasilitas[$kunci] = true;
                $berubah = true;
            }
        }

        if ($berubah) {
            $lokasi->update(['fasilitas' => $fasilitas]);
        }
    }

    protected function analisisDenganAi(LaporanFasilitas $laporan, ?Lokasi $lokasi): array
    {
        $fasilitasSaatIni = $lokasi && is_array($lokasi->fasilitas)
            ? implode(', ', array_keys($lokasi->fasilitas))
            : 'ac, proyektor, kursi, lampu, komputer, papan_tulis, sound_system';

        $inlineImage = null;
        if ($laporan->foto_path && Storage::disk('public')->exists($laporan->foto_path)) {
            $bytes = Storage::disk('public')->get($laporan->foto_path);
            $mime  = Storage::disk('public')->mimeType($laporan->foto_path) ?: 'image/jpeg';
            $inlineImage = [
                'mime_type' => $mime,
                'data'      => base64_encode($bytes),
            ];
        }

        if ($this->llm->tersedia()) {
            $systemPrompt = <<<PROMPT
Kamu adalah AI Agent Pemeliharaan Fasilitas Kampus SMARTSPACE POLINELA.
Analisis keluhan mahasiswa (dan foto jika dilampirkan) lalu identifikasi fasilitas apa yang bermasalah.
Daftar kunci fasilitas standar yang umum di ruangan/gedung ini: {$fasilitasSaatIni}.

Kembalikan JSON valid dengan format persis:
{
  "ringkasan_ai": "ringkasan 1 kalimat singkat kondisi kerusakan",
  "tingkat_urgensi": "rendah" | "sedang" | "tinggi",
  "item_rusak": [
    { "nama": "nama_fasilitas_huruf_kecil_snake_case", "status": "label singkat max 3 kata, mis. Rusak / Mati" }
  ]
}
PROMPT;

            $teksUser = "Lokasi: " . ($lokasi?->nama ?? 'Belum spesifik')
                . "\nRingkasan: {$laporan->ringkasan}"
                . "\nKeterangan Mahasiswa: {$laporan->isi}";

            $json = $this->llm->chatJson($systemPrompt, [
                ['role' => 'user', 'content' => $teksUser],
            ], [
                'temperature'  => 0.1,
                'max_tokens'   => 250,
                'inline_image' => $inlineImage,
            ]);

            if (is_array($json) && !empty($json['item_rusak']) && is_array($json['item_rusak'])) {
                return [
                    'ringkasan_ai'    => (string) ($json['ringkasan_ai'] ?? $laporan->ringkasan),
                    'tingkat_urgensi' => (string) ($json['tingkat_urgensi'] ?? 'sedang'),
                    'item_rusak'      => $json['item_rusak'],
                    'sumber_analisis' => 'gemini_ai',
                ];
            }
        }

        // Fallback lokal cerdas
        return $this->analisisLokal($laporan);
    }

    protected function analisisLokal(LaporanFasilitas $laporan): array
    {
        $teks = mb_strtolower($laporan->ringkasan . ' ' . $laporan->isi);
        $kamus = [
            'ac'           => ['ac', 'pendingin', 'dingin'],
            'kursi'        => ['kursi', 'bangku', 'meja'],
            'proyektor'    => ['proyektor', 'infocus', 'layar'],
            'lampu'        => ['lampu', 'penerangan', 'gelap'],
            'komputer'     => ['komputer', 'pc', 'monitor', 'keyboard'],
            'sound_system' => ['sound', 'speaker', 'mic', 'mikrofon', 'audio'],
            'internet'     => ['wifi', 'internet', 'jaringan'],
            'papan_tulis'  => ['papan', 'whiteboard', 'spidol'],
            'pintu'        => ['pintu', 'kunci', 'jendela'],
            'atap'         => ['atap', 'plafon', 'bocor'],
        ];

        $itemRusak = [];
        foreach ($kamus as $kunci => $kataKunci) {
            foreach ($kataKunci as $k) {
                if (str_contains($teks, $k)) {
                    $status = str_contains($teks, 'mati') ? 'Mati (Dilaporkan)'
                        : (str_contains($teks, 'bocor') ? 'Bocor (Dilaporkan)'
                        : (str_contains($teks, 'kotor') ? 'Kotor (Dilaporkan)' : 'Rusak (Dilaporkan)'));
                    $itemRusak[] = ['nama' => $kunci, 'status' => $status];
                    break;
                }
            }
        }

        if (empty($itemRusak)) {
            $itemRusak[] = ['nama' => 'fasilitas_umum', 'status' => 'Perlu Perbaikan'];
        }

        return [
            'ringkasan_ai'    => $laporan->ringkasan,
            'tingkat_urgensi' => 'sedang',
            'item_rusak'      => $itemRusak,
            'sumber_analisis' => 'lokal',
        ];
    }

    protected function normalisasiKunci(string $nama): string
    {
        $k = mb_strtolower(trim($nama));
        $k = preg_replace('/[^a-z0-9]+/', '_', $k);
        return trim($k, '_');
    }
}
