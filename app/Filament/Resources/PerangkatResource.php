<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerangkatResource\Pages;
use App\Models\Perangkat;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PerangkatResource extends Resource
{
    protected static ?string $model = Perangkat::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static string | UnitEnum | null $navigationGroup = 'Infrastruktur';

    protected static ?string $navigationLabel = 'Perangkat';

    protected static ?string $modelLabel = 'Perangkat';

    protected static ?string $slug = 'perangkat';

    protected static ?int $navigationSort = 1;

    // Admin, Operator, Auditor bisa melihat Perangkat
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['Admin', 'Operator', 'Auditor']);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if ($user?->hasRole('Operator')) {
            if ($user->managed_unit_kerja_id === null) {
                return false;
            }
            $unitId = $user->managed_unit_kerja_id;
            return $record->pegawai?->unit_kerja_id === $unitId
                || $record->ruangan?->unit_kerja_id === $unitId;
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    public static function form(Schema $form): Schema
    {
        $user = auth()->user();
        $isOperator = $user?->hasRole('Operator');

        return $form
            ->schema([
                Section::make('Identitas Perangkat')
                    ->schema([
                        Forms\Components\TextInput::make('kode_inventaris')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Kode inventaris unik perangkat'),
                        Forms\Components\TextInput::make('jenis_perangkat')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Contoh: Laptop, PC Desktop, Printer'),
                        Forms\Components\TextInput::make('merek')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tipe')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Model/tipe perangkat'),
                    ])->columns(2),

                Section::make('Spesifikasi Teknis')
                    ->schema([
                        Forms\Components\TextInput::make('processor')
                            ->maxLength(255)
                            ->helperText('Contoh: Intel Core i5-12400'),
                        Forms\Components\TextInput::make('ram')
                            ->maxLength(255)
                            ->helperText('Contoh: 8 GB DDR4'),
                        Forms\Components\TextInput::make('penyimpanan')
                            ->maxLength(255)
                            ->helperText('Contoh: SSD 256 GB'),
                        Forms\Components\TextInput::make('os')
                            ->label('Sistem Operasi')
                            ->maxLength(255)
                            ->helperText('Contoh: Windows 11 Pro'),
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Alamat IP perangkat (opsional, harus unik jika diisi)'),
                    ])->columns(2),

                Section::make('Penempatan & Kondisi')
                    ->schema([
                        Forms\Components\Select::make('kondisi')
                            ->options([
                                'Baik' => 'Baik',
                                'Rusak Ringan' => 'Rusak Ringan',
                                'Rusak Berat' => 'Rusak Berat',
                            ])
                            ->required()
                            ->default('Baik')
                            ->helperText('Kondisi fisik perangkat saat ini'),
                        Forms\Components\Select::make('pegawai_id')
                            ->relationship('pegawai', 'nama', function (Builder $query) use ($isOperator, $user) {
                                if ($isOperator && $user->managed_unit_kerja_id) {
                                    $query->where('unit_kerja_id', $user->managed_unit_kerja_id);
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Pegawai pengguna perangkat'),
                        Forms\Components\Select::make('ruangan_id')
                            ->relationship('ruangan', 'nama_ruangan', function (Builder $query) use ($isOperator, $user) {
                                if ($isOperator && $user->managed_unit_kerja_id) {
                                    $query->where('unit_kerja_id', $user->managed_unit_kerja_id);
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Ruangan tempat perangkat berada'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user?->hasRole('Operator') && $user->managed_unit_kerja_id) {
                    $query->where(function (Builder $q) use ($user) {
                        $q->whereHas('pegawai', fn (Builder $sub) => $sub->where('unit_kerja_id', $user->managed_unit_kerja_id))
                          ->orWhereHas('ruangan', fn (Builder $sub) => $sub->where('unit_kerja_id', $user->managed_unit_kerja_id));
                    });
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('kode_inventaris')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jenis_perangkat')->searchable(),
                Tables\Columns\TextColumn::make('merek')->searchable(),
                Tables\Columns\TextColumn::make('tipe'),
                Tables\Columns\TextColumn::make('kondisi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Baik' => 'success',
                        'Rusak Ringan' => 'warning',
                        'Rusak Berat' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('pegawai.nama')->label('Pegawai')->toggleable(),
                Tables\Columns\TextColumn::make('ruangan.nama_ruangan')->label('Ruangan')->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Operator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('created_by')
                    ->label('Operator')
                    ->options(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Admin', 'Operator']))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->hasRole('Admin')),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('Admin')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerangkats::route('/'),
            'create' => Pages\CreatePerangkat::route('/create'),
            'edit' => Pages\EditPerangkat::route('/{record}/edit'),
        ];
    }
}
