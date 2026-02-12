<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerCloudResource\Pages;
use App\Models\ServerCloud;
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

class ServerCloudResource extends Resource
{
    protected static ?string $model = ServerCloud::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cloud';

    protected static string | UnitEnum | null $navigationGroup = 'Infrastruktur';

    protected static ?string $navigationLabel = 'Server & Cloud';

    protected static ?string $modelLabel = 'Server Cloud';

    protected static ?string $pluralModelLabel = 'Server & Cloud';

    protected static ?string $slug = 'server-cloud';

    protected static ?int $navigationSort = 3;

    // Operator tidak bisa melihat Server & Cloud (konfigurasi strategis)
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['Admin', 'Auditor']);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Informasi Layanan')
                    ->schema([
                        Forms\Components\Select::make('jenis_layanan')
                            ->options([
                                'SaaS' => 'SaaS (Software as a Service)',
                                'PaaS' => 'PaaS (Platform as a Service)',
                                'IaaS' => 'IaaS (Infrastructure as a Service)',
                            ])
                            ->required()
                            ->searchable()
                            ->helperText('Jenis model layanan cloud'),
                        Forms\Components\TextInput::make('nama_layanan')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Nama layanan cloud yang digunakan'),
                        Forms\Components\TextInput::make('provider')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Penyedia layanan (contoh: AWS, GCP, Azure)'),
                        Forms\Components\Select::make('status_kepemilikan')
                            ->options([
                                'Resmi' => 'Resmi',
                                'Tidak Resmi' => 'Tidak Resmi',
                            ])
                            ->required()
                            ->helperText('Status kepemilikan/legalitas layanan'),
                    ])->columns(2),

                Section::make('Detail Kontrak & Penggunaan')
                    ->schema([
                        Forms\Components\TextInput::make('nomor_kontrak')
                            ->maxLength(255)
                            ->helperText('Nomor kontrak layanan (opsional)'),
                        Forms\Components\DatePicker::make('masa_berlaku')
                            ->helperText('Tanggal berakhirnya kontrak layanan'),
                        Forms\Components\TextInput::make('jumlah_user')
                            ->numeric()
                            ->default(0)
                            ->helperText('Jumlah pengguna aktif layanan ini'),
                        Forms\Components\Select::make('kategori_data')
                            ->options([
                                'Publik' => 'Publik',
                                'Internal' => 'Internal',
                                'Rahasia' => 'Rahasia',
                            ])
                            ->required()
                            ->searchable()
                            ->helperText('Klasifikasi keamanan data yang disimpan'),
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
                Tables\Columns\TextColumn::make('nama_layanan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jenis_layanan')->badge(),
                Tables\Columns\TextColumn::make('provider')->searchable(),
                Tables\Columns\TextColumn::make('status_kepemilikan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Resmi' => 'success',
                        'Tidak Resmi' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('jumlah_user')->sortable(),
                Tables\Columns\TextColumn::make('kategori_data')->badge(),
                Tables\Columns\TextColumn::make('masa_berlaku')->date()->sortable(),
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
            'index' => Pages\ListServerClouds::route('/'),
            'create' => Pages\CreateServerCloud::route('/create'),
            'edit' => Pages\EditServerCloud::route('/{record}/edit'),
        ];
    }
}
