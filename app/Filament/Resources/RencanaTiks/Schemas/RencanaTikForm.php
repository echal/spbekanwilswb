<?php

namespace App\Filament\Resources\RencanaTiks\Schemas;

use App\Models\RencanaTik;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RencanaTikForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Rencana')
                    ->schema([
                        Forms\Components\Select::make('tahun')
                            ->options(array_combine(
                                range(2024, 2035),
                                range(2024, 2035)
                            ))
                            ->required()
                            ->default(now()->year)
                            ->helperText('Tahun perencanaan')
                            ->disabled(fn (?RencanaTik $record) => $record?->status === 'final'),

                        Forms\Components\TextInput::make('nama_rencana')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Nama rencana infrastruktur TIK')
                            ->disabled(fn (?RencanaTik $record) => $record?->status === 'final'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'disetujui' => 'Disetujui',
                                'final' => 'Final',
                            ])
                            ->required()
                            ->default('draft')
                            ->helperText('Status persetujuan rencana')
                            ->disabled(fn () => ! auth()->user()->hasRole('Admin')),

                        Forms\Components\Textarea::make('keterangan')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Keterangan tambahan (opsional)')
                            ->disabled(fn (?RencanaTik $record) => $record?->status === 'final'),
                    ])->columns(2),

                Section::make('Detail Kebutuhan')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('kategori')
                                    ->options([
                                        'perangkat' => 'Perangkat',
                                        'server' => 'Server',
                                        'jaringan' => 'Jaringan',
                                        'aplikasi' => 'Aplikasi',
                                    ])
                                    ->required()
                                    ->native(false),

                                Forms\Components\Select::make('unit_kerja_id')
                                    ->relationship('unitKerja', 'nama_unit')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->label('Unit Kerja'),

                                Forms\Components\TextInput::make('nama_item')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Nama Item')
                                    ->helperText('Nama perangkat/server/jaringan/aplikasi'),

                                Forms\Components\TextInput::make('jumlah_direncanakan')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->label('Jumlah Direncanakan'),

                                Forms\Components\TextInput::make('jumlah_terpenuhi')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->label('Jumlah Terpenuhi')
                                    ->rule(function (callable $get) {
                                        return function (string $attribute, $value, $fail) use ($get) {
                                            $direncanakan = $get('jumlah_direncanakan');
                                            if ($value > $direncanakan) {
                                                $fail('Jumlah terpenuhi tidak boleh melebihi jumlah direncanakan.');
                                            }
                                        };
                                    }),

                                Forms\Components\Select::make('status_realisasi')
                                    ->options([
                                        'belum' => 'Belum',
                                        'sebagian' => 'Sebagian',
                                        'selesai' => 'Selesai',
                                    ])
                                    ->default('belum')
                                    ->required()
                                    ->native(false)
                                    ->label('Status Realisasi'),

                                Forms\Components\Select::make('prioritas')
                                    ->options([
                                        'tinggi' => 'Tinggi',
                                        'sedang' => 'Sedang',
                                        'rendah' => 'Rendah',
                                    ])
                                    ->default('sedang')
                                    ->required()
                                    ->native(false)
                                    ->label('Prioritas'),

                                Forms\Components\TextInput::make('estimasi_anggaran')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->nullable()
                                    ->label('Estimasi Anggaran')
                                    ->helperText('Estimasi anggaran dalam Rupiah'),

                                Forms\Components\DatePicker::make('tanggal_target')
                                    ->nullable()
                                    ->label('Tanggal Target')
                                    ->helperText('Target tanggal penyelesaian'),

                                Forms\Components\Textarea::make('keterangan')
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->label('Keterangan'),
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['nama_item'] ?? null)
                            ->addActionLabel('Tambah Item Kebutuhan')
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->disabled(fn (?RencanaTik $record) => $record?->status === 'final')
                            ->deletable(fn (?RencanaTik $record) => $record?->status !== 'final')
                            ->addable(fn (?RencanaTik $record) => $record?->status !== 'final')
                            ->reorderable(fn (?RencanaTik $record) => $record?->status !== 'final'),
                    ]),
            ]);
    }
}
