<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SpbeGlobalProgressWidget;
use App\Filament\Widgets\SpbeKategoriChart;
use App\Filament\Widgets\SpbeUnitChart;
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

    public function mount(): void
    {
        $this->tahun = now()->year;
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('tahun')
                    ->label('Tahun')
                    ->options(
                        collect(range(2024, 2035))
                            ->mapWithKeys(fn ($y) => [$y => $y])
                            ->toArray()
                    )
                    ->default(now()->year)
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->tahun = (int) $state),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SpbeGlobalProgressWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            SpbeKategoriChart::class,
            SpbeUnitChart::class,
        ];
    }

    /**
     * Pass tahun ke semua widgets.
     */
    public function getWidgetData(): array
    {
        return [
            'tahun' => $this->tahun,
        ];
    }
}
