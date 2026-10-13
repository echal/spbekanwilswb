<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SpbeGlobalProgressWidget;
use App\Filament\Widgets\SpbeKategoriChart;
use App\Filament\Widgets\SpbeKomponenTable;
use App\Filament\Widgets\SpbeKpiWidget;
use App\Filament\Widgets\SpbePenggunaAplikasiTable;
use App\Filament\Widgets\SpbeTrenKinerjaChart;
use App\Filament\Widgets\SpbeUnitChart;
use App\Services\SpbeQuarterlyPerformanceService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class DashboardSpbe extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Evaluasi';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Dashboard SPBE';

    protected static ?string $title = 'Dashboard Evaluasi SPBE';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dashboard-spbe';

    public int $tahun;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Auditor']) ?? false;
    }

    public int $triwulan;

    public function mount(): void
    {
        $this->tahun = now()->year;
        $this->triwulan = app(SpbeQuarterlyPerformanceService::class)->triwulanAwal($this->tahun);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->columns(['default' => 1, 'sm' => 2])
            ->schema([
                Select::make('tahun')
                    ->label('Tahun')
                    ->options(
                        collect(range(2024, 2035))
                            ->mapWithKeys(fn ($y) => [$y => $y])
                            ->toArray()
                    )
                    ->default(now()->year)
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(function ($state): void {
                        $this->tahun = (int) $state;
                        // A-2: triwulan diatur ulang ke nilai awal tahun terpilih
                        $this->triwulan = app(SpbeQuarterlyPerformanceService::class)->triwulanAwal($this->tahun);
                    }),

                Select::make('triwulan')
                    ->label('Triwulan')
                    ->options([
                        1 => 'TW I (Jan–Mar)',
                        2 => 'TW II (Apr–Jun)',
                        3 => 'TW III (Jul–Sep)',
                        4 => 'TW IV (Okt–Des)',
                    ])
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->triwulan = (int) $state),
            ]);
    }

    /**
     * Semua widget dirender setelah form agar filter tahun & triwulan berada di atas area KPI
     * (header widget dirender sebelum konten halaman).
     */
    protected function getFooterWidgets(): array
    {
        return [
            SpbeKpiWidget::class,
            SpbeKomponenTable::class,
            SpbeTrenKinerjaChart::class,
            SpbeGlobalProgressWidget::class,
            SpbeKategoriChart::class,
            SpbeUnitChart::class,
            SpbePenggunaAplikasiTable::class,
        ];
    }

    /**
     * Pass tahun & triwulan ke semua widgets (widget tanpa properti triwulan mengabaikannya).
     */
    public function getWidgetData(): array
    {
        return [
            'tahun' => $this->tahun,
            'triwulan' => $this->triwulan,
        ];
    }
}
