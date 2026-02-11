<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PegawaiResource\Pages;
use App\Models\Pegawai;
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

class PegawaiResource extends Resource
{
    protected static ?string $model = Pegawai::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Pegawai';

    protected static ?string $modelLabel = 'Pegawai';

    protected static ?string $slug = 'pegawai';

    protected static ?int $navigationSort = 2;

    // Admin, Operator, Auditor bisa melihat Pegawai
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
                && $record->unit_kerja_id === $user->managed_unit_kerja_id;
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
                Section::make('Data Pegawai')
                    ->schema([
                        Forms\Components\TextInput::make('nip')
                            ->label('NIP')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Nomor Induk Pegawai, harus unik'),
                        Forms\Components\TextInput::make('nama')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Nama lengkap pegawai'),
                        Forms\Components\TextInput::make('jabatan')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Jabatan atau posisi pegawai'),
                        Forms\Components\Select::make('unit_kerja_id')
                            ->relationship('unitKerja', 'nama_unit')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => $isOperator ? $user->managed_unit_kerja_id : null)
                            ->disabled(fn () => $isOperator)
                            ->dehydrated()
                            ->helperText('Unit kerja tempat pegawai bertugas'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user?->hasRole('Operator') && $user->managed_unit_kerja_id) {
                    $query->where('unit_kerja_id', $user->managed_unit_kerja_id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('nip')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jabatan')->searchable(),
                Tables\Columns\TextColumn::make('unitKerja.nama_unit')->label('Unit Kerja')->sortable(),
                Tables\Columns\IconColumn::make('user_exists')
                    ->label('Punya Akun')
                    ->state(fn ($record) => $record->user()->exists())
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Operator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListPegawais::route('/'),
            'create' => Pages\CreatePegawai::route('/create'),
            'edit' => Pages\EditPegawai::route('/{record}/edit'),
        ];
    }
}
