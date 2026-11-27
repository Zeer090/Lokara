<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal SMARTSPACE AI. Aman dijalankan berulang (idempoten).
     */
    public function run(): void
    {
        $this->call([
            PenggunaSeeder::class,
            LokasiSeeder::class,
            PengelolaLokasiSeeder::class,
            PengaturanSeeder::class,
        ]);
    }
}
