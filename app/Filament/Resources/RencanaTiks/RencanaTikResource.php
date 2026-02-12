<?php

namespace App\Filament\Resources\RencanaTiks;

use App\Filament\Resources\RencanaTiks\Pages\CreateRencanaTik;
use App\Filament\Resources\RencanaTiks\Pages\EditRencanaTik;
use App\Filament\Resources\RencanaTiks\Pages\ListRencanaTiks;
use App\Filament\Resources\RencanaTiks\Schemas\RencanaTikForm;
use App\Filament\Resources\RencanaTiks\Tables\RencanaTiksTable;
use App\Models\RencanaTik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RencanaTikResource extends Resource
{
    protected static ?string $model = RencanaTik::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string | UnitEnum | null $navigationGroup = 'Perencanaan';

    protected static ?string $navigationLabel = 'Rencana Infrastruktur TIK';

    protected static ?string $modelLabel = 'Rencana TIK';

    protected static ?string $pluralModelLabel = 'Rencana TIK';

    protected static ?string $recordTitleAttribute = 'nama_rencana';

    // Admin dan Operator bisa melihat
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Operator']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return RencanaTikForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RencanaTiksTable::configure($table);
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
            'index' => ListRencanaTiks::route('/'),
            'create' => CreateRencanaTik::route('/create'),
            'edit' => EditRencanaTik::route('/{record}/edit'),
        ];
    }

    // Eager loading untuk mencegah N+1 query
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['items', 'creator']);
    }
}
