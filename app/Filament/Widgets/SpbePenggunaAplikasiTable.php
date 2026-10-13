<?php

namespace App\Filament\Widgets;

use App\Services\SpbeQuarterlyPerformanceService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

class SpbePenggunaAplikasiTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    // Reactive: nilai ikut berubah saat filter tahun di DashboardSpbe diganti (hanya dibaca, tidak dimutasi widget)
    #[Reactive]
    public ?int $tahun = null;

    // Reactive: triwulan terpilih di DashboardSpbe
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
        $akhir = $service->akhirTriwulan($tahun, $tw);
        $status = $service->statusPeriode($tahun, $tw);
        $belum = $status === SpbeQuarterlyPerformanceService::PERIODE_BELUM;

        return $table
            ->heading('Pegawai dengan Akses Aktif per Aplikasi')
            ->description(
                'Posisi s.d. ' . Carbon::parse($akhir)->translatedFormat('j F Y')
                . match ($status) {
                    SpbeQuarterlyPerformanceService::PERIODE_BERJALAN => ' (sementara)',
                    SpbeQuarterlyPerformanceService::PERIODE_BELUM    => ' (belum berjalan)',
                    default                                           => '',
                } . '. '
                . 'Berdasarkan data akses (bukan data penggunaan aktual) dan status akses saat ini, sehingga bukan snapshot historis. '
                . 'Satu pegawai dapat tercatat di beberapa aplikasi, sehingga angka per aplikasi tidak dijumlahkan menjadi total pegawai.'
            )
            // Triwulan yang belum berjalan: jangan tampilkan posisi akses hari ini sebagai posisi masa depan
            ->query(fn () => $belum
                ? $service->queryPenggunaPerAplikasi($akhir)->whereRaw('1 = 0')
                : $service->queryPenggunaPerAplikasi($akhir))
            ->defaultSort('pegawai_akses_aktif', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('nama_aplikasi')
                    ->label('Aplikasi')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pegawai_akses_aktif')
                    ->label('Pegawai dengan akses aktif')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('pegawai_akses_aktif', $direction))
                    ->formatStateUsing(fn ($state) => (int) $state > 0
                        ? number_format((int) $state)
                        : '0 · belum ada pemetaan akses aktif')
                    ->color(fn ($state) => (int) $state > 0 ? null : 'gray'),
            ])
            ->emptyStateHeading($belum ? 'Triwulan belum berjalan' : 'Belum ada aplikasi terdaftar')
            ->emptyStateDescription($belum ? "Posisi akses untuk TW {$tw} {$tahun} belum tersedia." : null)
            ->paginated([10, 25, 50]);
    }
}
