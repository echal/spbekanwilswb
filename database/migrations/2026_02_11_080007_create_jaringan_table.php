<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaringan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jaringan');
            $table->string('provider');
            $table->string('bandwidth');
            $table->string('lokasi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jaringan');
    }
};
