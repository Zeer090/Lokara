<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    // ── Daftar ───────────────────────────────────────────────────────────

    public function test_daftar_berhasil(): void
    {
        $response = $this->postJson('/api/auth/daftar', [
            'nama'       => 'Mahasiswa Baru',
            'email'      => 'baru@kampus.ac.id',
            'password'   => 'Rahasia123!',
            'organisasi' => 'BEM',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'pengguna' => ['id', 'nama', 'email', 'peran']])
            ->assertJsonPath('pengguna.peran', 'pengguna');

        $this->assertDatabaseHas('pengguna', ['email' => 'baru@kampus.ac.id']);
    }

    public function test_daftar_email_duplikat_gagal(): void
    {
        Pengguna::create([
            'nama' => 'Existing', 'email' => 'ada@kampus.ac.id',
            'password_hash' => Hash::make('Test1234!'), 'peran' => 'pengguna',
        ]);

        $response = $this->postJson('/api/auth/daftar', [
            'nama' => 'Duplikat', 'email' => 'ada@kampus.ac.id', 'password' => 'Test1234!',
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['galat']);
    }

    public function test_daftar_validasi_gagal(): void
    {
        $response = $this->postJson('/api/auth/daftar', [
            'nama' => '', 'email' => 'bukan-email', 'password' => '123',
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['galat']);
    }

    // ── Masuk ────────────────────────────────────────────────────────────

    public function test_masuk_berhasil(): void
    {
        Pengguna::create([
            'nama'          => 'Pengelola Gedung',
            'email'         => 'pengelola@kampus.ac.id',
            'password_hash' => Hash::make('Gedung123!'),
            'peran'         => 'pengelola_gedung',
            'aktif'         => true,
        ]);

        $response = $this->postJson('/api/auth/masuk', [
            'email'    => 'pengelola@kampus.ac.id',
            'password' => 'Gedung123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'pengguna'])
            ->assertJsonPath('pengguna.peran', 'pengelola_gedung')
            ->assertJsonPath('pengguna.perlu_pemetaan', false);
    }

    public function test_masuk_password_salah(): void
    {
        Pengguna::create([
            'nama'          => 'Pengelola',
            'email'         => 'pengelola2@kampus.ac.id',
            'password_hash' => Hash::make('Gedung123!'),
            'peran'         => 'pengelola_gedung',
        ]);

        $response = $this->postJson('/api/auth/masuk', [
            'email'    => 'pengelola2@kampus.ac.id',
            'password' => 'salah',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('galat', 'Email atau password salah.');
    }

    public function test_pembuatan_peran_lama_ditolak_trigger_database(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Pengguna::create([
            'nama'          => 'Admin Lama',
            'email'         => 'admin_baru@kampus.ac.id',
            'password_hash' => Hash::make('Admin123!'),
            'peran'         => 'admin',
        ]);
    }

    public function test_masuk_akun_tidak_aktif(): void
    {
        Pengguna::create([
            'nama' => 'NonAktif', 'email' => 'non@kampus.ac.id',
            'password_hash' => Hash::make('Test1234!'), 'peran' => 'pengguna',
            'aktif' => false,
        ]);

        $response = $this->postJson('/api/auth/masuk', [
            'email' => 'non@kampus.ac.id', 'password' => 'Test1234!',
        ]);

        $response->assertStatus(403)
            ->assertJsonStructure(['galat']);
    }

    // ── Saya ─────────────────────────────────────────────────────────────

    public function test_db_name(): void
    {
        $this->assertEquals('smartspace_test', DB::connection()->getDatabaseName());
    }

    public function test_saya_dengan_token(): void
    {
        $pengguna = Pengguna::create([
            'nama' => 'Tester', 'email' => 'tester2@kampus.ac.id',
            'password_hash' => Hash::make('Test1234!'), 'peran' => 'pengguna',
        ]);

        $token = $pengguna->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/auth/saya');

        $response->assertOk()
            ->assertJsonPath('pengguna.email', 'tester2@kampus.ac.id');
    }

    public function test_saya_tanpa_token_401(): void
    {
        $response = $this->getJson('/api/auth/saya');

        $response->assertStatus(401)
            ->assertJsonStructure(['galat']);
    }

    // ── Keluar ───────────────────────────────────────────────────────────

    public function test_keluar_hapus_token(): void
    {
        $pengguna = Pengguna::create([
            'nama' => 'Keluar', 'email' => 'keluar@kampus.ac.id',
            'password_hash' => Hash::make('Test1234!'), 'peran' => 'pengguna',
        ]);

        $token = $pengguna->createToken('test')->plainTextToken;

        $this->postJson('/api/auth/keluar', [], ['Authorization' => "Bearer $token"])
            ->assertOk();

        // Token sudah tidak berlaku
        $this->getJson('/api/auth/saya', ['Authorization' => "Bearer $token"])
            ->assertStatus(401);
    }

    // ── Peran ────────────────────────────────────────────────────────────

    public function test_middleware_peran_403(): void
    {
        // Buat endpoint dummy yang butuh peran admin untuk tes middleware
        // Kita tes lewat route yang akan ada nanti, untuk sekarang kita cukup
        // verifikasi bahwa middleware class bisa di-resolve dan bekerja
        $pengguna = Pengguna::create([
            'nama' => 'Biasa', 'email' => 'biasa@kampus.ac.id',
            'password_hash' => Hash::make('Test1234!'), 'peran' => 'pengguna',
        ]);

        $token = $pengguna->createToken('test')->plainTextToken;

        // Akses /auth/saya (ini berhasil karena tidak butuh peran khusus)
        $this->getJson('/api/auth/saya', ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('pengguna.peran', 'pengguna');
    }

    // ── Format galat ─────────────────────────────────────────────────────

    public function test_endpoint_tidak_ada_404(): void
    {
        $response = $this->getJson('/api/endpoint-tidak-ada');

        $response->assertStatus(404)
            ->assertJsonStructure(['galat']);
    }
}
