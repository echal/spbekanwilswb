<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitKerjaResource\Pages;
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
use UnitEnum;

class UnitKerjaResource extends Resource
{
    protected static ?string $model = UnitKerja::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office';

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Unit Kerja';

    protected static ?string $modelLabel = 'Unit Kerja';

    protected static ?string $pluralModelLabel = 'Unit Kerja';

    protected static ?string $slug = 'unit-kerja';

    protected static ?int $navigationSort = 1;

    // Operator tidak bisa melihat Unit Kerja global
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['Admin', 'Auditor']);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Informasi Unit Kerja')
                    ->schema([
                        Forms\Components\TextInput::make('kode_unit')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Kode unik unit kerja, contoh: UK-001'),
                        Forms\Components\TextInput::make('nama_unit')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Nama lengkap unit kerja'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode_unit')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('nama_unit')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('pegawai_count')->counts('pegawai')->label('Jml Pegawai'),
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
            'index' => Pages\ListUnitKerjas::route('/'),
            'create' => Pages\CreateUnitKerja::route('/create'),
            'edit' => Pages\EditUnitKerja::route('/{record}/edit'),
        ];
    }
}
