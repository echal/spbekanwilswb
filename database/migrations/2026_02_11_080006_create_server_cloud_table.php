<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_cloud', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_layanan');
            $table->string('nama_layanan');
            $table->string('provider');
            $table->string('status_kepemilikan');
            $table->string('nomor_kontrak')->nullable();
            $table->date('masa_berlaku')->nullable();
            $table->integer('jumlah_user')->default(0);
            $table->string('kategori_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_cloud');
    }
};
