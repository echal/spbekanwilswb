<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perangkat', function (Blueprint $table) {
            $table->id();
            $table->string('kode_inventaris')->unique();
            $table->string('jenis_perangkat');
            $table->string('merek');
            $table->string('tipe');
            $table->string('processor')->nullable();
            $table->string('ram')->nullable();
            $table->string('penyimpanan')->nullable();
            $table->string('os')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('kondisi')->default('Baik');
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->foreignId('ruangan_id')->nullable()->constrained('ruangan')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perangkat');
    }
};
