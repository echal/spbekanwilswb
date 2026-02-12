<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aplikasi', function (Blueprint $table) {
            $table->string('url_aplikasi')->nullable()->after('tingkat_kritis');
        });
    }

    public function down(): void
    {
        Schema::table('aplikasi', function (Blueprint $table) {
            $table->dropColumn('url_aplikasi');
        });
    }
};
