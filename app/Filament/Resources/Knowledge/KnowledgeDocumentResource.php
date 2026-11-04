<?php

namespace App\Filament\Resources\Knowledge;

use App\Filament\Resources\Knowledge\Pages\CreateKnowledgeDocument;
use App\Filament\Resources\Knowledge\Pages\EditKnowledgeDocument;
use App\Filament\Resources\Knowledge\Pages\ListKnowledge;
use App\Filament\Resources\Knowledge\Schemas\KnowledgeDocumentForm;
use App\Filament\Resources\Knowledge\Tables\KnowledgeTable;
use App\Models\KnowledgeDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class KnowledgeDocumentResource extends Resource
{
    protected static ?string $model = KnowledgeDocument::class;

    protected static ?string $slug = 'conhecimento';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Base de conhecimento';

    protected static ?string $navigationLabel = 'Base de conhecimento';

    protected static string|UnitEnum|null $navigationGroup = 'Dados';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return KnowledgeDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KnowledgeTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKnowledge::route('/'),
            'create' => CreateKnowledgeDocument::route('/create'),
            'edit' => EditKnowledgeDocument::route('/{record}/edit'),
        ];
    }
}
