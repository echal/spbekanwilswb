<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai_aplikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('aplikasi_id')->constrained('aplikasi')->cascadeOnDelete();
            $table->string('peran_pengguna');
            $table->string('status_akses');
            $table->date('tanggal_diberikan');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['pegawai_id', 'aplikasi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai_aplikasi');
    }
};
