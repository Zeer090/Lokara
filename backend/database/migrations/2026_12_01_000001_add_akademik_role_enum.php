<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        // Add the 'akademik' role to the enum. Use IF NOT EXISTS for safety.
        DB::statement("ALTER TYPE peran_pengguna ADD VALUE IF NOT EXISTS 'akademik'");
    }

    public function down(): void
    {
        // PostgreSQL does not support removing enum values safely.
    }
};

