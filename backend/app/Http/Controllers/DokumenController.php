<?php

namespace App\Http\Controllers;

use App\Models\Memo;
use App\Models\PengelolaLokasi;
use App\Models\Reservasi;
use App\Models\Surat;
use App\Services\DocumentService;
use App\Services\PemindaiSuratAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DokumenController extends Controller
{
    public function __construct(protected DocumentService $documentService)
    {
    }

    /**
     * POST /api/dokumen/ai-scan-surat
     * Memindai foto kertas/surat dengan Gemini Vision AI dan menghasilkan PDF rapi via DomPDF.
     */
    public function aiScanSurat(Request $request, PemindaiSuratAiService $pemindai): JsonResponse
    {
        $request->validate([
            'foto' => 'required|image|max:8192',
        ]);

        $hasil = $pemindai->pindaiDanBuatPdf(
            $request->file('foto'),
            $request->user()?->nama
        );

        return response()->json($hasil);
    }

    /**
     * GET /api/surat/{id}/unduh
     * Unduh berkas surat permohonan.
     * Akses: Pemilik reservasi atau pengelola gedung yang berwenang atas gedung itu.
     * Pengelola sistem DITOLAK (HTTP 403).
     * Setiap akses (berhasil maupun ditolak) dicatat ke log_akses_dokumen.
     */
    public function unduhSurat(Request $request, string $id)
    {
        $user = $request->user();
        $surat = Surat::find($id);
        if (! $surat) {
            $surat = Surat::whereHas('reservasi', fn ($q) => $q->where('id', $id))->first();
        }
        if (! $surat) {
            return response()->json(['galat' => 'Dokumen surat tidak ditemukan.'], 404);
        }
        $reservasi = Reservasi::where('surat_id', $surat->id)->first();
        $reservasiId = $reservasi?->id;

        // 1. Pengelola sistem mutlak dilarang
        if ($user->peran === 'pengelola_sistem') {
            $this->documentService->logAccess(
                $user->id,
                'surat',
                $surat->id,
                $reservasiId,
                'unduh',
                'ditolak',
                'Pengelola sistem dilarang mengakses dokumen surat permohonan.',
                $request->ip()
            );

            return response()->json([
                'galat' => 'Pengelola sistem tidak memiliki hak akses terhadap dokumen surat permohonan.',
            ], 403);
        }

        // 2. Periksa kepemilikan atau wewenang pengelola
        $diizinkan = false;

        if ($user->peran === 'pengguna' && $reservasi && $reservasi->pengguna_id === $user->id) {
            $diizinkan = true;
        } elseif ($user->peran === 'pengelola_gedung' && $reservasi) {
            $diizinkan = PengelolaLokasi::where('pengguna_id', $user->id)
                ->where('lokasi_id', $reservasi->lokasi_id)
                ->exists();
        }

        if (! $diizinkan) {
            $this->documentService->logAccess(
                $user->id,
                'surat',
                $surat->id,
                $reservasiId,
                'unduh',
                'ditolak',
                'Pengguna tidak memiliki wewenang atau bukan pemilik dokumen.',
                $request->ip()
            );

            return response()->json([
                'galat' => 'Anda tidak memiliki wewenang untuk mengunduh dokumen surat ini.',
            ], 403);
        }

        // Catat akses berhasil
        $this->documentService->logAccess(
            $user->id,
            'surat',
            $surat->id,
            $reservasiId,
            'unduh',
            'diizinkan',
            null,
            $request->ip()
        );

        $path = 'private/' . $surat->path_berkas;

        if (! Storage::disk('local')->exists($path)) {
            return response()->json([
                'galat' => 'Berkas fisik surat tidak ditemukan pada penyimpanan server.',
            ], 404);
        }

        $fullPath = Storage::disk('local')->path($path);

        return response()->download($fullPath, $surat->nama_asli ?? 'surat_permohonan.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * GET /api/memo/{id}/unduh
     * Unduh berkas memo persetujuan resmi.
     * Bila status memo 'tidak_berlaku', berkas dirender dengan stempel "TIDAK BERLAKU".
     * Akses: Pemilik reservasi atau pengelola gedung yang berwenang atas gedung itu.
     * Pengelola sistem DITOLAK (HTTP 403).
     * Setiap akses (berhasil maupun ditolak) dicatat ke log_akses_dokumen.
     */
    public function unduhMemo(Request $request, string $id)
    {
        $user = $request->user();
        $memo = Memo::find($id) ?? Memo::where('reservasi_id', $id)->first();
        if (! $memo) {
            return response()->json(['galat' => 'Dokumen memo tidak ditemukan.'], 404);
        }
        $reservasi = Reservasi::findOrFail($memo->reservasi_id);

        // 1. Pengelola sistem mutlak dilarang
        if ($user->peran === 'pengelola_sistem') {
            $this->documentService->logAccess(
                $user->id,
                'memo',
                $memo->id,
                $reservasi->id,
                'unduh',
                'ditolak',
                'Pengelola sistem dilarang mengakses dokumen memo persetujuan.',
                $request->ip()
            );

            return response()->json([
                'galat' => 'Pengelola sistem tidak memiliki hak akses terhadap dokumen memo.',
            ], 403);
        }

        // 2. Periksa kepemilikan atau wewenang pengelola
        $diizinkan = false;

        if ($user->peran === 'pengguna' && $reservasi->pengguna_id === $user->id) {
            $diizinkan = true;
        } elseif ($user->peran === 'pengelola_gedung') {
            $diizinkan = PengelolaLokasi::where('pengguna_id', $user->id)
                ->where('lokasi_id', $reservasi->lokasi_id)
                ->exists();
        }

        if (! $diizinkan) {
            $this->documentService->logAccess(
                $user->id,
                'memo',
                $memo->id,
                $reservasi->id,
                'unduh',
                'ditolak',
                'Pengguna tidak memiliki wewenang atau bukan pemilik memo.',
                $request->ip()
            );

            return response()->json([
                'galat' => 'Anda tidak memiliki wewenang untuk mengunduh memo ini.',
            ], 403);
        }

        // Catat akses berhasil
        $this->documentService->logAccess(
            $user->id,
            'memo',
            $memo->id,
            $reservasi->id,
            'unduh',
            'diizinkan',
            null,
            $request->ip()
        );

        $safeNomor = str_replace(['/', '\\'], '_', $memo->nomor_memo);
        $filename = "memo_{$safeNomor}.pdf";

        // Bila dicabut (status tidak_berlaku), buat memo dengan cap batal secara dinamis
        if ($memo->status === 'tidak_berlaku') {
            $pdfContent = $this->documentService->renderMemoOutput($memo);

            return response($pdfContent, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // Jika memo aktif dan berkas fisik ada di storage
        if ($memo->jalur_berkas && Storage::disk('local')->exists('private/' . $memo->jalur_berkas)) {
            $fullPath = Storage::disk('local')->path('private/' . $memo->jalur_berkas);

            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        // Fallback: render memo aktif secara dinamis
        $pdfContent = $this->documentService->renderMemoOutput($memo);

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
