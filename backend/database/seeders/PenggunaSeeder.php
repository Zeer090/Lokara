<?php

namespace Database\Seeders;

use App\Models\Pengguna;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data awal pengguna (4 Kategori Akses: pengelola_sistem, pengelola_gedung, pengguna).
 */
class PenggunaSeeder extends Seeder
{
    public function run(): void
    {
        $daftar = [
            [
                'email' => 'sistem@kampus.ac.id',
                'nama' => 'Admin Sistem',
                'peran' => 'pengelola_sistem',
                'organisasi' => 'Bagian TI & Administrasi Sistem',
                'password' => 'Sistem2026!',
            ],
            [
                'email' => 'pengelola_gedung@kampus.ac.id',
                'nama' => 'Bu Eci',
                'peran' => 'pengelola_gedung',
                'organisasi' => 'Tata Usaha / Pengelola Fasilitas',
                'password' => 'Pengelola2026!',
            ],
            [
                'email' => 'pengguna@kampus.ac.id',
                'nama' => 'Mahasiswa Contoh',
                'peran' => 'pengguna',
                'organisasi' => 'HMJ Informatika',
                'password' => 'Pengguna2026!',
            ],
        ];

        foreach ($daftar as $akun) {
            Pengguna::updateOrCreate(
                ['email' => $akun['email']],
                [
                    'nama' => $akun['nama'],
                    'password_hash' => Hash::make($akun['password']),
                    'peran' => $akun['peran'],
                    'organisasi' => $akun['organisasi'],
                    'no_hp' => '08123456789',
                    'aktif' => true,
                ],
            );
        }
    }
}
