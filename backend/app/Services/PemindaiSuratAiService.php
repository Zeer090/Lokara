<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;

/**
 * PemindaiSuratAiService
 *
 * Menggunakan Gemini Vision untuk membaca foto kertas/surat permohonan,
 * merapikan struktur & ejaan teksnya, lalu men-generate file PDF resmi A4 yang rapi via DomPDF.
 */
class PemindaiSuratAiService
{
    public function __construct(
        protected LayananLlmService $llm
    ) {}

    public function pindaiDanBuatPdf(UploadedFile $foto, ?string $namaPemohon = null): array
    {
        $bytes = file_get_contents($foto->getRealPath());
        $mime  = $foto->getMimeType() ?: 'image/jpeg';
        $base64Foto = base64_encode($bytes);

        $dataSurat = $this->ekstrakTeksSuratDenganAi($base64Foto, $mime, $namaPemohon);

        $pdf = Pdf::loadView('pdf.surat_scan_ai', [
            'surat'          => $dataSurat,
            'foto_data_uri'  => "data:{$mime};base64,{$base64Foto}",
            'waktu_scan'     => now('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i') . ' WIB',
        ])->setPaper('a4', 'portrait');

        $pdfBinary = $pdf->output();
        $filename  = 'surat_scan_ai_' . now('Asia/Jakarta')->format('Ymd_His') . '.pdf';

        return [
            'hasil_scan' => $dataSurat,
            'filename'   => $filename,
            'pdf_base64' => base64_encode($pdfBinary),
            'size_bytes' => strlen($pdfBinary),
        ];
    }

    protected function ekstrakTeksSuratDenganAi(string $base64Foto, string $mime, ?string $namaPemohon): array
    {
        $default = [
            'kop_instansi'      => 'POLITEKNIK NEGERI LAMPUNG (POLINELA)',
            'nomor_surat'       => '-',
            'tanggal_surat'     => now('Asia/Jakarta')->locale('id')->translatedFormat('d F Y'),
            'perihal'           => 'Permohonan Peminjaman Gedung Kampus',
            'tujuan_surat'      => 'Yth. Pengelola Gedung (Bu Eci) Politeknik Negeri Lampung',
            'isi_paragraf'      => [
                'Dengan hormat, melalui surat ini kami bermaksud mengajukan permohonan peminjaman gedung kampus untuk mendukung kelancaran kegiatan kami.',
                'Demikian surat permohonan ini kami sampaikan. Atas perhatian dan persetujuan Ibu, kami ucapkan terima kasih.',
            ],
            'penandatangan'     => $namaPemohon ?: 'Pemohon',
            'jabatan'           => 'Penanggung Jawab Kegiatan',
            'keperluan_ringkas' => 'Permohonan peminjaman gedung sesuai lampiran surat fisik.',
            'sumber_scan'       => 'fallback_lokal',
        ];

        if (!$this->llm->tersedia()) {
            return $default;
        }

        $systemPrompt = <<<PROMPT
Kamu adalah AI OCR & Penulis Dokumen Resmi Kampus SMARTSPACE POLINELA.
Tugasmu: Baca foto kertas/surat permohonan yang dilampirkan secara teliti, perbaiki salah ketik/ejaan ringan agar rapi dan formal, lalu kembalikan dalam format JSON valid dengan key persis:
{
  "kop_instansi": "nama organisasi/unit/instansi pengirim di kepala surat (jika tidak terbaca, tulis POLITEKNIK NEGERI LAMPUNG)",
  "nomor_surat": "nomor surat jika ada, atau '-'",
  "tanggal_surat": "tanggal surat yang tertulis atau tanggal hari ini",
  "perihal": "perihal / subjek surat",
  "tujuan_surat": "tujuan surat (mis. Yth. Pengelola Gedung POLINELA)",
  "isi_paragraf": ["paragraf 1 isi surat yang rapi dan lengkap...", "paragraf 2...", "paragraf penutup..."],
  "penandatangan": "nama penandatangan di bagian bawah surat",
  "jabatan": "jabatan penandatangan (mis. Ketua Pelaksana / Sekretaris)",
  "keperluan_ringkas": "ringkasan 1 kalimat singkat keperluan acara untuk mengisi form peminjaman"
}
JANGAN mengarang fakta yang bertentangan dengan isi foto. Jika tulisan kurang jelas, tulis inti yang terbaca dengan bahasa Indonesia formal yang rapi.
PROMPT;

        $json = $this->llm->chatJson($systemPrompt, [
            ['role' => 'user', 'content' => 'Tolong pindai dan rapikan surat permohonan dari foto kertas ini menjadi JSON terstruktur.'],
        ], [
            'temperature'  => 0.1,
            'max_tokens'   => 900,
            'inline_image' => [
                'mime_type' => $mime,
                'data'      => $base64Foto,
            ],
        ]);

        if (!is_array($json) || empty($json['isi_paragraf'])) {
            return $default;
        }

        return [
            'kop_instansi'      => (string) ($json['kop_instansi'] ?? $default['kop_instansi']),
            'nomor_surat'       => (string) ($json['nomor_surat'] ?? '-'),
            'tanggal_surat'     => (string) ($json['tanggal_surat'] ?? $default['tanggal_surat']),
            'perihal'           => (string) ($json['perihal'] ?? $default['perihal']),
            'tujuan_surat'      => (string) ($json['tujuan_surat'] ?? $default['tujuan_surat']),
            'isi_paragraf'      => is_array($json['isi_paragraf']) ? $json['isi_paragraf'] : [$default['isi_paragraf'][0]],
            'penandatangan'     => (string) ($json['penandatangan'] ?? $default['penandatangan']),
            'jabatan'           => (string) ($json['jabatan'] ?? $default['jabatan']),
            'keperluan_ringkas' => (string) ($json['keperluan_ringkas'] ?? $default['keperluan_ringkas']),
            'sumber_scan'       => 'gemini_vision',
        ];
    }
}
