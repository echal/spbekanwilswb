<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realisasi_tik', function (Blueprint $table) {
            $table->id();

            // FK ke item rencana — satu item bisa punya banyak entri realisasi bertahap
            $table->foreignId('rencana_item_id')
                ->constrained('rencana_tik_items')
                ->cascadeOnDelete();

            // Data realisasi
            $table->unsignedInteger('jumlah_realisasi');
            $table->date('tanggal_realisasi');
            $table->string('nomor_dokumen')->nullable();

            // Eviden
            $table->string('eviden_file')->nullable();   // path di storage
            $table->string('eviden_url')->nullable();    // link eksternal (Google Drive, dll)
            $table->text('keterangan')->nullable();

            // Workflow verifikasi
            $table->enum('status_verifikasi', ['menunggu', 'disetujui', 'ditolak'])
                ->default('menunggu')
                ->index();
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamp('diverifikasi_at')->nullable();

            // Audit trail
            $table->foreignId('dibuat_oleh')->constrained('users')->cascadeOnDelete();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Composite index untuk query dashboard
            $table->index(['rencana_item_id', 'status_verifikasi']);
            $table->index(['tanggal_realisasi', 'status_verifikasi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisasi_tik');
    }
};
