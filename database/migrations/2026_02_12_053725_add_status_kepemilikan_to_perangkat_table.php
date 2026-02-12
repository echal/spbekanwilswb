<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perangkat', function (Blueprint $table) {
            $table->enum('status_kepemilikan', [
                'milik_kantor',
                'milik_pribadi',
                'belum_memiliki',
                'perangkat_bersama'
            ])->default('milik_kantor')->after('pegawai_id');

            // Ubah kode_inventaris menjadi nullable
            $table->string('kode_inventaris')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('perangkat', function (Blueprint $table) {
            $table->dropColumn('status_kepemilikan');

            // Kembalikan kode_inventaris menjadi not null
            $table->string('kode_inventaris')->nullable(false)->change();
        });
    }
};
