<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unit_kerja_id')->nullable()->after('remember_token')
                ->constrained('unit_kerja')->nullOnDelete();
        });

        $tables = ['pegawai', 'perangkat', 'aplikasi', 'server_cloud', 'jaringan', 'monitoring_jaringan', 'pegawai_aplikasi', 'unit_kerja', 'ruangan'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tables = ['pegawai', 'perangkat', 'aplikasi', 'server_cloud', 'jaringan', 'monitoring_jaringan', 'pegawai_aplikasi', 'unit_kerja', 'ruangan'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
                $table->dropConstrainedForeignId('updated_by');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_kerja_id');
        });
    }
};
