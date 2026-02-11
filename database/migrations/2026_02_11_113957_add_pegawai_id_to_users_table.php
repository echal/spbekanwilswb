<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah pegawai_id ke users (relasi baru: user → pegawai)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('pegawai_id')->nullable()->after('managed_unit_kerja_id')
                ->constrained('pegawai')->nullOnDelete();
        });

        // Hapus user_id dari pegawai (relasi lama dipindahkan)
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('unit_kerja_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pegawai_id');
        });
    }
};
