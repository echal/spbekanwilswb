<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_jaringan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jaringan_id')->constrained('jaringan')->cascadeOnDelete();
            $table->date('tanggal_monitoring');
            $table->integer('jumlah_pengguna')->default(0);
            $table->integer('jumlah_perangkat')->default(0);
            $table->decimal('upload_speed', 10, 2)->nullable();
            $table->decimal('download_speed', 10, 2)->nullable();
            $table->decimal('ping', 10, 2)->nullable();
            $table->string('status_koneksi');
            $table->text('kendala')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_jaringan');
    }
};
