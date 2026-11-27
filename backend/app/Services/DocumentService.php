<?php

namespace App\Services;

use App\Models\LogAksesDokumen;
use App\Models\Memo;
use App\Models\MemoRiwayat;
use App\Models\Reservasi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DocumentService
{
    /**
     * Catat setiap akses surat dan memo ke log_akses_dokumen.
     * Sesuai aturan: jika pencatatan gagal, akses wajib ditolak.
     */
    public function logAccess(
        ?string $penggunaId,
        string $jenisDokumen,
        string $dokumenId,
        ?string $reservasiId,
        string $aksi,
        string $hasil,
        ?string $alasanTolak = null,
        ?string $ip = null
    ): LogAksesDokumen {
        try {
            return LogAksesDokumen::create([
                'pengguna_id'   => $penggunaId,
                'jenis_dokumen' => $jenisDokumen,
                'dokumen_id'    => $dokumenId,
                'reservasi_id'  => $reservasiId,
                'aksi'          => $aksi,
                'hasil'         => $hasil,
                'alasan_tolak'  => $alasanTolak,
                'alamat_ip'     => $ip,
            ]);
        } catch (\Throwable $e) {
            // Jika logging gagal, sistem wajib menolak akses
            throw new HttpException(500, 'Gagal mencatat audit log akses dokumen: ' . $e->getMessage());
        }
    }

    /**
     * Validasi berkas PDF surat:
     * - Ekstensi .pdf
     * - MIME application/pdf
     * - Magic bytes %PDF-
     * - Ukuran maksimal MAX_PDF_MB (default 5 MB)
     */
    public function validatePdfUpload(UploadedFile $file): void
    {
        $maxBytes = (int) env('MAX_PDF_MB', 5) * 1024 * 1024;

        if ($file->getSize() > $maxBytes) {
            throw new HttpException(413, 'Ukuran berkas PDF melebihi batas maksimal 5 MB.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            throw new HttpException(422, 'Berkas harus berformat PDF.');
        }

        $mime = $file->getMimeType();
        if ($mime !== 'application/pdf') {
            throw new HttpException(422, 'Tipe berkas tidak valid, harus application/pdf.');
        }

        // Cek magic bytes (%PDF-)
        $handle = fopen($file->getRealPath(), 'rb');
        $bytes = fread($handle, 5);
        fclose($handle);

        if ($bytes !== '%PDF-') {
            throw new HttpException(422, 'Berkas bukan dokumen PDF yang valid.');
        }
    }

    /**
     * Buat berkas PDF memo resmi saat reservasi gedung disetujui.
     * Disimpan di storage privat: storage/app/private/memo/
     */
    public function generateMemoPdf(Reservasi $reservasi, string $nomorMemo): string
    {
        $filename = 'memo_' . Str::uuid() . '.pdf';
        $relativeDir = 'memo/' . date('Y');
        $fullPath = $relativeDir . '/' . $filename;

        $html = view('pdf.memo', [
            'reservasi' => $reservasi,
            'nomorMemo' => $nomorMemo,
            'lokasi'    => $reservasi->lokasi,
            'pengguna'  => $reservasi->pengguna,
            'tanggal'   => now()->translatedFormat('d F Y'),
            'dicabut'   => false,
        ])->render();

        // Ukuran landscape memanjang ke samping (80mm x 45mm = 226.77 pt x 127.56 pt)
        $customPaper = [0, 0, 226.77, 127.56];
        $pdf = Pdf::loadHTML($html)->setPaper($customPaper);
        $content = $pdf->output();

        Storage::disk('local')->put('private/' . $fullPath, $content);

        return $fullPath;
    }

    /**
     * Perbarui memo menjadi 'tidak_berlaku' saat terjadi penggusuran atau pembatalan,
     * serta catat riwayat pencabutan di tabel memo_riwayat.
     */
    public function stampMemoTidakBerlaku(Memo $memo, ?string $alasan = null, ?string $olehId = null): void
    {
        $memo->update([
            'status'       => 'tidak_berlaku',
            'dicabut_pada' => now(),
            'alasan_cabut' => $alasan,
        ]);

        MemoRiwayat::create([
            'memo_id' => $memo->id,
            'aksi'    => 'dicabut',
            'oleh'    => $olehId,
            'alasan'  => $alasan,
        ]);
    }

    /**
     * Buat versi PDF bertanda "TIDAK BERLAKU" untuk unduhan memo yang dicabut.
     */
    public function renderMemoOutput(Memo $memo): string
    {
        $reservasi = $memo->reservasi()->with(['lokasi', 'pengguna'])->first();

        $html = view('pdf.memo', [
            'reservasi'    => $reservasi,
            'nomorMemo'    => $memo->nomor_memo,
            'lokasi'       => $reservasi->lokasi,
            'pengguna'     => $reservasi->pengguna,
            'tanggal'      => $memo->diterbitkan_pada->translatedFormat('d F Y'),
            'dicabut'      => $memo->status === 'tidak_berlaku',
            'dicabutPada'  => $memo->dicabut_pada?->translatedFormat('d F Y H:i WIB'),
            'alasanCabut'  => $memo->alasan_cabut,
        ])->render();

        // Ukuran landscape memanjang ke samping (80mm x 45mm = 226.77 pt x 127.56 pt)
        $customPaper = [0, 0, 226.77, 127.56];
        $pdf = Pdf::loadHTML($html)->setPaper($customPaper);

        return $pdf->output();
    }
}

