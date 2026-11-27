<?php

namespace Tests\Feature;

use App\Models\KasusPenggusuran;
use App\Models\Lokasi;
use App\Models\Memo;
use App\Models\Pengaturan;
use App\Models\PengelolaLokasi;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\SlotTerkunci;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReservationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected Pengguna $pengguna;
    protected Pengguna $pengelolaGedung;
    protected Pengguna $pengelolaLain;
    protected Pengguna $pengelolaSistem;
    protected Lokasi $ruangan;
    protected Lokasi $gedung;
    protected Lokasi $gedungLain;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        Pengaturan::updateOrCreate(['kunci' => 'wa_admin_nomor'], ['nilai' => '6281234567890']);

        $this->pengguna = Pengguna::create([
            'nama'          => 'Mahasiswa BEM',
            'email'         => 'bem_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Password123!'),
            'peran'         => 'pengguna',
            'organisasi'    => 'BEM Polinela',
            'aktif'         => true,
        ]);

        $this->pengelolaGedung = Pengguna::create([
            'nama'          => 'Bu Eci Pengelola GSG',
            'email'         => 'eci_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Password123!'),
            'peran'         => 'pengelola_gedung',
            'aktif'         => true,
        ]);

        $this->pengelolaLain = Pengguna::create([
            'nama'          => 'Pak Budi Pengelola Gedung Lain',
            'email'         => 'budi_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Password123!'),
            'peran'         => 'pengelola_gedung',
            'aktif'         => true,
        ]);

        $this->pengelolaSistem = Pengguna::create([
            'nama'          => 'Admin Sistem IT',
            'email'         => 'it_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Password123!'),
            'peran'         => 'pengelola_sistem',
            'aktif'         => true,
        ]);

        $this->ruangan = Lokasi::create([
            'nama'      => 'Lab Komputer ' . uniqid(),
            'jenis'     => 'ruangan',
            'kapasitas' => 40,
            'fasilitas' => ['AC', 'PC', 'Proyektor'],
            'aktif'     => true,
        ]);

        $this->gedung = Lokasi::create([
            'nama'      => 'Gedung Serbaguna ' . uniqid(),
            'jenis'     => 'gedung',
            'kapasitas' => 500,
            'fasilitas' => ['Sound System', 'Panggung', 'Kursi'],
            'aktif'     => true,
        ]);

        $this->gedungLain = Lokasi::create([
            'nama'      => 'Gedung Pertemuan ' . uniqid(),
            'jenis'     => 'gedung',
            'kapasitas' => 200,
            'fasilitas' => ['AC'],
            'aktif'     => true,
        ]);

        // Tugaskan pengelolaGedung ke $this->gedung
        PengelolaLokasi::create([
            'pengguna_id'     => $this->pengelolaGedung->id,
            'lokasi_id'       => $this->gedung->id,
            'ditugaskan_oleh' => $this->pengelolaSistem->id,
        ]);

        // Tugaskan pengelolaLain ke $this->gedungLain
        PengelolaLokasi::create([
            'pengguna_id'     => $this->pengelolaLain->id,
            'lokasi_id'       => $this->gedungLain->id,
            'ditugaskan_oleh' => $this->pengelolaSistem->id,
        ]);
    }

    protected function fakePdf(string $name = 'surat.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nTest PDF Valid Content");
    }

    public function test_reservasi_ruangan_otomatis_disetujui_dan_kunci_slot(): void
    {
        $mulai = Carbon::now()->addDays(2)->setTime(8, 0)->toIso8601String();
        $selesai = Carbon::now()->addDays(2)->setTime(10, 0)->toIso8601String();

        $response = $this->actingAs($this->pengguna)
            ->postJson('/api/reservasi', [
                'lokasi_id'      => $this->ruangan->id,
                'keperluan'      => 'Kuliah Praktikum Algoritma',
                'mulai'          => $mulai,
                'selesai'        => $selesai,
                'jumlah_peserta' => 35,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('reservasi.status', 'disetujui')
            ->assertJsonPath('reservasi.jenis_penggunaan', 'rutin')
            ->assertJsonPath('reservasi.alur_persetujuan', 'otomatis')
            ->assertJsonPath('reservasi.surat_id', null);

        $id = $response->json('reservasi.id');

        // Pastikan slot terkunci dibuat dalam transaksi yang sama
        $this->assertDatabaseHas('slot_terkunci', [
            'reservasi_id' => $id,
            'lokasi_id'    => $this->ruangan->id,
        ]);
    }

    public function test_reservasi_ruangan_dengan_surat_wajib_ditolak_422(): void
    {
        $mulai = Carbon::now()->addDays(2)->setTime(10, 0)->toIso8601String();
        $selesai = Carbon::now()->addDays(2)->setTime(12, 0)->toIso8601String();

        $pdf = $this->fakePdf();

        $response = $this->actingAs($this->pengguna)
            ->post('/api/reservasi', [
                'lokasi_id' => $this->ruangan->id,
                'keperluan' => 'Mencoba kirim surat untuk ruangan',
                'mulai'     => $mulai,
                'selesai'   => $selesai,
                'surat'     => $pdf,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonPath('galat', 'Reservasi ruangan tidak memerlukan surat.');

        // Pastikan tidak ada reservasi yang tersimpan
        $this->assertDatabaseMissing('reservasi', [
            'lokasi_id' => $this->ruangan->id,
            'keperluan' => 'Mencoba kirim surat untuk ruangan',
        ]);
    }

    public function test_reservasi_ruangan_bentrok_ditolak_409(): void
    {
        $mulai = Carbon::now()->addDays(3)->setTime(8, 0)->toIso8601String();
        $selesai = Carbon::now()->addDays(3)->setTime(11, 0)->toIso8601String();

        // Reservasi pertama sukses
        $this->actingAs($this->pengguna)->postJson('/api/reservasi', [
            'lokasi_id' => $this->ruangan->id,
            'keperluan' => 'Sesi Pertama',
            'mulai'     => $mulai,
            'selesai'   => $selesai,
        ])->assertStatus(201);

        // Pengguna lain mencoba pesan rentang yang beririsan
        $pengguna2 = Pengguna::create([
            'nama'          => 'Mahasiswa Lain',
            'email'         => 'lain_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Pw123!'),
            'peran'         => 'pengguna',
            'aktif'         => true,
        ]);

        $irisanMulai = Carbon::now()->addDays(3)->setTime(9, 30)->toIso8601String();
        $irisanSelesai = Carbon::now()->addDays(3)->setTime(12, 0)->toIso8601String();

        $response = $this->actingAs($pengguna2)->postJson('/api/reservasi', [
            'lokasi_id' => $this->ruangan->id,
            'keperluan' => 'Sesi Bentrok',
            'mulai'     => $irisanMulai,
            'selesai'   => $irisanSelesai,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('galat', 'Jadwal yang dipilih bentrok dengan jadwal ruangan yang sudah terisi.');
    }

    public function test_pengelola_tidak_dapat_mengajukan_reservasi(): void
    {
        $mulai = Carbon::now()->addDays(4)->setTime(8, 0)->toIso8601String();
        $selesai = Carbon::now()->addDays(4)->setTime(10, 0)->toIso8601String();

        $response = $this->actingAs($this->pengelolaGedung)
            ->postJson('/api/reservasi', [
                'lokasi_id' => $this->ruangan->id,
                'keperluan' => 'Coba pesan dari akun pengelola',
                'mulai'     => $mulai,
                'selesai'   => $selesai,
            ]);

        // Ditolak peran middleware atau service dengan 403
        $response->assertStatus(403);
    }

    public function test_reservasi_gedung_tanpa_surat_ditolak_422(): void
    {
        $mulai = Carbon::now()->addDays(5)->setTime(8, 0)->toIso8601String();
        $selesai = Carbon::now()->addDays(5)->setTime(12, 0)->toIso8601String();

        $response = $this->actingAs($this->pengguna)->postJson('/api/reservasi', [
            'lokasi_id' => $this->gedung->id,
            'keperluan' => 'Seminar Tanpa Surat',
            'mulai'     => $mulai,
            'selesai'   => $selesai,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('galat', 'Reservasi gedung wajib mengunggah berkas surat permohonan format PDF.');
    }

    public function test_reservasi_gedung_dengan_surat_berstatus_menunggu(): void
    {
        $mulai = Carbon::now()->addDays(5)->setTime(8, 0)->toIso8601String();
        $selesai = Carbon::now()->addDays(5)->setTime(12, 0)->toIso8601String();
        $pdf = $this->fakePdf('surat_permohonan.pdf');

        $response = $this->actingAs($this->pengguna)->post('/api/reservasi', [
            'lokasi_id' => $this->gedung->id,
            'keperluan' => 'Seminar Nasional Teknologi',
            'mulai'     => $mulai,
            'selesai'   => $selesai,
            'surat'     => $pdf,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('reservasi.status', 'menunggu')
            ->assertJsonPath('reservasi.jenis_penggunaan', 'event_kegiatan')
            ->assertJsonPath('reservasi.alur_persetujuan', 'manual');

        $id = $response->json('reservasi.id');

        // Belum disetujui, sehingga belum mengunci slot
        $this->assertDatabaseMissing('slot_terkunci', [
            'reservasi_id' => $id,
        ]);
    }

    public function test_pengelola_berwenang_menyetujui_gedung_memo_terbit_dan_slot_terkunci(): void
    {
        $mulai = Carbon::now()->addDays(6)->setTime(8, 0)->toIso8601String();
        $selesai = Carbon::now()->addDays(6)->setTime(15, 0)->toIso8601String();
        $pdf = $this->fakePdf();

        $res = $this->actingAs($this->pengguna)->post('/api/reservasi', [
            'lokasi_id' => $this->gedung->id,
            'keperluan' => 'Wisuda Jurusan',
            'mulai'     => $mulai,
            'selesai'   => $selesai,
            'surat'     => $pdf,
        ], ['Accept' => 'application/json']);

        $reservasiId = $res->json('reservasi.id');

        // Disetujui oleh pengelola berwenang ($this->pengelolaGedung)
        $setujuRes = $this->actingAs($this->pengelolaGedung)
            ->postJson("/api/pengelola/reservasi/{$reservasiId}/setuju");

        $setujuRes->assertOk()
            ->assertJsonPath('reservasi.status', 'disetujui');

        // Cek slot terkunci ada
        $this->assertDatabaseHas('slot_terkunci', [
            'reservasi_id' => $reservasiId,
            'lokasi_id'    => $this->gedung->id,
        ]);

        // Cek memo terbit
        $this->assertDatabaseHas('memo', [
            'reservasi_id' => $reservasiId,
            'status'       => 'berlaku',
        ]);
    }

    public function test_pengelola_tidak_berwenang_ditolak_403(): void
    {
        $mulai = Carbon::now()->addDays(7)->setTime(8, 0)->toIso8601String();
        $selesai = Carbon::now()->addDays(7)->setTime(12, 0)->toIso8601String();
        $pdf = $this->fakePdf();

        $res = $this->actingAs($this->pengguna)->post('/api/reservasi', [
            'lokasi_id' => $this->gedung->id,
            'keperluan' => 'Dies Natalis',
            'mulai'     => $mulai,
            'selesai'   => $selesai,
            'surat'     => $pdf,
        ], ['Accept' => 'application/json']);

        $reservasiId = $res->json('reservasi.id');

        // Dicoba disetujui oleh pengelola gedung lain ($this->pengelolaLain)
        $setujuRes = $this->actingAs($this->pengelolaLain)
            ->postJson("/api/pengelola/reservasi/{$reservasiId}/setuju");

        $setujuRes->assertStatus(403);
    }

    public function test_pengelola_sistem_dilarang_menyetujui_reservasi(): void
    {
        $mulai = Carbon::now()->addDays(8)->setTime(8, 0)->toIso8601String();
        $selesai = Carbon::now()->addDays(8)->setTime(12, 0)->toIso8601String();
        $pdf = $this->fakePdf();

        $res = $this->actingAs($this->pengguna)->post('/api/reservasi', [
            'lokasi_id' => $this->gedung->id,
            'keperluan' => 'Pekan Olahraga',
            'mulai'     => $mulai,
            'selesai'   => $selesai,
            'surat'     => $pdf,
        ], ['Accept' => 'application/json']);

        $reservasiId = $res->json('reservasi.id');

        $setujuRes = $this->actingAs($this->pengelolaSistem)
            ->postJson("/api/pengelola/reservasi/{$reservasiId}/setuju");

        $setujuRes->assertStatus(403);
    }

    public function test_acara_kampus_menggusur_reservasi_secara_transaksional(): void
    {
        $mulai = Carbon::now()->addDays(10)->setTime(8, 0)->toIso8601String();
        $selesai = Carbon::now()->addDays(10)->setTime(16, 0)->toIso8601String();
        $pdf = $this->fakePdf();

        // 1. Buat dan setujui reservasi
        $res = $this->actingAs($this->pengguna)->post('/api/reservasi', [
            'lokasi_id' => $this->gedung->id,
            'keperluan' => 'Seminar BEM yang akan tergusur',
            'mulai'     => $mulai,
            'selesai'   => $selesai,
            'surat'     => $pdf,
        ], ['Accept' => 'application/json']);

        $reservasiId = $res->json('reservasi.id');
        $this->actingAs($this->pengelolaGedung)->postJson("/api/pengelola/reservasi/{$reservasiId}/setuju")->assertOk();

        $memoId = Memo::where('reservasi_id', $reservasiId)->value('id');

        // 2. Pengelola gedung membuat Acara Kampus mendesak pada jadwal yang beririsan
        $acaraRes = $this->actingAs($this->pengelolaGedung)->postJson('/api/pengelola/acara-kampus', [
            'lokasi_id' => $this->gedung->id,
            'nama'      => 'Kunjungan Kerja Menteri Pendidikan',
            'mulai'     => $mulai,
            'selesai'   => $selesai,
            'alasan'    => 'Kunjungan kenegaraan mendadak oleh Menteri',
        ]);

        $acaraRes->assertStatus(201)
            ->assertJsonPath('jumlah_tergusur', 1);

        // 3. Verifikasi status reservasi menjadi 'tergusur'
        $this->assertDatabaseHas('reservasi', [
            'id'     => $reservasiId,
            'status' => 'tergusur',
        ]);

        // 4. Verifikasi slot reservasi dihapus dan slot acara kampus tersimpan
        $this->assertDatabaseMissing('slot_terkunci', [
            'reservasi_id' => $reservasiId,
        ]);

        // 5. Verifikasi memo menjadi 'tidak_berlaku'
        $this->assertDatabaseHas('memo', [
            'id'     => $memoId,
            'status' => 'tidak_berlaku',
        ]);

        // 6. Verifikasi kasus penggusuran dibuat dengan alasan yang sama persis
        $this->assertDatabaseHas('kasus_penggusuran', [
            'reservasi_id'       => $reservasiId,
            'alasan_penggusuran' => 'Kunjungan kenegaraan mendadak oleh Menteri',
            'status'             => 'terbuka',
        ]);
    }
}
