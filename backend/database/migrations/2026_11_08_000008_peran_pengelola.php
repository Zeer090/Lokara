<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 008_peran_pengelola
 *
 * Menambahkan dua peran baru: pengelola_gedung dan pengelola_sistem.
 * Berdiri sendiri di luar transaksi ($withinTransaction = false).
 * Lihat docs/02_database_postgresql.md bagian 5.1.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TYPE peran_pengguna ADD VALUE IF NOT EXISTS 'pengelola_gedung'");
        DB::statement("ALTER TYPE peran_pengguna ADD VALUE IF NOT EXISTS 'pengelola_sistem'");
    }

    public function down(): void
    {
        // Nilai enum pada PostgreSQL tidak dapat dihapus dengan aman via DROP VALUE.
    }
};

