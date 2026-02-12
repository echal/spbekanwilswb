<?php

namespace App\Services;

use App\Models\RencanaTikItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SpbeEvaluationService
{
    /**
     * Capaian global SPBE per tahun.
     */
    public function getGlobalProgress(int $tahun): array
    {
        return Cache::remember("spbe_global_{$tahun}", 300, function () use ($tahun) {
            $result = RencanaTikItem::query()
                ->join('rencana_tik', 'rencana_tik.id', '=', 'rencana_tik_items.rencana_tik_id')
                ->where('rencana_tik.tahun', $tahun)
                ->selectRaw('COALESCE(SUM(jumlah_direncanakan), 0) as total_direncanakan')
                ->selectRaw('COALESCE(SUM(jumlah_terpenuhi), 0) as total_terpenuhi')
                ->first();

            $direncanakan = (int) $result->total_direncanakan;
            $terpenuhi = (int) $result->total_terpenuhi;
            $persentase = $direncanakan > 0
                ? round(($terpenuhi / $direncanakan) * 100, 2)
                : 0;

            return [
                'total_direncanakan' => $direncanakan,
                'total_terpenuhi' => $terpenuhi,
                'persentase' => $persentase,
                'warna' => $this->getWarna($persentase),
            ];
        });
    }

    /**
     * Breakdown capaian per kategori.
     */
    public function getByKategori(int $tahun): Collection
    {
        return Cache::remember("spbe_kategori_{$tahun}", 300, function () use ($tahun) {
            return RencanaTikItem::query()
                ->join('rencana_tik', 'rencana_tik.id', '=', 'rencana_tik_items.rencana_tik_id')
                ->where('rencana_tik.tahun', $tahun)
                ->groupBy('rencana_tik_items.kategori')
                ->select(
                    'rencana_tik_items.kategori',
                    DB::raw('COALESCE(SUM(jumlah_direncanakan), 0) as total_direncanakan'),
                    DB::raw('COALESCE(SUM(jumlah_terpenuhi), 0) as total_terpenuhi'),
                )
                ->get()
                ->map(function ($row) {
                    $row->persentase = $row->total_direncanakan > 0
                        ? round(($row->total_terpenuhi / $row->total_direncanakan) * 100, 2)
                        : 0;

                    return $row;
                });
        });
    }

    /**
     * Breakdown capaian per unit kerja.
     */
    public function getByUnit(int $tahun): Collection
    {
        return Cache::remember("spbe_unit_{$tahun}", 300, function () use ($tahun) {
            return RencanaTikItem::query()
                ->join('rencana_tik', 'rencana_tik.id', '=', 'rencana_tik_items.rencana_tik_id')
                ->join('unit_kerja', 'unit_kerja.id', '=', 'rencana_tik_items.unit_kerja_id')
                ->where('rencana_tik.tahun', $tahun)
                ->groupBy('rencana_tik_items.unit_kerja_id', 'unit_kerja.nama_unit')
                ->select(
                    'unit_kerja.nama_unit as unit_kerja',
                    DB::raw('COALESCE(SUM(jumlah_direncanakan), 0) as total_direncanakan'),
                    DB::raw('COALESCE(SUM(jumlah_terpenuhi), 0) as total_terpenuhi'),
                )
                ->get()
                ->map(function ($row) {
                    $row->persentase = $row->total_direncanakan > 0
                        ? round(($row->total_terpenuhi / $row->total_direncanakan) * 100, 2)
                        : 0;

                    return $row;
                })
                ->sortByDesc('persentase')
                ->values();
        });
    }

    /**
     * Tentukan warna berdasarkan persentase.
     */
    protected function getWarna(float $persentase): string
    {
        return match (true) {
            $persentase >= 80 => 'success',
            $persentase >= 60 => 'warning',
            default => 'danger',
        };
    }
}
