<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuanganResource\Pages;
use App\Models\Ruangan;
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
use UnitEnum;

class RuanganResource extends Resource
{
    protected static ?string $model = Ruangan::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | UnitEnum | null $navigationGroup = 'Infrastruktur';

    protected static ?string $navigationLabel = 'Ruangan';

    protected static ?string $modelLabel = 'Ruangan';

    protected static ?string $pluralModelLabel = 'Ruangan';

    protected static ?string $slug = 'ruangan';

    protected static ?int $navigationSort = 5;

    // Admin dan Operator bisa melihat Ruangan
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

            return $record->unit_kerja_id === $user->managed_unit_kerja_id;
        }

        return $user?->hasRole('Admin');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('Admin');
    }

    public static function form(Schema $form): Schema
    {
        $user = auth()->user();
        $isOperator = $user?->hasRole('Operator') && ! $user->hasRole('Admin');

        return $form
            ->schema([
                Section::make('Informasi Ruangan')
                    ->schema([
                        Forms\Components\Select::make('unit_kerja_id')
                            ->label('Unit Kerja')
                            ->relationship('unitKerja', 'nama_unit')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => $isOperator ? $user->managed_unit_kerja_id : null)
                            ->disabled(fn () => $isOperator)
                            ->dehydrated()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('nama_ruangan')
                            ->label('Nama Ruangan')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Contoh: Ruang Operator, Ruang Server, dll')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = auth()->user();
        $isOperator = $user?->hasRole('Operator') && ! $user->hasRole('Admin');

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($isOperator, $user) {
                if ($isOperator && $user->managed_unit_kerja_id) {
                    $query->where('unit_kerja_id', $user->managed_unit_kerja_id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('nama_ruangan')
                    ->label('Nama Ruangan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unitKerja.nama_unit')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('perangkat_count')
                    ->label('Jumlah Perangkat')
                    ->counts('perangkat')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Operator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('unit_kerja_id')
                    ->label('Unit Kerja')
                    ->relationship('unitKerja', 'nama_unit')
                    ->searchable()
                    ->preload(),

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
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRuangan::route('/'),
            'create' => Pages\CreateRuangan::route('/create'),
            'edit' => Pages\EditRuangan::route('/{record}/edit'),
        ];
    }
}
