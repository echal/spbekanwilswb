<?php

namespace App\Services;

use App\Models\RealisasiTik;
use App\Models\RencanaTikItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SpbeEvaluationService
{
    public function __construct(
        protected SpbeQuarterlyPerformanceService $kinerja,
    ) {}

    /**
     * Capaian global SPBE per tahun (kumulatif s.d. 31 Desember tahun terpilih).
     *
     * Komponen   = rincian target & realisasi per komponen beserta satuannya
     *              (tidak dijumlahkan lintas komponen karena satuannya berbeda)
     * Persentase = rata-rata sederhana capaian 4 komponen (null bila ada komponen tanpa target)
     */
    public function getGlobalProgress(int $tahun): array
    {
        $progress = $this->kinerja->getAnnualProgress($tahun);
        $persentase = $progress['persentase_rata_rata'];

        return [
            'komponen'              => $progress['komponen'],
            'persentase'            => $persentase,
            'komponen_tanpa_target' => $progress['komponen_tanpa_target'],
            'peringatan_target'     => $progress['peringatan_target'],
            'warna'                 => $this->kinerja->progressWarna($persentase),
        ];
    }

    /**
     * Breakdown capaian per kategori (komponen) yang memiliki target, dari sumber yang sama dengan KPI tahunan.
     */
    public function getByKategori(int $tahun): Collection
    {
        return collect($this->kinerja->getAnnualProgress($tahun)['komponen'])
            ->filter(fn (array $k) => $k['target_tahunan'] > 0)
            ->map(fn (array $k, string $kategori) => (object) [
                'kategori'           => $kategori,
                'total_direncanakan' => $k['target_tahunan'],
                'total_terpenuhi'    => $k['realisasi_kumulatif'],
                'persentase'         => $k['persentase'],
            ])
            ->values();
    }

    /**
     * Breakdown capaian per unit kerja.
     *
     * Komponen Aplikasi dikecualikan sementara: target Aplikasi belum dialokasikan per unit,
     * sedangkan pegawai penggunanya tersebar di banyak unit.
     */
    public function getByUnit(int $tahun): Collection
    {
        $kecuali = SpbeQuarterlyPerformanceService::KOMPONEN_BERBASIS_AKSES;

        return Cache::remember(SpbeQuarterlyPerformanceService::cacheKey("spbe_unit_{$tahun}"), 300, function () use ($tahun, $kecuali) {
            // X3: hanya dari satu rencana sumber target; nol atau lebih dari satu rencana → tidak ada data
            $rencanaId = $this->kinerja->sumberTarget($tahun)['rencana_id'];

            if ($rencanaId === null) {
                return collect();
            }

            // Direncanakan per unit
            $direncanakan = RencanaTikItem::query()
                ->join('rencana_tik', 'rencana_tik.id', '=', 'rencana_tik_items.rencana_tik_id')
                ->join('unit_kerja', 'unit_kerja.id', '=', 'rencana_tik_items.unit_kerja_id')
                ->where('rencana_tik_items.rencana_tik_id', $rencanaId)
                ->where('rencana_tik_items.kategori', '!=', $kecuali)
                ->groupBy('rencana_tik_items.unit_kerja_id', 'unit_kerja.nama_unit')
                ->select(
                    'unit_kerja.nama_unit as unit_kerja',
                    'rencana_tik_items.unit_kerja_id',
                    DB::raw('COALESCE(SUM(rencana_tik_items.jumlah_direncanakan), 0) as total_direncanakan'),
                )
                ->get()
                ->keyBy('unit_kerja_id');

            // Terpenuhi per unit (realisasi disetujui, bertanggal di tahun kalender terpilih)
            $terpenuhi = RealisasiTik::query()
                ->join('rencana_tik_items', 'rencana_tik_items.id', '=', 'realisasi_tik.rencana_item_id')
                ->where('rencana_tik_items.rencana_tik_id', $rencanaId)
                ->where('realisasi_tik.status_verifikasi', 'disetujui')
                ->where('rencana_tik_items.kategori', '!=', $kecuali)
                ->tap(fn ($query) => SpbeQuarterlyPerformanceService::filterTanggalInklusif(
                    $query, 'realisasi_tik.tanggal_realisasi', sprintf('%04d-01-01', $tahun), sprintf('%04d-12-31', $tahun),
                ))
                ->groupBy('rencana_tik_items.unit_kerja_id')
                ->select(
                    'rencana_tik_items.unit_kerja_id',
                    DB::raw('COALESCE(SUM(realisasi_tik.jumlah_realisasi), 0) as total_terpenuhi'),
                )
                ->get()
                ->keyBy('unit_kerja_id');

            return $direncanakan->map(function ($row) use ($terpenuhi) {
                $d = (int) $row->total_direncanakan;
                $t = (int) ($terpenuhi->get($row->unit_kerja_id)?->total_terpenuhi ?? 0);

                return (object) [
                    'unit_kerja'         => $row->unit_kerja,
                    'total_direncanakan' => $d,
                    'total_terpenuhi'    => $t,
                    'persentase'         => $d > 0 ? round(($t / $d) * 100, 2) : 0.0,
                ];
            })
            ->sortByDesc('persentase')
            ->values();
        });
    }
}
