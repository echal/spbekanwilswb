<?php

namespace App\Filament\Widgets;

use App\Services\SpbeQuarterlyPerformanceService;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

/**
 * Grafik tren kinerja TW I–IV untuk tahun terpilih (Pekerjaan D).
 * Semua nilai dari SpbeQuarterlyPerformanceService::trenTahunan(); widget tidak menghitung rumus.
 */
class SpbeTrenKinerjaChart extends ChartWidget
{
    protected ?string $heading = 'Tren Kinerja per Triwulan';

    protected static ?int $sort = 2;

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    // Reactive: nilai ikut berubah saat filter tahun/triwulan di DashboardSpbe diganti (hanya dibaca)
    #[Reactive]
    public ?int $tahun = null;

    // Triwulan terpilih hanya dipakai sebagai penanda titik, bukan untuk menyaring rangkaian
    #[Reactive]
    public ?int $triwulan = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Auditor']) ?? false;
    }

    protected function tren(): array
    {
        return app(SpbeQuarterlyPerformanceService::class)->trenTahunan($this->tahun ?? now()->year);
    }

    public function getHeading(): ?string
    {
        return 'Tren Kinerja per Triwulan ' . ($this->tahun ?? now()->year);
    }

    public function getDescription(): ?string
    {
        $tren = $this->tren();
        $titik = collect($tren['titik']);

        $teks = 'Rangkaian TW I–IV dalam satu tahun (kumulatif 1 Januari s.d. akhir triwulan), bukan perbandingan dengan baseline 2025. '
            . 'Triwulan yang belum berjalan tidak digambar; triwulan berjalan bertanda "sementara". '
            . 'Komponen Aplikasi dihitung dari status akses saat ini sehingga titik triwulan lalu bukan snapshot historis terverifikasi. '
            . 'Garis per komponen dapat diaktifkan melalui legenda.';

        if (filled($tren['peringatan_target'])) {
            return $tren['peringatan_target'] . ' ' . $teks;
        }

        if ($titik->every(fn (array $t) => $t['capaian_induk'] === null && $t['target_kumulatif'] === null)) {
            return 'Belum ada data capaian maupun target untuk tahun ini. ' . $teks;
        }

        return $teks;
    }

    protected function getData(): array
    {
        $titik = collect($this->tren()['titik']);
        $terpilih = $this->triwulan;

        $label = $titik->map(fn (array $t) => $t['label'] . match ($t['status_periode']) {
            SpbeQuarterlyPerformanceService::PERIODE_BERJALAN => ' (sementara)',
            SpbeQuarterlyPerformanceService::PERIODE_BELUM    => ' (belum berjalan)',
            default                                           => '',
        })->all();

        // Penanda triwulan terpilih: titik lebih besar pada seri capaian induk
        $ukuranTitik = $titik->map(fn (array $t) => $t['tw'] === $terpilih ? 7 : 4)->all();

        $warnaKomponen = [
            'jaringan'  => '#0ea5e9',
            'aplikasi'  => '#8b5cf6',
            'server'    => '#f59e0b',
            'perangkat' => '#10b981',
        ];

        $komponen = collect(SpbeQuarterlyPerformanceService::KOMPONEN)
            ->map(fn (string $nama, string $kategori) => [
                'label'           => "Capaian {$nama} (%)",
                'data'            => $titik->map(fn (array $t) => $t['komponen'][$kategori] ?? null)->all(),
                'borderColor'     => $warnaKomponen[$kategori] ?? '#94a3b8',
                'backgroundColor' => $warnaKomponen[$kategori] ?? '#94a3b8',
                'borderWidth'     => 1.5,
                'pointRadius'     => 3,
                'spanGaps'        => false,
                'hidden'          => true,
            ])
            ->values()
            ->all();

        return [
            'datasets' => [
                [
                    'label'           => 'Target Kinerja Kumulatif (%)',
                    'data'            => $titik->pluck('target_kumulatif')->all(),
                    'borderColor'     => '#64748b',
                    'backgroundColor' => '#64748b',
                    'borderDash'      => [6, 4],
                    'borderWidth'     => 2,
                    'pointRadius'     => 3,
                    'spanGaps'        => false,
                ],
                [
                    'label'           => 'Capaian Indikator Induk (%)',
                    'data'            => $titik->pluck('capaian_induk')->all(),
                    'borderColor'     => '#2563eb',
                    'backgroundColor' => '#2563eb',
                    'borderWidth'     => 3,
                    'pointRadius'     => $ukuranTitik,
                    'pointHoverRadius' => 8,
                    'spanGaps'        => false,
                    // Data tambahan untuk tooltip (bukan seri tersendiri)
                    'capaianKinerja'  => $titik->pluck('capaian_kinerja')->all(),
                ],
                ...$komponen,
            ],
            'labels' => $label,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        // suggestedMax tanpa batas maksimum tetap: nilai di atas 100% tetap utuh
        return RawJs::make(<<<'JS'
            {
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 100,
                        ticks: { callback: (value) => value + '%' },
                        title: { display: true, text: 'Persen (%)' },
                    },
                },
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const nilai = context.parsed.y;
                                return context.dataset.label + ': ' + (nilai === null ? '–' : nilai.toFixed(2) + '%');
                            },
                            afterLabel: (context) => {
                                const kinerja = context.dataset.capaianKinerja;
                                if (! kinerja) {
                                    return '';
                                }
                                const nilai = kinerja[context.dataIndex];
                                return 'Capaian Kinerja: ' + (nilai === null ? '–' : nilai.toFixed(2) + '%');
                            },
                        },
                    },
                },
            }
        JS);
    }
}
