<?php

namespace App\Providers;

use App\Models\Aplikasi;
use App\Models\Pegawai;
use App\Models\PegawaiAplikasi;
use App\Models\RealisasiTik;
use App\Models\RencanaTik;
use App\Models\RencanaTikItem;
use App\Services\SpbeQuarterlyPerformanceService;
use App\Support\IntegritasRealisasi;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lindungi histori realisasi TIK dari perubahan kategori dan penghapusan berantai
        IntegritasRealisasi::daftarkanPengaman();

        // Batalkan cache KPI SPBE setiap kali data rencana/realisasi berubah,
        // agar dashboard tidak menampilkan angka lama hingga TTL cache habis.
        foreach ([RencanaTik::class, RencanaTikItem::class, RealisasiTik::class, PegawaiAplikasi::class] as $model) {
            $model::saved(fn () => SpbeQuarterlyPerformanceService::flushCache());
            $model::deleted(fn () => SpbeQuarterlyPerformanceService::flushCache());
        }

        // Penghapusan pegawai/aplikasi ikut menghapus akses (cascade di database, tanpa event model)
        foreach ([Pegawai::class, Aplikasi::class] as $model) {
            $model::deleted(fn () => SpbeQuarterlyPerformanceService::flushCache());
        }
    }
}
