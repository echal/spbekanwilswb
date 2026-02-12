<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambahkan link_eviden ke tabel perangkat
        if (!Schema::hasColumn('perangkat', 'link_eviden')) {
            Schema::table('perangkat', function (Blueprint $table) {
                $table->string('link_eviden')->nullable()->after('ip_address');
            });
        }

        // Tambahkan link_eviden ke tabel server_cloud
        if (!Schema::hasColumn('server_cloud', 'link_eviden')) {
            Schema::table('server_cloud', function (Blueprint $table) {
                $table->string('link_eviden')->nullable()->after('kategori_data');
            });
        }

        // Tambahkan link_eviden ke tabel monitoring_jaringan
        if (!Schema::hasColumn('monitoring_jaringan', 'link_eviden')) {
            Schema::table('monitoring_jaringan', function (Blueprint $table) {
                $table->string('link_eviden')->nullable()->after('tindak_lanjut');
            });
        }

        // Tambahkan link_eviden ke tabel aplikasi
        if (!Schema::hasColumn('aplikasi', 'link_eviden')) {
            Schema::table('aplikasi', function (Blueprint $table) {
                $table->string('link_eviden')->nullable()->after('tingkat_kritis');
            });
        }
    }

    public function down(): void
    {
        Schema::table('perangkat', function (Blueprint $table) {
            $table->dropColumn('link_eviden');
        });

        Schema::table('server_cloud', function (Blueprint $table) {
            $table->dropColumn('link_eviden');
        });

        Schema::table('monitoring_jaringan', function (Blueprint $table) {
            $table->dropColumn('link_eviden');
        });

        Schema::table('aplikasi', function (Blueprint $table) {
            $table->dropColumn('link_eviden');
        });
    }
};
