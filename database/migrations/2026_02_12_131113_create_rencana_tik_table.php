<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rencana_tik', function (Blueprint $table) {
            $table->id();
            $table->integer('tahun')->index();
            $table->string('nama_rencana');
            $table->text('keterangan')->nullable();
            $table->enum('status', ['draft', 'disetujui', 'final'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rencana_tik');
    }
};
