<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AplikasiResource\Pages;
use App\Models\Aplikasi;
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

class AplikasiResource extends Resource
{
    protected static ?string $model = Aplikasi::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string | UnitEnum | null $navigationGroup = 'Infrastruktur';

    protected static ?string $navigationLabel = 'Aplikasi';

    protected static ?string $modelLabel = 'Aplikasi';

    protected static ?string $pluralModelLabel = 'Aplikasi';

    protected static ?string $slug = 'aplikasi';

    protected static ?int $navigationSort = 2;

    // Operator tidak bisa melihat Aplikasi (hanya via Manajemen Akses Aplikasi)
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['Admin', 'Auditor']);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Informasi Aplikasi')
                    ->schema([
                        Forms\Components\TextInput::make('nama_aplikasi')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Nama lengkap aplikasi'),
                        Forms\Components\Select::make('jenis')
                            ->options([
                                'Internal' => 'Internal',
                                'Nasional' => 'Nasional',
                                'Eksternal' => 'Eksternal',
                            ])
                            ->required()
                            ->searchable()
                            ->helperText('Jenis penggunaan aplikasi'),
                        Forms\Components\Select::make('basis')
                            ->options([
                                'Web' => 'Web',
                                'Desktop' => 'Desktop',
                                'Mobile' => 'Mobile',
                            ])
                            ->required()
                            ->searchable()
                            ->helperText('Platform basis aplikasi'),
                        Forms\Components\Select::make('tingkat_kritis')
                            ->options([
                                'Tinggi' => 'Tinggi',
                                'Sedang' => 'Sedang',
                                'Rendah' => 'Rendah',
                            ])
                            ->required()
                            ->searchable()
                            ->helperText('Tingkat kekritisan aplikasi terhadap operasional'),
                        Forms\Components\TextInput::make('url_aplikasi')
                            ->label('URL Aplikasi')
                            ->url()
                            ->nullable()
                            ->suffixIcon('heroicon-m-link')
                            ->helperText('Masukkan alamat website aplikasi (https://...)')
                            ->columnSpanFull(),
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
            ->columns([
                Tables\Columns\TextColumn::make('nama_aplikasi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jenis')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Internal' => 'info',
                        'Nasional' => 'success',
                        'Eksternal' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('basis')->badge(),
                Tables\Columns\TextColumn::make('tingkat_kritis')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Tinggi' => 'danger',
                        'Sedang' => 'warning',
                        'Rendah' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('url_aplikasi')
                    ->label('URL Aplikasi')
                    ->formatStateUsing(fn ($state) => $state ? 'Buka Aplikasi' : '-')
                    ->url(fn ($record) => $record->url_aplikasi)
                    ->openUrlInNewTab()
                    ->toggleable()
                    ->placeholder('-'),
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
            'index' => Pages\ListAplikasis::route('/'),
            'create' => Pages\CreateAplikasi::route('/create'),
            'edit' => Pages\EditAplikasi::route('/{record}/edit'),
        ];
    }
}
