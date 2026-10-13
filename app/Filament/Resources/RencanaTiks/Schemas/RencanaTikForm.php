<?php

namespace App\Filament\Resources\RencanaTiks\Schemas;

use App\Models\RencanaTik;
use App\Models\RencanaTikItem;
use App\Support\IntegritasRealisasi;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

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
                            ->helperText(fn (?RencanaTik $record) => static::punyaRealisasi($record)
                                ? 'Terkunci: rencana sudah memiliki histori realisasi.'
                                : 'Tahun perencanaan')
                            // Terkunci bila final, atau bila rencana sudah memiliki realisasi (ditegakkan juga di server)
                            ->disabled(fn (?RencanaTik $record) => $record?->status === 'final' || static::punyaRealisasi($record)),

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
                            // Rencana yang sudah memiliki realisasi tidak dapat dikembalikan ke Draft (ditegakkan juga di server)
                            ->disableOptionWhen(fn (string $value, ?RencanaTik $record): bool => $value === 'draft'
                                && $record?->status !== 'draft'
                                && static::punyaRealisasi($record))
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
                                    ->native(false)
                                    // Terkunci untuk semua role bila item sudah memiliki realisasi (ditegakkan juga di server)
                                    ->disabled(fn (?RencanaTikItem $record) => IntegritasRealisasi::itemPunyaRealisasi($record))
                                    ->dehydrated()
                                    ->helperText(fn (?RencanaTikItem $record) => IntegritasRealisasi::itemPunyaRealisasi($record)
                                        ? 'Terkunci: item sudah memiliki histori realisasi.'
                                        : null),

                                Forms\Components\Select::make('unit_kerja_id')
                                    ->relationship('unitKerja', 'nama_unit')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn () => static::unitOperator())
                                    ->disabled(fn () => static::isOperator())
                                    ->dehydrated()
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
                            // Penegakan di server: item milik Operator selalu disimpan ke unit yang dikelolanya
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => static::kunciUnitOperator($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, RencanaTikItem $record): array => static::kunciUnitOperator(static::kunciKategori($data, $record)))
                            // Item yang sudah memiliki realisasi tidak dapat dihapus dari repeater
                            ->deleteAction(fn (Action $action) => $action->hidden(
                                fn (array $arguments, Repeater $component): bool => IntegritasRealisasi::itemPunyaRealisasi(
                                    $component->getCachedExistingRecords()->get($arguments['item'] ?? '')
                                )
                            ))
                            ->disabled(fn (?RencanaTik $record) => $record?->status === 'final')
                            ->deletable(fn (?RencanaTik $record) => $record?->status !== 'final')
                            ->addable(fn (?RencanaTik $record) => $record?->status !== 'final')
                            ->reorderable(fn (?RencanaTik $record) => $record?->status !== 'final'),
                    ]),
            ]);
    }

    protected static function punyaRealisasi(?RencanaTik $record): bool
    {
        return $record?->exists === true && IntegritasRealisasi::rencanaPunyaRealisasi($record);
    }

    protected static function isOperator(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole('Operator') && ! $user->hasRole('Admin');
    }

    protected static function unitOperator(): ?int
    {
        return static::isOperator() ? auth()->user()->managed_unit_kerja_id : null;
    }

    /**
     * Lapis kedua (setelah validasi beforeSave di EditRencanaTik): tolak perubahan kategori item berealisasi.
     */
    protected static function kunciKategori(array $data, RencanaTikItem $record): array
    {
        if (array_key_exists('kategori', $data)
            && $data['kategori'] !== $record->kategori
            && IntegritasRealisasi::itemPunyaRealisasi($record)) {
            throw ValidationException::withMessages([
                "data.items.record-{$record->getKey()}.kategori" => IntegritasRealisasi::pesanKategori($record->nama_item),
            ]);
        }

        return $data;
    }

    protected static function kunciUnitOperator(array $data): array
    {
        if (static::isOperator()) {
            abort_if(static::unitOperator() === null, 403, 'Unit kerja Operator belum ditentukan.');

            $data['unit_kerja_id'] = static::unitOperator();
        }

        return $data;
    }
}
