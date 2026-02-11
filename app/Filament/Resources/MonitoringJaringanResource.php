<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonitoringJaringanResource\Pages;
use App\Models\MonitoringJaringan;
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

class MonitoringJaringanResource extends Resource
{
    protected static ?string $model = MonitoringJaringan::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | UnitEnum | null $navigationGroup = 'Infrastruktur';

    protected static ?string $navigationLabel = 'Monitoring Jaringan';

    protected static ?string $modelLabel = 'Monitoring Jaringan';

    protected static ?string $pluralModelLabel = 'Monitoring Jaringan';

    protected static ?string $slug = 'monitoring-jaringan';

    protected static ?int $navigationSort = 5;

    // Admin, Operator, Auditor bisa melihat Monitoring Jaringan
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['Admin', 'Operator', 'Auditor']);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Informasi Monitoring')
                    ->schema([
                        Forms\Components\Select::make('jaringan_id')
                            ->relationship('jaringan', 'nama_jaringan')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Pilih jaringan yang dimonitor'),
                        Forms\Components\DatePicker::make('tanggal_monitoring')
                            ->required()
                            ->default(now())
                            ->helperText('Tanggal pelaksanaan monitoring'),
                    ])->columns(2),

                Section::make('Data Penggunaan')
                    ->schema([
                        Forms\Components\TextInput::make('jumlah_pengguna')
                            ->numeric()
                            ->default(0)
                            ->helperText('Total pengguna aktif saat monitoring'),
                        Forms\Components\TextInput::make('jumlah_perangkat')
                            ->numeric()
                            ->default(0)
                            ->helperText('Total perangkat terhubung ke jaringan'),
                    ])->columns(2),

                Section::make('Performa Jaringan')
                    ->schema([
                        Forms\Components\TextInput::make('upload_speed')
                            ->numeric()
                            ->suffix('Mbps')
                            ->helperText('Kecepatan upload dalam Mbps'),
                        Forms\Components\TextInput::make('download_speed')
                            ->numeric()
                            ->suffix('Mbps')
                            ->helperText('Kecepatan download dalam Mbps'),
                        Forms\Components\TextInput::make('ping')
                            ->numeric()
                            ->suffix('ms')
                            ->helperText('Latensi ping dalam millisecond'),
                        Forms\Components\Select::make('status_koneksi')
                            ->options([
                                'Stabil' => 'Stabil',
                                'Tidak Stabil' => 'Tidak Stabil',
                                'Putus' => 'Putus',
                            ])
                            ->required()
                            ->searchable()
                            ->helperText('Status koneksi jaringan saat monitoring'),
                    ])->columns(2),

                Section::make('Catatan')
                    ->schema([
                        Forms\Components\Textarea::make('kendala')
                            ->helperText('Kendala yang ditemukan saat monitoring')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('tindak_lanjut')
                            ->helperText('Tindakan yang sudah/akan dilakukan')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('jaringan.nama_jaringan')->label('Jaringan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tanggal_monitoring')->date()->sortable(),
                Tables\Columns\TextColumn::make('jumlah_pengguna')->sortable(),
                Tables\Columns\TextColumn::make('download_speed')->suffix(' Mbps'),
                Tables\Columns\TextColumn::make('upload_speed')->suffix(' Mbps'),
                Tables\Columns\TextColumn::make('ping')->suffix(' ms'),
                Tables\Columns\TextColumn::make('status_koneksi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Stabil' => 'success',
                        'Tidak Stabil' => 'warning',
                        'Putus' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Operator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('tanggal_monitoring', 'desc')
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
            'index' => Pages\ListMonitoringJaringans::route('/'),
            'create' => Pages\CreateMonitoringJaringan::route('/create'),
            'edit' => Pages\EditMonitoringJaringan::route('/{record}/edit'),
        ];
    }
}
