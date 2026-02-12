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

    protected static ?string $pluralModelLabel = 'Perangkat';

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
                Section::make('Status Kepemilikan')
                    ->schema([
                        Forms\Components\Select::make('status_kepemilikan')
                            ->label('Status Kepemilikan')
                            ->options([
                                'milik_kantor' => 'Milik Kantor',
                                'milik_pribadi' => 'Milik Pribadi (BYOD)',
                                'belum_memiliki' => 'Belum Memiliki Perangkat',
                                'perangkat_bersama' => 'Perangkat Bersama',
                            ])
                            ->required()
                            ->default('milik_kantor')
                            ->live()
                            ->helperText('Status kepemilikan perangkat oleh ASN')
                            ->columnSpanFull(),
                    ]),

                Section::make('Identitas Perangkat')
                    ->schema([
                        Forms\Components\TextInput::make('kode_inventaris')
                            ->label('Kode Inventaris')
                            ->required(fn (callable $get) => in_array($get('status_kepemilikan'), ['milik_kantor', 'perangkat_bersama']))
                            ->disabled(fn (callable $get) => $get('status_kepemilikan') === 'belum_memiliki')
                            ->dehydrated(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText(fn (callable $get) =>
                                $get('status_kepemilikan') === 'milik_kantor' || $get('status_kepemilikan') === 'perangkat_bersama'
                                    ? 'Wajib diisi untuk perangkat kantor/bersama'
                                    : 'Opsional untuk perangkat pribadi'
                            ),

                        Forms\Components\TextInput::make('jenis_perangkat')
                            ->label('Jenis Perangkat')
                            ->required(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->disabled(fn (callable $get) => $get('status_kepemilikan') === 'belum_memiliki')
                            ->dehydrated(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->maxLength(255)
                            ->helperText('Contoh: Laptop, PC Desktop, Printer'),

                        Forms\Components\TextInput::make('merek')
                            ->label('Merek')
                            ->required(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->disabled(fn (callable $get) => $get('status_kepemilikan') === 'belum_memiliki')
                            ->dehydrated(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tipe')
                            ->label('Tipe/Model')
                            ->required(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->disabled(fn (callable $get) => $get('status_kepemilikan') === 'belum_memiliki')
                            ->dehydrated(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->maxLength(255)
                            ->helperText('Model/tipe perangkat'),
                    ])->columns(2),

                Section::make('Spesifikasi Teknis')
                    ->schema([
                        Forms\Components\TextInput::make('processor')
                            ->label('Processor')
                            ->disabled(fn (callable $get) => $get('status_kepemilikan') === 'belum_memiliki')
                            ->dehydrated(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->maxLength(255)
                            ->helperText('Contoh: Intel Core i5-12400'),

                        Forms\Components\TextInput::make('ram')
                            ->label('RAM')
                            ->disabled(fn (callable $get) => $get('status_kepemilikan') === 'belum_memiliki')
                            ->dehydrated(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->maxLength(255)
                            ->helperText('Contoh: 8 GB DDR4'),

                        Forms\Components\TextInput::make('penyimpanan')
                            ->label('Penyimpanan')
                            ->disabled(fn (callable $get) => $get('status_kepemilikan') === 'belum_memiliki')
                            ->dehydrated(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->maxLength(255)
                            ->helperText('Contoh: SSD 256 GB'),

                        Forms\Components\TextInput::make('os')
                            ->label('Sistem Operasi')
                            ->disabled(fn (callable $get) => $get('status_kepemilikan') === 'belum_memiliki')
                            ->dehydrated(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
                            ->maxLength(255)
                            ->helperText('Contoh: Windows 11 Pro'),

                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled(fn (callable $get) => $get('status_kepemilikan') === 'belum_memiliki')
                            ->dehydrated(fn (callable $get) => $get('status_kepemilikan') !== 'belum_memiliki')
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

                Section::make('Eviden')
                    ->schema([
                        Forms\Components\TextInput::make('link_eviden')
                            ->label('Link Eviden')
                            ->url()
                            ->nullable()
                            ->prefixIcon('heroicon-o-link')
                            ->helperText('Masukkan link Google Drive / cloud storage sebagai bukti eviden')
                            ->columnSpanFull(),
                    ]),
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
                Tables\Columns\TextColumn::make('kode_inventaris')
                    ->label('Kode Inventaris')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status_kepemilikan')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'milik_kantor' => 'Milik Kantor',
                        'milik_pribadi' => 'BYOD',
                        'belum_memiliki' => 'Belum Ada',
                        'perangkat_bersama' => 'Bersama',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'milik_kantor' => 'success',
                        'milik_pribadi' => 'info',
                        'belum_memiliki' => 'warning',
                        'perangkat_bersama' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('jenis_perangkat')
                    ->label('Jenis')
                    ->searchable(),

                Tables\Columns\TextColumn::make('merek')
                    ->label('Merek')
                    ->searchable(),

                Tables\Columns\TextColumn::make('tipe')
                    ->label('Tipe')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('kondisi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Baik' => 'success',
                        'Rusak Ringan' => 'warning',
                        'Rusak Berat' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('pegawai.nama')
                    ->label('Pegawai')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ruangan.nama_ruangan')
                    ->label('Ruangan')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('link_eviden')
                    ->label('Link Eviden')
                    ->url(fn ($record) => $record->link_eviden)
                    ->openUrlInNewTab()
                    ->toggleable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Operator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status_kepemilikan')
                    ->label('Status Kepemilikan')
                    ->options([
                        'milik_kantor' => 'Milik Kantor',
                        'milik_pribadi' => 'Milik Pribadi (BYOD)',
                        'belum_memiliki' => 'Belum Memiliki',
                        'perangkat_bersama' => 'Perangkat Bersama',
                    ]),

                SelectFilter::make('kondisi')
                    ->label('Kondisi')
                    ->options([
                        'Baik' => 'Baik',
                        'Rusak Ringan' => 'Rusak Ringan',
                        'Rusak Berat' => 'Rusak Berat',
                    ]),

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
