<?php

namespace Database\Seeders;

use App\Models\Lokasi;
use Illuminate\Database\Seeder;

/**
 * Data awal lokasi (Data Contoh Sementara bertanda L-01).
 *
 * Variasi kapasitas dan fasilitas sengaja dibuat untuk pengujian
 * pencarian ketersediaan, reservasi ruangan, dan reservasi gedung.
 */
class LokasiSeeder extends Seeder
{
    public function run(): void
    {
        $daftar = [
            [
                'nama' => 'Gedung Serbaguna',
                'jenis' => 'gedung',
                'kapasitas' => 500,
                'fasilitas' => ['proyektor' => true, 'ac' => true, 'sound_system' => true, 'panggung' => true],
                'keterangan' => 'Gedung pertemuan utama (Data Contoh)',
                'sementara' => true,
                'aktif' => true,
            ],
            [
                'nama' => 'Gedung A',
                'jenis' => 'gedung',
                'kapasitas' => 300,
                'fasilitas' => ['proyektor' => true, 'ac' => true, 'sound_system' => true],
                'keterangan' => 'Gedung kuliah bersama A (Data Contoh)',
                'sementara' => true,
                'aktif' => true,
            ],
            [
                'nama' => 'Gedung B',
                'jenis' => 'gedung',
                'kapasitas' => 200,
                'fasilitas' => ['proyektor' => true, 'ac' => true],
                'keterangan' => 'Gedung kuliah B (Data Contoh)',
                'sementara' => true,
                'aktif' => true,
            ],
            [
                'nama' => 'Gedung C',
                'jenis' => 'gedung',
                'kapasitas' => 120,
                'fasilitas' => ['ac' => true, 'papan_tulis' => true],
                'keterangan' => 'Gedung C tanpa pengelola (Data Contoh)',
                'sementara' => true,
                'aktif' => true,
            ],
            [
                'nama' => 'Ruang 101',
                'jenis' => 'ruangan',
                'kapasitas' => 40,
                'fasilitas' => ['proyektor' => true, 'ac' => true],
                'keterangan' => 'Ruang kelas teori lantai 1 (Data Contoh)',
                'sementara' => true,
                'aktif' => true,
            ],
            [
                'nama' => 'Ruang 102',
                'jenis' => 'ruangan',
                'kapasitas' => 30,
                'fasilitas' => ['proyektor' => true],
                'keterangan' => 'Ruang kelas teori lantai 1 (Data Contoh)',
                'sementara' => true,
                'aktif' => true,
            ],
            [
                'nama' => 'Ruang Seminar',
                'jenis' => 'ruangan',
                'kapasitas' => 60,
                'fasilitas' => ['proyektor' => true, 'ac' => true, 'sound_system' => true],
                'keterangan' => 'Ruang seminar akademik (Data Contoh)',
                'sementara' => true,
                'aktif' => true,
            ],
            [
                'nama' => 'Lab Komputer 1',
                'jenis' => 'ruangan',
                'kapasitas' => 35,
                'fasilitas' => ['komputer' => true, 'ac' => true, 'internet' => true],
                'keterangan' => 'Laboratorium komputer praktikum (Data Contoh)',
                'sementara' => true,
                'aktif' => true,
            ],
            [
                'nama' => 'Lab Komputer 2',
                'jenis' => 'ruangan',
                'kapasitas' => 30,
                'fasilitas' => ['komputer' => true, 'proyektor' => true, 'ac' => true],
                'keterangan' => 'Laboratorium multimedia (Data Contoh)',
                'sementara' => true,
                'aktif' => true,
            ],
        ];

        foreach ($daftar as $lokasi) {
            Lokasi::updateOrCreate(['nama' => $lokasi['nama']], $lokasi);
        }
    }
}
