<?php

namespace Tests\Feature;

use App\Models\LaporanFasilitas;
use App\Models\Lokasi;
use App\Models\Pengaturan;
use App\Models\PengelolaLokasi;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\Surat;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemManagerTest extends TestCase
{
    use DatabaseTransactions;

    protected Pengguna $sistem;
    protected Pengguna $pengguna;
    protected Pengguna $pengelolaGedung;
    protected Lokasi $gedung;
    protected Lokasi $ruangan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sistem = Pengguna::create([
            'nama'          => 'Admin Super',
            'email'         => 'super_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Pw123!'),
            'peran'         => 'pengelola_sistem',
            'aktif'         => true,
        ]);

        $this->pengguna = Pengguna::create([
            'nama'          => 'User Aktif',
            'email'         => 'user_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Pw123!'),
            'peran'         => 'pengguna',
            'aktif'         => true,
        ]);

        $this->pengelolaGedung = Pengguna::create([
            'nama'          => 'Calon Pengelola',
            'email'         => 'calon_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Pw123!'),
            'peran'         => 'pengelola_gedung',
            'aktif'         => true,
        ]);

        $this->gedung = Lokasi::create([
            'nama'      => 'Gedung Rektorat ' . uniqid(),
            'jenis'     => 'gedung',
            'kapasitas' => 200,
            'aktif'     => true,
        ]);

        $this->ruangan = Lokasi::create([
            'nama'      => 'Ruang Teori ' . uniqid(),
            'jenis'     => 'ruangan',
            'kapasitas' => 50,
            'aktif'     => true,
        ]);
    }

    public function test_promosi_peran_ditolak_jika_pengguna_punya_reservasi_aktif(): void
    {
        // Buat reservasi aktif untuk $this->pengguna
        Reservasi::create([
            'kode'             => 'RES-AKT-' . rand(1000, 9999),
            'pengguna_id'      => $this->pengguna->id,
            'lokasi_id'        => $this->ruangan->id,
            'keperluan'        => 'Kuliah Pengganti',
            'mulai'            => Carbon::now()->addDays(2),
            'selesai'          => Carbon::now()->addDays(2)->addHours(2),
            'jenis_penggunaan' => 'rutin',
            'alur_persetujuan' => 'otomatis',
            'status'           => 'disetujui',
        ]);

        $response = $this->actingAs($this->sistem)->postJson("/api/sistem/pengguna/{$this->pengguna->id}/peran", [
            'peran' => 'pengelola_gedung',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('galat', 'Pengguna masih memiliki reservasi aktif (menunggu/disetujui). Selesaikan atau batalkan terlebih dahulu.');
    }

    public function test_penugasan_pengelola_ke_ruangan_ditolak(): void
    {
        $response = $this->actingAs($this->sistem)->postJson('/api/sistem/penugasan', [
            'pengguna_id' => $this->pengelolaGedung->id,
            'lokasi_id'   => $this->ruangan->id, // RUANGAN!
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('galat', 'Penugasan pengelola hanya berlaku untuk gedung, bukan ruangan.');
    }

    public function test_penugasan_pengelola_ke_gedung_berhasil(): void
    {
        $response = $this->actingAs($this->sistem)->postJson('/api/sistem/penugasan', [
            'pengguna_id' => $this->pengelolaGedung->id,
            'lokasi_id'   => $this->gedung->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('pengelola_lokasi', [
            'pengguna_id' => $this->pengelolaGedung->id,
            'lokasi_id'   => $this->gedung->id,
        ]);
    }

    public function test_gedung_tanpa_pengelola_menampilkan_jumlah_pengajuan_tanpa_privat(): void
    {
        // Gedung belum ditugaskan
        $surat = Surat::create([
            'pengguna_id' => $this->pengguna->id,
            'nama_asli'   => 'surat.pdf',
            'path_berkas' => 'surat/dummy.pdf',
            'ukuran_byte' => 500,
        ]);

        Reservasi::create([
            'kode'             => 'RES-BLM-' . rand(1000, 9999),
            'pengguna_id'      => $this->pengguna->id,
            'lokasi_id'        => $this->gedung->id,
            'surat_id'         => $surat->id,
            'keperluan'        => 'Kegiatan Rahasia BEM',
            'mulai'            => Carbon::now()->addDays(5),
            'selesai'          => Carbon::now()->addDays(5)->addHours(3),
            'jenis_penggunaan' => 'event_kegiatan',
            'alur_persetujuan' => 'manual',
            'status'           => 'menunggu',
        ]);

        $response = $this->actingAs($this->sistem)->getJson('/api/sistem/gedung-tanpa-pengelola');

        $response->assertOk();

        $item = collect($response->json())->firstWhere('id', $this->gedung->id);
        $this->assertNotNull($item);
        $this->assertEquals(1, $item['pengajuan_menunggu']);
        // Pastikan tidak ada detail privat
        $this->assertArrayNotHasKey('keperluan', $item);
        $this->assertArrayNotHasKey('pengguna', $item);
    }

    public function test_laporan_fasilitas_tersimpan_setelah_konfirmasi(): void
    {
        // 1. Tanpa konfirmasi -> 422
        $resGagal = $this->postJson('/api/laporan-fasilitas', [
            'lokasi_id'  => $this->ruangan->id,
            'ringkasan'  => 'AC tidak dingin',
            'isi'        => 'AC di ruangan tidak dingin',
            'konfirmasi' => false,
        ]);

        $resGagal->assertStatus(422);

        // 2. Dengan konfirmasi -> 201
        $resSukses = $this->postJson('/api/laporan-fasilitas', [
            'lokasi_id'  => $this->ruangan->id,
            'ringkasan'  => 'AC rusak dan berbunyi',
            'isi'        => 'AC tidak dingin dan berbunyi bising',
            'konfirmasi' => true,
            'anonim'     => true,
        ]);

        $resSukses->assertStatus(201)
            ->assertJsonPath('laporan.status', 'baru');

        $this->assertDatabaseHas('laporan_fasilitas', [
            'lokasi_id' => $this->ruangan->id,
            'ringkasan' => 'AC rusak dan berbunyi',
            'status'    => 'baru',
        ]);
    }
}
