<?php

namespace App\Filament\Widgets;

use App\Services\SpbeQuarterlyPerformanceService;
use Filament\Support\ArrayRecord;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

/**
 * Tabel capaian per komponen (Pekerjaan C). Semua angka berasal dari
 * SpbeQuarterlyPerformanceService::getKomponenTriwulan(); widget hanya memformat tampilan.
 */
class SpbeKomponenTable extends TableWidget
{
    protected static ?int $sort = 1;

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    // Reactive: nilai ikut berubah saat filter tahun/triwulan di DashboardSpbe diganti (hanya dibaca)
    #[Reactive]
    public ?int $tahun = null;

    #[Reactive]
    public ?int $triwulan = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Auditor']) ?? false;
    }

    public function table(Table $table): Table
    {
        $service = app(SpbeQuarterlyPerformanceService::class);
        $tahun = $this->tahun ?? now()->year;
        $tw = $this->triwulan ?? $service->triwulanAwal($tahun);
        $data = $service->getKomponenTriwulan($tahun, $tw);

        $penanda = match ($data['status_periode']) {
            SpbeQuarterlyPerformanceService::PERIODE_BERJALAN => ' (sementara)',
            SpbeQuarterlyPerformanceService::PERIODE_BELUM    => ' (belum berjalan)',
            default                                           => '',
        };

        return $table
            ->heading("Capaian per Komponen s.d. TW {$data['triwulan']} {$tahun}{$penanda}")
            ->description($this->keterangan($data))
            ->records(fn (): array => collect($data['komponen'])
                ->map(fn (array $baris) => [ArrayRecord::getKeyName() => $baris['kategori'], ...$baris])
                ->all())
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Komponen')
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('satuan')
                    ->label('Satuan')
                    ->wrap()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('target_tahunan')
                    ->label('Target Tahunan')
                    ->state(fn (array $record): string => $record['target_tahunan'] === null
                        ? '–'
                        : number_format($record['target_tahunan']))
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('realisasi_kumulatif')
                    ->label("Realisasi s.d. TW {$data['triwulan']}")
                    ->state(fn (array $record): string => $record['realisasi_kumulatif'] === null
                        ? '–'
                        : number_format($record['realisasi_kumulatif']))
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('realisasi_periode')
                    ->label("Perubahan TW {$data['triwulan']}")
                    ->state(fn (array $record): string => $this->formatPeriode($record))
                    ->color(fn (array $record): ?string => $record['realisasi_periode'] === null ? 'gray' : null)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('persentase')
                    ->label('Capaian')
                    ->state(fn (array $record): string => $record['persentase'] === null
                        ? '–'
                        : number_format($record['persentase'], 2) . '%')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('sumber')
                    ->label('Keterangan Sumber')
                    ->wrap()
                    ->size('sm')
                    ->color('gray'),
            ])
            ->paginated(false);
    }

    /**
     * Perubahan periode: selisih posisi akhir triwulan terpilih terhadap akhir triwulan sebelumnya (boleh negatif).
     */
    protected function formatPeriode(array $record): string
    {
        return match ($record['status_realisasi_periode']) {
            'belum_berjalan'      => 'belum berjalan',
            'belum_terverifikasi' => 'belum dapat diverifikasi',
            'turunan'             => $this->bertanda($record['realisasi_periode']) . ' *',
            default               => $this->bertanda($record['realisasi_periode']),
        };
    }

    protected function bertanda(int $nilai): string
    {
        return ($nilai > 0 ? '+' : ($nilai < 0 ? '−' : '')) . number_format(abs($nilai));
    }

    protected function keterangan(array $data): string
    {
        if (filled($data['peringatan_target'])) {
            return $data['peringatan_target'];
        }

        if ($data['status_periode'] === SpbeQuarterlyPerformanceService::PERIODE_BELUM) {
            return 'Triwulan belum berjalan; realisasi dan capaian belum tersedia.';
        }

        return 'Posisi s.d. ' . Carbon::parse($data['periode_akhir'])->translatedFormat('j F Y') . '. '
            . 'Capaian = realisasi s.d. akhir triwulan ÷ target tahunan × 100% (tidak dibatasi 100%); '
            . 'capaian indikator induk pada KPI = rata-rata sederhana keempat capaian ini. '
            . 'Perubahan TW = selisih terhadap akhir triwulan sebelumnya. '
            . '* Aplikasi: angka turunan dari status akses saat ini, bukan snapshot historis terverifikasi; '
            . 'perubahan TW I belum dapat diverifikasi karena posisi 31 Desember tahun sebelumnya tidak terdokumentasi.';
    }
}
