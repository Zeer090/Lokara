<?php

namespace Database\Seeders;

use App\Models\Lokasi;
use App\Models\PengelolaLokasi;
use App\Models\Pengguna;
use Illuminate\Database\Seeder;

class PengelolaLokasiSeeder extends Seeder
{
    public function run(): void
    {
        $buEci = Pengguna::where('email', 'pengelola_gedung@kampus.ac.id')->first();
        $sistem = Pengguna::where('email', 'sistem@kampus.ac.id')->first();

        if (! $buEci || ! $sistem) {
            return;
        }

        $gedungDitugaskan = ['Gedung Serbaguna', 'Gedung A', 'Gedung B'];

        foreach ($gedungDitugaskan as $namaGedung) {
            $lokasi = Lokasi::where('nama', $namaGedung)->first();
            if ($lokasi) {
                PengelolaLokasi::firstOrCreate(
                    [
                        'pengguna_id' => $buEci->id,
                        'lokasi_id' => $lokasi->id,
                    ],
                    [
                        'ditugaskan_oleh' => $sistem->id,
                    ]
                );
            }
        }
    }
}

