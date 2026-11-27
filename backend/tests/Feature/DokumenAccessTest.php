<?php

namespace Tests\Feature;

use App\Models\LogAksesDokumen;
use App\Models\Lokasi;
use App\Models\Memo;
use App\Models\PengelolaLokasi;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\SlotTerkunci;
use App\Models\Surat;
use App\Services\DocumentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DokumenAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected Pengguna $pemilik;
    protected Pengguna $penggunaLain;
    protected Pengguna $pengelolaGedung;
    protected Pengguna $pengelolaLain;
    protected Pengguna $pengelolaSistem;
    protected Lokasi $gedung;
    protected Reservasi $reservasi;
    protected Surat $surat;
    protected Memo $memo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->pemilik = Pengguna::create([
            'nama'          => 'Pemilik Surat',
            'email'         => 'pemilik_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Pw123!'),
            'peran'         => 'pengguna',
            'aktif'         => true,
        ]);

        $this->penggunaLain = Pengguna::create([
            'nama'          => 'Orang Lain',
            'email'         => 'lain_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Pw123!'),
            'peran'         => 'pengguna',
            'aktif'         => true,
        ]);

        $this->pengelolaGedung = Pengguna::create([
            'nama'          => 'Pengelola Resmi',
            'email'         => 'pengelola_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Pw123!'),
            'peran'         => 'pengelola_gedung',
            'aktif'         => true,
        ]);

        $this->pengelolaLain = Pengguna::create([
            'nama'          => 'Pengelola Luar',
            'email'         => 'luar_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Pw123!'),
            'peran'         => 'pengelola_gedung',
            'aktif'         => true,
        ]);

        $this->pengelolaSistem = Pengguna::create([
            'nama'          => 'Admin Sistem IT',
            'email'         => 'it_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Pw123!'),
            'peran'         => 'pengelola_sistem',
            'aktif'         => true,
        ]);

        $this->gedung = Lokasi::create([
            'nama'      => 'Gedung Uji Dokumen ' . uniqid(),
            'jenis'     => 'gedung',
            'kapasitas' => 300,
            'aktif'     => true,
        ]);

        PengelolaLokasi::create([
            'pengguna_id'     => $this->pengelolaGedung->id,
            'lokasi_id'       => $this->gedung->id,
            'ditugaskan_oleh' => $this->pengelolaSistem->id,
        ]);

        // Simpan file surat dummy di storage
        $filename = 'surat_test_' . uniqid() . '.pdf';
        Storage::disk('local')->put('private/surat/' . $filename, "%PDF-1.4\nTest Surat Content");

        $this->surat = Surat::create([
            'pengguna_id' => $this->pemilik->id,
            'nama_asli'   => 'surat_permohonan.pdf',
            'path_berkas' => 'surat/' . $filename,
            'ukuran_byte' => 1024,
        ]);

        $this->reservasi = Reservasi::create([
            'kode'              => 'RES-DOK-' . rand(1000, 9999),
            'pengguna_id'       => $this->pemilik->id,
            'lokasi_id'         => $this->gedung->id,
            'surat_id'          => $this->surat->id,
            'keperluan'         => 'Uji Akses Dokumen',
            'mulai'             => Carbon::now()->addDays(5)->setTime(8, 0),
            'selesai'           => Carbon::now()->addDays(5)->setTime(12, 0),
            'jenis_penggunaan'  => 'event_kegiatan',
            'alur_persetujuan'  => 'manual',
            'status'            => 'disetujui',
        ]);

        SlotTerkunci::create([
            'reservasi_id' => $this->reservasi->id,
            'lokasi_id'    => $this->gedung->id,
            'rentang'      => "[{$this->reservasi->mulai->toIso8601String()}, {$this->reservasi->selesai->toIso8601String()})",
            'sumber'       => 'reservasi',
        ]);

        $this->memo = Memo::create([
            'reservasi_id'    => $this->reservasi->id,
            'nomor_memo'      => 'MEMO/2026/' . rand(1000, 9999),
            'jalur_berkas'    => null,
            'status'          => 'berlaku',
            'diterbitkan_oleh'=> $this->pengelolaGedung->id,
            'diterbitkan_pada'=> now(),
        ]);
    }

    public function test_pemilik_dapat_mengunduh_surat_dan_tercatat_di_log(): void
    {
        $response = $this->actingAs($this->pemilik)
            ->get("/api/surat/{$this->surat->id}/unduh");

        $response->assertOk();

        $this->assertDatabaseHas('log_akses_dokumen', [
            'pengguna_id'   => $this->pemilik->id,
            'jenis_dokumen' => 'surat',
            'dokumen_id'    => $this->surat->id,
            'aksi'          => 'unduh',
            'hasil'         => 'diizinkan',
        ]);
    }

    public function test_pengelola_berwenang_dapat_mengunduh_surat(): void
    {
        $response = $this->actingAs($this->pengelolaGedung)
            ->get("/api/surat/{$this->surat->id}/unduh");

        $response->assertOk();

        $this->assertDatabaseHas('log_akses_dokumen', [
            'pengguna_id'   => $this->pengelolaGedung->id,
            'jenis_dokumen' => 'surat',
            'hasil'         => 'diizinkan',
        ]);
    }

    public function test_pengguna_lain_ditolak_mengunduh_surat_dan_tercatat_ditolak(): void
    {
        $response = $this->actingAs($this->penggunaLain)
            ->getJson("/api/surat/{$this->surat->id}/unduh");

        $response->assertStatus(403);

        $this->assertDatabaseHas('log_akses_dokumen', [
            'pengguna_id'   => $this->penggunaLain->id,
            'jenis_dokumen' => 'surat',
            'hasil'         => 'ditolak',
        ]);
    }

    public function test_pengelola_sistem_ditolak_mengunduh_surat_dan_memo(): void
    {
        // 1. Coba unduh surat
        $resSurat = $this->actingAs($this->pengelolaSistem)
            ->getJson("/api/surat/{$this->surat->id}/unduh");

        $resSurat->assertStatus(403);

        $this->assertDatabaseHas('log_akses_dokumen', [
            'pengguna_id'   => $this->pengelolaSistem->id,
            'jenis_dokumen' => 'surat',
            'hasil'         => 'ditolak',
        ]);

        // 2. Coba unduh memo
        $resMemo = $this->actingAs($this->pengelolaSistem)
            ->getJson("/api/memo/{$this->memo->id}/unduh");

        $resMemo->assertStatus(403);

        $this->assertDatabaseHas('log_akses_dokumen', [
            'pengguna_id'   => $this->pengelolaSistem->id,
            'jenis_dokumen' => 'memo',
            'hasil'         => 'ditolak',
        ]);
    }

    public function test_unduh_memo_dicabut_menampilkan_stempel_tidak_berlaku(): void
    {
        // Cabut memo
        $this->memo->update([
            'status'       => 'tidak_berlaku',
            'dicabut_pada' => now(),
            'alasan_cabut' => 'Gedung direnovasi darurat',
        ]);

        $response = $this->actingAs($this->pemilik)
            ->get("/api/memo/{$this->memo->id}/unduh");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseHas('log_akses_dokumen', [
            'pengguna_id'   => $this->pemilik->id,
            'jenis_dokumen' => 'memo',
            'hasil'         => 'diizinkan',
        ]);
    }
}
