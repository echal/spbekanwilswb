<?php

namespace App\Filament\Resources\RealisasiTiks;

use App\Filament\Resources\RealisasiTiks\Pages\CreateRealisasiTik;
use App\Filament\Resources\RealisasiTiks\Pages\EditRealisasiTik;
use App\Filament\Resources\RealisasiTiks\Pages\ListRealisasiTiks;
use App\Models\RealisasiTik;
use App\Models\RencanaTikItem;
use App\Services\SpbeQuarterlyPerformanceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RealisasiTikResource extends Resource
{
    protected static ?string $model = RealisasiTik::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-check-circle';

    protected static string | UnitEnum | null $navigationGroup = 'Perencanaan';

    protected static ?string $navigationLabel = 'Realisasi Infrastruktur TIK';

    protected static ?string $modelLabel = 'Realisasi TIK';

    protected static ?string $pluralModelLabel = 'Realisasi TIK';

    protected static ?int $navigationSort = 2;

    // Otorisasi create/edit/delete/verify ada di App\Policies\RealisasiTikPolicy
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Operator', 'Auditor']) ?? false;
    }

    /**
     * Unit kerja Operator yang membatasi data, atau null untuk role tanpa pembatasan unit.
     * Operator tanpa unit menghasilkan 0 (tidak cocok dengan unit mana pun).
     */
    protected static function unitScopeOperator(): ?int
    {
        $user = auth()->user();

        if (! $user || ! $user->hasRole('Operator') || $user->hasRole('Admin')) {
            return null;
        }

        return $user->managed_unit_kerja_id ?? 0;
    }

    /**
     * Query item rencana yang boleh dipilih: rencana Disetujui/Final dan, untuk Operator, unit yang dikelola.
     * Item kategori Aplikasi dikecualikan karena capaiannya dihitung otomatis dari Akses Aplikasi Pegawai.
     */
    protected static function itemRencanaTersedia(): \Illuminate\Database\Eloquent\Builder
    {
        $unitId = static::unitScopeOperator();

        return RencanaTikItem::query()
            ->join('rencana_tik', 'rencana_tik.id', '=', 'rencana_tik_items.rencana_tik_id')
            ->whereIn('rencana_tik.status', ['disetujui', 'final'])
            ->where('rencana_tik_items.kategori', '!=', SpbeQuarterlyPerformanceService::KOMPONEN_BERBASIS_AKSES)
            ->when($unitId !== null, fn ($q) => $q->where('rencana_tik_items.unit_kerja_id', $unitId));
    }

    // ----------------------------------------------------------------
    // FORM
    // ----------------------------------------------------------------

    public static function form(Schema $schema): Schema
    {
        $user       = auth()->user();
        $isOperator = $user?->hasRole('Operator');
        $isAdmin    = $user?->hasRole('Admin');

        return $schema->components([
            Section::make('Data Realisasi')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('rencana_item_id')
                        ->label('Item Rencana')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            return static::itemRencanaTersedia()
                                ->select('rencana_tik_items.id', 'rencana_tik_items.nama_item',
                                    'rencana_tik.tahun', 'rencana_tik_items.jumlah_direncanakan')
                                ->get()
                                ->mapWithKeys(fn ($item) => [
                                    $item->id => "[{$item->tahun}] {$item->nama_item} (Rencana: {$item->jumlah_direncanakan})",
                                ]);
                        })
                        // Validasi server: item harus termasuk pilihan yang sah (tidak cukup disembunyikan di UI)
                        ->rule(fn () => function (string $attribute, $value, $fail) {
                            if (RencanaTikItem::whereKey($value)->value('kategori') === SpbeQuarterlyPerformanceService::KOMPONEN_BERBASIS_AKSES) {
                                $fail('Realisasi Aplikasi dihitung otomatis dari Akses Aplikasi Pegawai dan tidak diinput manual.');

                                return;
                            }

                            if (! static::itemRencanaTersedia()->where('rencana_tik_items.id', $value)->exists()) {
                                $fail('Item rencana tidak tersedia untuk akun Anda.');
                            }
                        })
                        ->helperText($isOperator && $user->managed_unit_kerja_id === null
                            ? 'Unit kerja Anda belum ditentukan. Hubungi Admin.'
                            : 'Hanya item dari rencana Disetujui/Final. Capaian Aplikasi dihitung otomatis dari Akses Aplikasi Pegawai.'),

                    Forms\Components\TextInput::make('jumlah_realisasi')
                        ->label('Jumlah Realisasi')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->rule(function (callable $get) {
                            return function (string $attribute, $value, $fail) use ($get) {
                                $itemId = $get('rencana_item_id');
                                if (! $itemId) return;

                                $item = RencanaTikItem::find($itemId);
                                if (! $item) return;

                                $sudahDirealisasi = RealisasiTik::where('rencana_item_id', $itemId)
                                    ->where('status_verifikasi', 'disetujui')
                                    ->sum('jumlah_realisasi');

                                $sisa = $item->jumlah_direncanakan - $sudahDirealisasi;

                                if ($value > $sisa) {
                                    $fail("Jumlah realisasi ({$value}) melebihi sisa yang dapat direalisasikan ({$sisa}).");
                                }
                            };
                        }),

                    Forms\Components\DatePicker::make('tanggal_realisasi')
                        ->label('Tanggal Realisasi')
                        ->required()
                        ->default(now()),

                    Forms\Components\TextInput::make('nomor_dokumen')
                        ->label('Nomor Dokumen')
                        ->nullable()
                        ->placeholder('Contoh: 023/TIK/2026'),
                ]),

            Section::make('Eviden & Keterangan')
                ->columns(2)
                ->schema([
                    Forms\Components\FileUpload::make('eviden_file')
                        ->label('Upload Eviden')
                        ->directory('realisasi-tik/eviden')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(5120)
                        ->nullable(),

                    Forms\Components\TextInput::make('eviden_url')
                        ->label('Link Eviden Eksternal')
                        ->url()
                        ->nullable()
                        ->prefixIcon('heroicon-m-link')
                        ->placeholder('https://drive.google.com/...')
                        ->helperText('Google Drive, OneDrive, atau link dokumen lainnya'),

                    Forms\Components\Textarea::make('keterangan')
                        ->label('Keterangan')
                        ->nullable()
                        ->columnSpanFull()
                        ->rows(3),
                ]),

            Section::make('Verifikasi')
                ->columns(2)
                ->visible(fn () => $isAdmin)
                ->schema([
                    Forms\Components\Select::make('status_verifikasi')
                        ->label('Status Verifikasi')
                        ->options([
                            'menunggu'  => 'Menunggu',
                            'disetujui' => 'Disetujui',
                            'ditolak'   => 'Ditolak',
                        ])
                        ->default('menunggu')
                        ->required()
                        ->live(),

                    Forms\Components\Textarea::make('catatan_verifikasi')
                        ->label('Catatan Verifikasi')
                        ->nullable()
                        ->rows(3)
                        ->visible(fn (callable $get) => in_array($get('status_verifikasi'), ['disetujui', 'ditolak'])),
                ]),
        ]);
    }

    // ----------------------------------------------------------------
    // TABLE
    // ----------------------------------------------------------------

    public static function table(Table $table): Table
    {
        $isAdmin = auth()->user()?->hasRole('Admin');

        // Pembatasan unit Operator diterapkan di getEloquentQuery()
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rencanaItem.nama_item')
                    ->label('Item Rencana')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->rencanaItem?->nama_item),

                Tables\Columns\TextColumn::make('rencanaItem.rencana.tahun')
                    ->label('Tahun')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rencanaItem.unitKerja.nama_unit')
                    ->label('Unit Kerja')
                    ->searchable(),

                Tables\Columns\TextColumn::make('jumlah_realisasi')
                    ->label('Jml Realisasi')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tanggal_realisasi')
                    ->label('Tgl Realisasi')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('No. Dokumen')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('eviden_url')
                    ->label('Eviden')
                    ->formatStateUsing(fn ($state) => $state ? 'Lihat Eviden' : '-')
                    ->url(fn ($record) => $record->eviden_url)
                    ->openUrlInNewTab()
                    ->color('primary'),

                Tables\Columns\BadgeColumn::make('status_verifikasi')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'menunggu',
                        'success' => 'disetujui',
                        'danger'  => 'ditolak',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'menunggu'  => 'Menunggu',
                        'disetujui' => 'Disetujui',
                        'ditolak'   => 'Ditolak',
                        default     => $state,
                    }),

                Tables\Columns\TextColumn::make('pembuat.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('verifikator.name')
                    ->label('Diverifikasi Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tahun')
                    ->label('Tahun')
                    ->options(
                        collect(range(2024, 2035))
                            ->mapWithKeys(fn ($y) => [$y => $y])
                            ->toArray()
                    )
                    ->query(fn ($query, $data) =>
                        $data['value']
                            ? $query->whereHas('rencanaItem.rencana', fn ($q) => $q->where('tahun', $data['value']))
                            : $query
                    ),

                Tables\Filters\SelectFilter::make('status_verifikasi')
                    ->label('Status Verifikasi')
                    ->options([
                        'menunggu'  => 'Menunggu',
                        'disetujui' => 'Disetujui',
                        'ditolak'   => 'Ditolak',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (RealisasiTik $record) => $record->isEditable()),

                // Tombol Verifikasi (Admin only)
                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(fn (RealisasiTik $record) => $isAdmin && $record->status_verifikasi === 'menunggu')
                    ->authorize(fn (RealisasiTik $record) => auth()->user()?->can('verify', $record) ?? false)
                    ->requiresConfirmation()
                    ->action(function (RealisasiTik $record) {
                        $record->update([
                            'status_verifikasi'  => 'disetujui',
                            'diverifikasi_oleh'  => auth()->id(),
                            'diverifikasi_at'    => now(),
                        ]);
                    }),

                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (RealisasiTik $record) => $isAdmin && $record->status_verifikasi === 'menunggu')
                    ->authorize(fn (RealisasiTik $record) => auth()->user()?->can('verify', $record) ?? false)
                    ->form([
                        \Filament\Forms\Components\Textarea::make('catatan_verifikasi')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (RealisasiTik $record, array $data) {
                        $record->update([
                            'status_verifikasi'  => 'ditolak',
                            'catatan_verifikasi' => $data['catatan_verifikasi'],
                            'diverifikasi_oleh'  => auth()->id(),
                            'diverifikasi_at'    => now(),
                        ]);
                    }),

                DeleteAction::make()
                    ->visible(fn (RealisasiTik $record) => $isAdmin || $record->isEditable()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => $isAdmin)
                        ->authorizeIndividualRecords(),
                ]),
            ])
            ->defaultSort('tanggal_realisasi', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListRealisasiTiks::route('/'),
            'create' => CreateRealisasiTik::route('/create'),
            'edit'   => EditRealisasiTik::route('/{record}/edit'),
        ];
    }

    // Pembatasan unit Operator berlaku untuk tabel dan akses URL langsung ke halaman edit
    public static function getEloquentQuery(): Builder
    {
        $unitId = static::unitScopeOperator();

        return parent::getEloquentQuery()
            ->with(['rencanaItem.rencana', 'rencanaItem.unitKerja', 'pembuat', 'verifikator'])
            ->when($unitId !== null, fn (Builder $query) => $query->whereHas(
                'rencanaItem',
                fn (Builder $q) => $q->where('unit_kerja_id', $unitId),
            ));
    }
}
