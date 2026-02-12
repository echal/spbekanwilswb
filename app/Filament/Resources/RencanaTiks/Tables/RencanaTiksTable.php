<?php

namespace App\Filament\Resources\RencanaTiks\Tables;

use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RencanaTiksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tahun')
                    ->sortable()
                    ->searchable()
                    ->label('Tahun'),

                Tables\Columns\TextColumn::make('nama_rencana')
                    ->searchable()
                    ->sortable()
                    ->label('Nama Rencana')
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'disetujui' => 'warning',
                        'final' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'disetujui' => 'Disetujui',
                        'final' => 'Final',
                        default => $state,
                    })
                    ->label('Status'),

                Tables\Columns\TextColumn::make('total_kebutuhan')
                    ->label('Total Kebutuhan')
                    ->getStateUsing(fn ($record) => $record->total_kebutuhan)
                    ->numeric()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_anggaran')
                    ->label('Total Anggaran')
                    ->getStateUsing(fn ($record) => $record->total_anggaran)
                    ->money('IDR')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable()
                    ->label('Tanggal Dibuat'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('tahun')
                    ->options(array_combine(
                        range(2024, 2035),
                        range(2024, 2035)
                    ))
                    ->label('Filter Tahun'),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'disetujui' => 'Disetujui',
                        'final' => 'Final',
                    ])
                    ->label('Filter Status'),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->hasRole('Admin')),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('Admin')),
                ]),
            ]);
    }
}
