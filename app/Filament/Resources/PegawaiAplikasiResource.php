<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PegawaiAplikasiResource\Pages;
use App\Models\PegawaiAplikasi;
use App\Models\UnitKerja;
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
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class PegawaiAplikasiResource extends Resource
{
    protected static ?string $model = PegawaiAplikasi::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-key';

    protected static string | UnitEnum | null $navigationGroup = 'Manajemen';

    protected static ?string $navigationLabel = 'Manajemen Akses Aplikasi';

    protected static ?string $modelLabel = 'Akses Aplikasi';

    protected static ?string $pluralModelLabel = 'Akses Aplikasi';

    protected static ?string $slug = 'manajemen-akses-aplikasi';

    // Admin, Operator, Auditor bisa melihat; Pimpinan tidak
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['Admin', 'Operator', 'Auditor']);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if ($user?->hasRole('Operator')) {
            return $user->managed_unit_kerja_id !== null
                && $record->pegawai?->unit_kerja_id === $user->managed_unit_kerja_id;
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
                Section::make('Data Akses')
                    ->schema([
                        Forms\Components\Select::make('pegawai_id')
                            ->relationship('pegawai', 'nama', function (Builder $query) use ($isOperator, $user) {
                                if ($isOperator && $user->managed_unit_kerja_id) {
                                    $query->where('unit_kerja_id', $user->managed_unit_kerja_id);
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Pilih pegawai yang akan diberikan akses aplikasi')
                            ->unique(
                                column: 'pegawai_id',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, callable $get) => $rule->where('aplikasi_id', $get('aplikasi_id')),
                            )
                            ->validationMessages([
                                'unique' => 'Pegawai ini sudah memiliki akses ke aplikasi yang dipilih.',
                            ]),
                        Forms\Components\Select::make('aplikasi_id')
                            ->relationship('aplikasi', 'nama_aplikasi')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Pilih aplikasi yang akan diakses'),
                    ])->columns(2),

                Section::make('Detail Akses')
                    ->schema([
                        Forms\Components\Select::make('peran_pengguna')
                            ->options([
                                'Admin' => 'Admin',
                                'Operator' => 'Operator',
                                'User' => 'User',
                            ])
                            ->required()
                            ->searchable()
                            ->helperText('Peran/hak akses pengguna dalam aplikasi'),
                        Forms\Components\Select::make('status_akses')
                            ->options([
                                'Aktif' => 'Aktif',
                                'Nonaktif' => 'Nonaktif',
                            ])
                            ->required()
                            ->default('Aktif')
                            ->helperText('Status aktifasi akses aplikasi'),
                        Forms\Components\DatePicker::make('tanggal_diberikan')
                            ->required()
                            ->default(now())
                            ->helperText('Tanggal resmi pemberian akses'),
                        Forms\Components\Textarea::make('keterangan')
                            ->helperText('Catatan tambahan terkait pemberian akses ini')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user?->hasRole('Operator') && $user->managed_unit_kerja_id) {
                    $query->whereHas('pegawai', fn (Builder $q) => $q->where('unit_kerja_id', $user->managed_unit_kerja_id));
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('pegawai.nama')
                    ->label('Pegawai')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pegawai.unitKerja.nama_unit')
                    ->label('Unit Kerja')
                    ->sortable(),
                Tables\Columns\TextColumn::make('aplikasi.nama_aplikasi')
                    ->label('Aplikasi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('peran_pengguna')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Admin' => 'danger',
                        'Operator' => 'warning',
                        'User' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status_akses')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Nonaktif' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tanggal_diberikan')->date()->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Operator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->options(fn () => UnitKerja::pluck('nama_unit', 'id'))
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'],
                        fn ($q, $v) => $q->whereHas('pegawai', fn ($q2) => $q2->where('unit_kerja_id', $v))
                    )),
                SelectFilter::make('aplikasi_id')
                    ->label('Aplikasi')
                    ->relationship('aplikasi', 'nama_aplikasi'),
                SelectFilter::make('status_akses')
                    ->options([
                        'Aktif' => 'Aktif',
                        'Nonaktif' => 'Nonaktif',
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
            'index' => Pages\ListPegawaiAplikasis::route('/'),
            'create' => Pages\CreatePegawaiAplikasi::route('/create'),
            'edit' => Pages\EditPegawaiAplikasi::route('/{record}/edit'),
        ];
    }
}
