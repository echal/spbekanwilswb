<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JaringanResource\Pages;
use App\Models\Jaringan;
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

class JaringanResource extends Resource
{
    protected static ?string $model = Jaringan::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-signal';

    protected static string | UnitEnum | null $navigationGroup = 'Infrastruktur';

    protected static ?string $navigationLabel = 'Jaringan';

    protected static ?string $modelLabel = 'Jaringan';

    protected static ?string $pluralModelLabel = 'Jaringan';

    protected static ?string $slug = 'jaringan';

    protected static ?int $navigationSort = 4;

    // Admin, Operator, Auditor bisa melihat Jaringan
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['Admin', 'Operator', 'Auditor']);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Informasi Jaringan')
                    ->schema([
                        Forms\Components\TextInput::make('nama_jaringan')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Nama identitas jaringan, contoh: LAN Gedung A'),
                        Forms\Components\TextInput::make('provider')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Penyedia layanan internet/jaringan'),
                        Forms\Components\TextInput::make('bandwidth')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Kapasitas bandwidth, contoh: 100 Mbps'),
                        Forms\Components\TextInput::make('lokasi')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Lokasi fisik titik jaringan'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_jaringan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('provider')->searchable(),
                Tables\Columns\TextColumn::make('bandwidth'),
                Tables\Columns\TextColumn::make('lokasi')->searchable(),
                Tables\Columns\TextColumn::make('monitoring_jaringan_count')->counts('monitoringJaringan')->label('Monitoring'),
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
            'index' => Pages\ListJaringans::route('/'),
            'create' => Pages\CreateJaringan::route('/create'),
            'edit' => Pages\EditJaringan::route('/{record}/edit'),
        ];
    }
}
