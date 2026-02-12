<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rencana_tik_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rencana_tik_id')->constrained('rencana_tik')->cascadeOnDelete();
            $table->enum('kategori', ['perangkat', 'server', 'jaringan', 'aplikasi']);
            $table->foreignId('unit_kerja_id')->constrained('unit_kerja')->cascadeOnDelete();
            $table->string('nama_item');
            $table->integer('jumlah_direncanakan');
            $table->integer('jumlah_terpenuhi')->default(0);
            $table->enum('status_realisasi', ['belum', 'sebagian', 'selesai'])->default('belum');
            $table->enum('prioritas', ['tinggi', 'sedang', 'rendah'])->default('sedang');
            $table->decimal('estimasi_anggaran', 18, 2)->nullable();
            $table->date('tanggal_target')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rencana_tik_items');
    }
};
