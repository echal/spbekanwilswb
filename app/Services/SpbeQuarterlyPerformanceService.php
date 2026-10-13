<?php

namespace App\Services;

use App\Models\Aplikasi;
use App\Models\PegawaiAplikasi;
use App\Models\RealisasiTik;
use App\Models\RencanaTik;
use App\Models\RencanaTikItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Perhitungan capaian Indikator 186 per komponen, kumulatif per triwulan.
 *
 * Aturan:
 * - Realisasi dihitung bila status_verifikasi = 'disetujui' dan tanggal_realisasi berada
 *   di antara 1 Januari tahun terpilih s.d. akhir triwulan (inklusif). diverifikasi_at tidak dipakai.
 * - Realisasi bertanggal di luar tahun terpilih tidak memengaruhi capaian tahun tersebut.
 * - Target tahunan komponen = SUM(jumlah_direncanakan) item Rencana TIK tahun terpilih
 *   berstatus disetujui/final, dikelompokkan per kategori.
 * - Pengecualian komponen Aplikasi: realisasi = jumlah pegawai unik dengan akses aplikasi
 *   berstatus Aktif (pegawai_aplikasi) yang tanggal_diberikan-nya tidak melewati akhir periode,
 *   termasuk akses sebelum tahun terpilih. Realisasi manual kategori aplikasi tidak dipakai.
 * - Capaian komponen = realisasi kumulatif / target tahunan x 100 (tidak dibatasi 100%).
 * - Capaian indikator = rata-rata sederhana capaian keempat komponen.
 */
class SpbeQuarterlyPerformanceService
{
    /**
     * Komponen Indikator 186 → nilai enum rencana_tik_items.kategori.
     */
    public const KOMPONEN = [
        'jaringan'  => 'Jaringan',
        'aplikasi'  => 'Aplikasi',
        'server'    => 'Cloud Server',
        'perangkat' => 'Perangkat Pegawai',
    ];

    /**
     * Komponen yang realisasinya dihitung dari akses pegawai, bukan dari realisasi_tik.
     */
    public const KOMPONEN_BERBASIS_AKSES = 'aplikasi';

    /**
     * Satuan tampilan per komponen. Selain Aplikasi, satuan mengikuti isian Rencana TIK
     * karena definisinya belum ditetapkan secara resmi.
     */
    public const SATUAN = [
        'jaringan'  => 'satuan sesuai Rencana TIK',
        'aplikasi'  => 'pegawai dengan akses aktif',
        'server'    => 'satuan sesuai Rencana TIK',
        'perangkat' => 'satuan sesuai Rencana TIK (definisi masih diaudit)',
    ];

    public const PERIODE_SELESAI = 'selesai';

    public const PERIODE_BERJALAN = 'berjalan';

    public const PERIODE_BELUM = 'belum';

    protected const CACHE_TTL = 300;

    protected const CACHE_VERSION_KEY = 'spbe_cache_version';

    // ----------------------------------------------------------------
    // PUBLIC API
    // ----------------------------------------------------------------

    /**
     * Capaian tahunan = kumulatif s.d. 31 Desember tahun terpilih.
     */
    public function getAnnualProgress(int $year): array
    {
        return $this->getQuarterProgress($year, 4);
    }

    /**
     * Capaian kumulatif 1 Januari s.d. akhir triwulan.
     *
     * @return array{
     *   tahun: int,
     *   quarter: int,
     *   triwulan: string,
     *   quarter_label: string,
     *   periode_awal: string,
     *   periode_akhir: string,
     *   komponen: array<string, array{label: string, satuan: string, target_tahunan: int, realisasi_kumulatif: ?int, persentase: ?float}>,
     *   komponen_tanpa_target: array<int, string>,
     *   persentase_rata_rata: ?float,
     *   persentase_rata_rata_presisi: ?float,
     *   target_kumulatif: ?float,
     *   target_kumulatif_baseline: ?float,
     *   capaian_kinerja: ?float,
     *   jumlah_rencana_memenuhi_syarat: int,
     *   peringatan_target: ?string,
     *   status_periode: string,
     * }
     *
     * persentase_rata_rata (dibulatkan) dan target_kumulatif_baseline dipertahankan untuk kompatibilitas;
     * persentase_rata_rata_presisi, target_kumulatif, dan capaian_kinerja bernilai presisi penuh.
     * Bila status_periode = 'belum', realisasi_kumulatif, persentase, persentase_rata_rata(_presisi),
     * dan capaian_kinerja bernilai null.
     */
    public function getQuarterProgress(int $year, int $quarter): array
    {
        $key = $this->cacheKey("spbe_kpi_{$year}_tw{$quarter}");

        $hasil = Cache::remember($key, self::CACHE_TTL, function () use ($year, $quarter) {
            [$awal, $akhir] = $this->cumulativeDateRange($year, $quarter);

            // X3: target hanya diambil bila tepat satu rencana memenuhi syarat (tanpa penjumlahan/fallback)
            $sumber = $this->sumberTarget($year);
            $target = $this->targetTahunanPerKomponen($sumber['rencana_id']);
            $realisasi = $this->realisasiKumulatifPerKomponen($year, $awal, $akhir);

            // Aplikasi: pegawai unik dengan akses aktif s.d. akhir periode (tanpa batas awal)
            $realisasi[self::KOMPONEN_BERBASIS_AKSES] = $this->penggunaAplikasiUnik($akhir);

            $komponen = [];
            foreach (self::KOMPONEN as $kategori => $label) {
                $t = (int) ($target[$kategori] ?? 0);
                $r = (int) ($realisasi[$kategori] ?? 0);

                $komponen[$kategori] = [
                    'label'               => $label,
                    'satuan'              => self::SATUAN[$kategori],
                    'target_tahunan'      => $t,
                    'realisasi_kumulatif' => $r,
                    // Target 0 / tidak ada: capaian tidak dapat dihitung (bukan 0%)
                    'persentase'          => $t > 0 ? round($r / $t * 100, 2) : null,
                ];
            }

            $tanpaTarget = collect($komponen)
                ->filter(fn (array $k) => $k['target_tahunan'] <= 0)
                ->pluck('label')
                ->values()
                ->all();

            $indukPresisi = $this->rataRataKomponen($komponen, $target, $realisasi);
            $targetKumulatif = $this->targetKumulatif($year, $quarter);

            return [
                'tahun'                     => $year,
                'quarter'                   => $quarter,
                'triwulan'                  => $this->quarterRoman($quarter),
                'quarter_label'             => $this->quarterLabel($quarter),
                'periode_awal'              => $awal,
                'periode_akhir'             => $akhir,
                'komponen'                  => $komponen,
                'komponen_tanpa_target'     => $tanpaTarget,
                // Kompatibilitas: dibulatkan 2 desimal; perhitungan turunan memakai *_presisi
                'persentase_rata_rata'      => $indukPresisi === null ? null : round($indukPresisi, 2),
                'persentase_rata_rata_presisi' => $indukPresisi,
                'target_kumulatif'          => $targetKumulatif,
                'target_kumulatif_baseline' => $this->targetKumulatifBaseline($year, $quarter),
                'capaian_kinerja'           => $this->capaianKinerja($indukPresisi, $targetKumulatif),
                'jumlah_rencana_memenuhi_syarat' => $sumber['jumlah'],
                'peringatan_target'         => $sumber['peringatan'],
            ];
        });

        // Status periode dihitung di luar cache karena bergantung pada tanggal hari ini
        $hasil['status_periode'] = $this->statusPeriode($year, $quarter);

        // Triwulan yang belum berjalan tidak boleh tampil seolah-olah memiliki realisasi (nol / posisi hari ini)
        if ($hasil['status_periode'] === self::PERIODE_BELUM) {
            foreach ($hasil['komponen'] as $kategori => $k) {
                $hasil['komponen'][$kategori]['realisasi_kumulatif'] = null;
                $hasil['komponen'][$kategori]['persentase'] = null;
            }

            $hasil['persentase_rata_rata'] = null;
            $hasil['persentase_rata_rata_presisi'] = null;
            $hasil['capaian_kinerja'] = null;
        }

        return $hasil;
    }

    /**
     * Penanda waktu triwulan terhadap tanggal hari ini (bukan status persetujuan/realisasi):
     * `selesai` (sudah berakhir), `berjalan` (mencakup hari ini), `belum` (belum dimulai).
     */
    public function statusPeriode(int $tahun, int $tw): string
    {
        $hariIni = now()->toDateString();
        $awal = sprintf('%04d-%02d-01', $tahun, ($tw - 1) * 3 + 1);
        $akhir = $this->akhirTriwulan($tahun, $tw);

        return match (true) {
            $hariIni < $awal  => self::PERIODE_BELUM,
            $hariIni > $akhir => self::PERIODE_SELESAI,
            default           => self::PERIODE_BERJALAN,
        };
    }

    /**
     * Nilai awal filter triwulan: tahun kalender berjalan → triwulan berjalan; tahun lain → TW I.
     */
    public function triwulanAwal(int $tahun): int
    {
        $sekarang = now();

        return $tahun === $sekarang->year ? $this->getQuarterFromDate($sekarang) : 1;
    }

    /**
     * Tanggal akhir triwulan (Y-m-d), dipakai sebagai batas posisi kumulatif.
     */
    public function akhirTriwulan(int $tahun, int $tw): string
    {
        return $this->cumulativeDateRange($tahun, $tw)[1];
    }

    /**
     * Baris tabel capaian per komponen untuk triwulan terpilih (Pekerjaan C).
     * Persentase diambil langsung dari getQuarterProgress() sehingga sama dengan dasar KPI.
     *
     * realisasi_periode = posisi kumulatif akhir TW terpilih − posisi kumulatif akhir TW sebelumnya
     * (boleh negatif). Status realisasi periode:
     * - `tersedia`             : dari realisasi bertanggal (Jaringan, Cloud Server, Perangkat)
     * - `turunan`              : Aplikasi TW II–IV, diturunkan dari status akses saat ini (bukan snapshot terverifikasi)
     * - `belum_terverifikasi`  : Aplikasi TW I, posisi pembanding 31 Desember tahun sebelumnya tidak dapat dibuktikan
     * - `belum_berjalan`       : triwulan belum dimulai
     *
     * @return array{
     *   tahun: int,
     *   triwulan: string,
     *   quarter: int,
     *   periode_akhir: string,
     *   status_periode: string,
     *   peringatan_target: ?string,
     *   komponen: array<int, array{kategori: string, label: string, satuan: string, target_tahunan: ?int,
     *     realisasi_kumulatif: ?int, realisasi_periode: ?int, status_realisasi_periode: string,
     *     persentase: ?float, sumber: string}>,
     * }
     */
    public function getKomponenTriwulan(int $tahun, int $tw): array
    {
        $saatIni = $this->getQuarterProgress($tahun, $tw);
        $sebelumnya = $tw > 1 ? $this->getQuarterProgress($tahun, $tw - 1) : null;
        $belum = $saatIni['status_periode'] === self::PERIODE_BELUM;

        $baris = [];
        foreach ($saatIni['komponen'] as $kategori => $k) {
            $berbasisAkses = $kategori === self::KOMPONEN_BERBASIS_AKSES;

            [$periode, $statusPeriode] = match (true) {
                $belum => [null, 'belum_berjalan'],
                $berbasisAkses && $sebelumnya === null => [null, 'belum_terverifikasi'],
                $berbasisAkses => [$k['realisasi_kumulatif'] - (int) $sebelumnya['komponen'][$kategori]['realisasi_kumulatif'], 'turunan'],
                default => [$k['realisasi_kumulatif'] - (int) ($sebelumnya['komponen'][$kategori]['realisasi_kumulatif'] ?? 0), 'tersedia'],
            };

            $baris[] = [
                'kategori'                 => $kategori,
                'label'                    => $k['label'],
                'satuan'                   => $k['satuan'],
                'target_tahunan'           => $k['target_tahunan'] > 0 ? $k['target_tahunan'] : null,
                'realisasi_kumulatif'      => $k['realisasi_kumulatif'],
                'realisasi_periode'        => $periode,
                'status_realisasi_periode' => $statusPeriode,
                'persentase'               => $k['persentase'],
                'sumber'                   => $berbasisAkses
                    ? 'Posisi ASN unik dengan akses aplikasi aktif pada akhir triwulan, dihitung dari status akses saat ini (bukan snapshot historis terverifikasi).'
                    : 'Realisasi TIK berstatus disetujui menurut tanggal realisasi.'
                        . ($kategori === 'perangkat' ? ' Definisi komponen masih diaudit.' : ''),
            ];
        }

        return [
            'tahun'             => $tahun,
            'triwulan'          => $saatIni['triwulan'],
            'quarter'           => $tw,
            'periode_akhir'     => $saatIni['periode_akhir'],
            'status_periode'    => $saatIni['status_periode'],
            'peringatan_target' => $saatIni['peringatan_target'],
            'komponen'          => $baris,
        ];
    }

    /**
     * Seluruh 4 triwulan (masing-masing kumulatif).
     *
     * @return Collection<int, array>
     */
    public function getAllQuarters(int $year): Collection
    {
        return collect([1, 2, 3, 4])->map(fn (int $q) => $this->getQuarterProgress($year, $q));
    }

    /**
     * Rangkaian tren TW I–IV satu tahun (Pekerjaan D), disusun dari getQuarterProgress()
     * sehingga nilainya identik dengan KPI dan tabel komponen. Nilai presisi penuh; null tetap null
     * (termasuk triwulan yang belum berjalan dan target tidak valid/ambigu X3).
     *
     * @return array{
     *   tahun: int,
     *   peringatan_target: ?string,
     *   titik: array<int, array{tw: int, label: string, status_periode: string, capaian_induk: ?float,
     *     target_kumulatif: ?float, capaian_kinerja: ?float, komponen: array<string, ?float>}>,
     * }
     */
    public function trenTahunan(int $tahun): array
    {
        $titik = $this->getAllQuarters($tahun)->map(fn (array $p) => [
            'tw'               => $p['quarter'],
            'label'            => 'TW ' . $p['triwulan'],
            'status_periode'   => $p['status_periode'],
            'capaian_induk'    => $p['persentase_rata_rata_presisi'],
            'target_kumulatif' => $p['target_kumulatif'],
            'capaian_kinerja'  => $p['capaian_kinerja'],
            'komponen'         => collect($p['komponen'])->map(fn (array $k) => $k['persentase'])->all(),
        ])->values()->all();

        return [
            'tahun'             => $tahun,
            'peringatan_target' => $this->sumberTarget($tahun)['peringatan'],
            'titik'             => $titik,
        ];
    }

    /**
     * Triwulan yang ditampilkan untuk tahun terpilih:
     * tahun lampau → TW IV (posisi akhir tahun), tahun berjalan → triwulan berjalan,
     * tahun mendatang → TW I.
     */
    public function quarterForYear(int $year): int
    {
        $sekarang = now();

        return match (true) {
            $year < $sekarang->year => 4,
            $year > $sekarang->year => 1,
            default                 => $this->getQuarterFromDate($sekarang),
        };
    }

    public function getQuarterFromDate(Carbon $date): int
    {
        return (int) ceil($date->month / 3);
    }

    /**
     * Target kumulatif indikator terhadap baseline (dari config/spbe.php), atau null bila belum ditetapkan.
     */
    public function targetKumulatifBaseline(int $year, int $quarter): ?float
    {
        $kumulatif = $this->targetKumulatif($year, $quarter);

        return $kumulatif === null ? null : round($kumulatif, 2);
    }

    /**
     * Target kinerja kumulatif triwulan (%) = jumlah target tambahan TW I s.d. TW terpilih
     * dari config/spbe.php (presisi penuh). Null bila tahun belum memiliki target.
     */
    public function targetKumulatif(int $year, int $quarter): ?float
    {
        $tambahan = config("spbe.target_indikator.{$year}.tambahan_triwulan");

        if (! is_array($tambahan)) {
            return null;
        }

        $kumulatif = 0.0;
        for ($q = 1; $q <= $quarter; $q++) {
            $kumulatif += (float) ($tambahan[$q] ?? 0);
        }

        return $kumulatif;
    }

    /**
     * Capaian Kinerja (%) = capaian indikator induk ÷ target kinerja kumulatif × 100, dari nilai presisi penuh.
     * Tidak dibatasi 100%. Null bila induk tidak tersedia atau target tidak tersedia/≤ 0.
     */
    public function capaianKinerja(?float $indukPresisi, ?float $targetKumulatif): ?float
    {
        if ($indukPresisi === null || $targetKumulatif === null || $targetKumulatif <= 0) {
            return null;
        }

        return $indukPresisi / $targetKumulatif * 100;
    }

    public function progressWarna(?float $persentase): string
    {
        return match (true) {
            $persentase === null => 'gray',
            $persentase >= 80    => 'success',
            $persentase >= 60    => 'warning',
            default              => 'danger',
        };
    }

    /**
     * Kunci cache yang ikut versi data; versi dinaikkan oleh flushCache().
     */
    public static function cacheKey(string $key): string
    {
        return $key . '_v' . Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    /**
     * Membatalkan seluruh cache KPI SPBE (dipanggil saat rencana/realisasi berubah).
     */
    public static function flushCache(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, (int) Cache::get(self::CACHE_VERSION_KEY, 1) + 1);
    }

    // ----------------------------------------------------------------
    // QUERY
    // ----------------------------------------------------------------

    /**
     * X3: sumber target tahunan = tepat satu Rencana TIK berstatus disetujui/final pada tahun tersebut.
     * Nol rencana → target tidak tersedia. Lebih dari satu → target tidak tersedia + peringatan
     * (tidak dijumlahkan dan tidak memilih rencana terbaru).
     *
     * @return array{jumlah: int, rencana_id: ?int, peringatan: ?string}
     */
    public function sumberTarget(int $year): array
    {
        $ids = RencanaTik::query()
            ->where('tahun', $year)
            ->whereIn('status', ['disetujui', 'final'])
            ->pluck('id');

        $jumlah = $ids->count();

        return [
            'jumlah'     => $jumlah,
            'rencana_id' => $jumlah === 1 ? (int) $ids->first() : null,
            'peringatan' => $jumlah > 1
                ? "Terdapat {$jumlah} Rencana TIK berstatus Disetujui/Final untuk tahun {$year}. Target tidak dihitung karena hanya satu rencana yang boleh berlaku."
                : null,
        ];
    }

    /**
     * @return array<string, int> kategori => SUM(jumlah_direncanakan) dari satu rencana sumber target
     */
    protected function targetTahunanPerKomponen(?int $rencanaId): array
    {
        if ($rencanaId === null) {
            return [];
        }

        return RencanaTikItem::query()
            ->where('rencana_tik_items.rencana_tik_id', $rencanaId)
            ->groupBy('rencana_tik_items.kategori')
            ->selectRaw('rencana_tik_items.kategori, COALESCE(SUM(rencana_tik_items.jumlah_direncanakan), 0) AS total')
            ->pluck('total', 'kategori')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Setiap baris realisasi dijumlah tepat sekali (JOIN bersifat many-to-one).
     *
     * @return array<string, int> kategori => SUM(jumlah_realisasi)
     */
    protected function realisasiKumulatifPerKomponen(int $year, string $awal, string $akhir): array
    {
        return RealisasiTik::query()
            ->join('rencana_tik_items', 'rencana_tik_items.id', '=', 'realisasi_tik.rencana_item_id')
            ->join('rencana_tik', 'rencana_tik.id', '=', 'rencana_tik_items.rencana_tik_id')
            ->where('rencana_tik.tahun', $year)
            ->whereIn('rencana_tik.status', ['disetujui', 'final'])
            ->where('realisasi_tik.status_verifikasi', 'disetujui')
            // Realisasi manual kategori aplikasi tetap tersimpan, tetapi bukan sumber pembilang
            ->where('rencana_tik_items.kategori', '!=', self::KOMPONEN_BERBASIS_AKSES)
            ->tap(fn ($query) => static::filterTanggalInklusif($query, 'realisasi_tik.tanggal_realisasi', $awal, $akhir))
            ->groupBy('rencana_tik_items.kategori')
            ->selectRaw('rencana_tik_items.kategori, COALESCE(SUM(realisasi_tik.jumlah_realisasi), 0) AS total')
            ->pluck('total', 'kategori')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Jumlah pegawai unik (pegawai.id) dengan akses aplikasi Aktif yang diberikan s.d. akhir periode.
     * Satu pegawai dengan banyak aplikasi dihitung sekali; semua peran pengguna dihitung.
     */
    public function penggunaAplikasiUnik(string $akhir): int
    {
        return (int) $this->queryAksesAktif($akhir)
            ->selectRaw('COUNT(DISTINCT pegawai.id) AS total')
            ->value('total');
    }

    /**
     * Daftar aplikasi beserta jumlah pegawai unik dengan akses Aktif s.d. akhir periode
     * (kolom `pegawai_akses_aktif`). Aplikasi tanpa pemetaan tetap muncul dengan nilai 0.
     */
    public function queryPenggunaPerAplikasi(string $akhir): \Illuminate\Database\Eloquent\Builder
    {
        return Aplikasi::query()
            ->select('aplikasi.*')
            ->selectSub(
                $this->queryAksesAktif($akhir)
                    ->whereColumn('pegawai_aplikasi.aplikasi_id', 'aplikasi.id')
                    ->selectRaw('COUNT(DISTINCT pegawai.id)')
                    ->toBase(),
                'pegawai_akses_aktif',
            );
    }

    /**
     * Tanggal akhir periode yang ditampilkan untuk tahun terpilih (mengikuti quarterForYear()).
     */
    public function akhirPeriodeTampil(int $year): string
    {
        return $this->cumulativeDateRange($year, $this->quarterForYear($year))[1];
    }

    /**
     * Akses Aktif yang diberikan s.d. akhir periode (inklusif s.d. akhir hari), tanpa batas awal.
     */
    protected function queryAksesAktif(string $akhir): \Illuminate\Database\Eloquent\Builder
    {
        return PegawaiAplikasi::query()
            ->join('pegawai', 'pegawai.id', '=', 'pegawai_aplikasi.pegawai_id')
            ->where('pegawai_aplikasi.status_akses', 'Aktif')
            ->where('pegawai_aplikasi.tanggal_diberikan', '<', Carbon::parse($akhir)->addDay()->toDateString());
    }

    /**
     * Filter tanggal inklusif [awal, akhir] sebagai interval setengah terbuka
     * (>= awal, < hari setelah akhir), sehingga nilai bertanggal akhir periode tetap masuk
     * meskipun tersimpan dengan komponen jam (mis. "2026-03-31 00:00:00"), dan index tetap terpakai.
     */
    public static function filterTanggalInklusif($query, string $kolom, string $awal, string $akhir)
    {
        return $query
            ->where($kolom, '>=', $awal)
            ->where($kolom, '<', Carbon::parse($akhir)->addDay()->toDateString());
    }

    // ----------------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------------

    /**
     * Rata-rata sederhana capaian 4 komponen, dari nilai tidak dibulatkan (hasil presisi penuh).
     * Bila ada komponen tanpa target, rata-rata tidak dihitung (null) agar komponen
     * tidak hilang diam-diam atau dianggap 0%.
     */
    protected function rataRataKomponen(array $komponen, array $target, array $realisasi): ?float
    {
        if (collect($komponen)->contains(fn (array $k) => $k['persentase'] === null)) {
            return null;
        }

        $total = 0.0;
        foreach (array_keys(self::KOMPONEN) as $kategori) {
            $total += (($realisasi[$kategori] ?? 0) / $target[$kategori]) * 100;
        }

        // Presisi penuh; pembulatan dilakukan saat ditampilkan / pada field kompatibilitas
        return $total / count(self::KOMPONEN);
    }

    /**
     * Rentang kumulatif sebagai string tanggal (kolom DATE, inklusif, bebas zona waktu).
     *
     * @return array{string, string}
     */
    protected function cumulativeDateRange(int $year, int $quarter): array
    {
        $akhir = Carbon::create($year, $quarter * 3, 1)->endOfMonth();

        return [sprintf('%04d-01-01', $year), $akhir->toDateString()];
    }

    protected function quarterLabel(int $quarter): string
    {
        return match ($quarter) {
            1 => 'Triwulan I (Jan–Mar)',
            2 => 'Triwulan II (Jan–Jun)',
            3 => 'Triwulan III (Jan–Sep)',
            4 => 'Triwulan IV (Jan–Des)',
            default => "Triwulan {$quarter}",
        };
    }

    protected function quarterRoman(int $quarter): string
    {
        return match ($quarter) {
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            default => (string) $quarter,
        };
    }
}
