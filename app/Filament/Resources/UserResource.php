<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';

    protected static string | UnitEnum | null $navigationGroup = 'Manajemen';

    protected static ?string $navigationLabel = 'Manajemen User';

    protected static ?string $modelLabel = 'User';

    protected static ?string $slug = 'users';

    protected static ?int $navigationSort = 1;

    // Hanya Admin yang bisa mengelola User
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Data Pegawai Terkait')
                    ->description('Pilih pegawai yang akan dibuatkan akun login. Nama akan otomatis terisi.')
                    ->schema([
                        Forms\Components\Select::make('pegawai_id')
                            ->label('Pegawai')
                            ->relationship('pegawai', 'nama')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->helperText('Pegawai pemilik akun ini (opsional)')
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $pegawai = \App\Models\Pegawai::find($state);
                                    if ($pegawai) {
                                        $set('name', $pegawai->nama);
                                    }
                                }
                            }),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Akun')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Nama tampilan akun (otomatis dari pegawai jika dipilih)'),
                    ])->columns(2),

                Section::make('Kredensial Login')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Email untuk login ke sistem'),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText('Kosongkan jika tidak ingin mengubah password'),
                    ])->columns(2),

                Section::make('Role & Pengelolaan')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Akun')
                            ->helperText('Nonaktifkan untuk memblokir akses login akun ini')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->onIcon('heroicon-m-check')
                            ->offIcon('heroicon-m-x-mark')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('roles')
                            ->label('Role Sistem')
                            ->options(fn () => Role::pluck('name', 'name'))
                            ->multiple()
                            ->searchable()
                            ->helperText('Hak akses pengguna di sistem')
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Forms\Components\Select $component, $record) {
                                if ($record) {
                                    $component->state($record->getRoleNames()->toArray());
                                }
                            }),
                        Forms\Components\Select::make('managed_unit_kerja_id')
                            ->label('Unit Kerja yang Dikelola')
                            ->relationship('managedUnitKerja', 'nama_unit')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Khusus role Operator: unit kerja yang dikelola'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pegawai.nama')
                    ->label('Pegawai')
                    ->searchable()
                    ->sortable()
                    ->placeholder('(Bukan pegawai)'),
                Tables\Columns\TextColumn::make('pegawai.unitKerja.nama_unit')
                    ->label('Unit Kerja')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Akun')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Admin' => 'danger',
                        'Operator' => 'warning',
                        'Auditor' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('managedUnitKerja.nama_unit')
                    ->label('Unit Dikelola')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn ($state) => $state ? 'Aktif' : 'Nonaktif'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Akun')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->placeholder('Semua'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}