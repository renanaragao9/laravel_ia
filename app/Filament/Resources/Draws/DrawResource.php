<?php

namespace App\Filament\Resources\Draws;

use App\Filament\Resources\Draws\Pages\ListDraws;
use App\Filament\Resources\Draws\Pages\ViewDraw;
use App\Filament\Resources\Draws\Schemas\DrawInfolist;
use App\Filament\Resources\Draws\Tables\DrawsTable;
use App\Models\Draw;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DrawResource extends Resource
{
    protected static ?string $model = Draw::class;

    protected static ?string $slug = 'sorteios';

    protected static ?string $recordTitleAttribute = 'drawn_on';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Sorteio';

    protected static ?string $pluralModelLabel = 'Sorteios';

    protected static ?string $navigationLabel = 'Sorteios';

    protected static string|UnitEnum|null $navigationGroup = 'Dados';

    protected static ?int $navigationSort = 1;

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof Draw ? trim($record->drawn_on->format('d/m/Y').' '.$record->draw_time) : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return DrawInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DrawsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDraws::route('/'),
            'view' => ViewDraw::route('/{record}'),
        ];
    }
}
