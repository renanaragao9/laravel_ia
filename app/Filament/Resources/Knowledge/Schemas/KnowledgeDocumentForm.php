<?php

namespace App\Filament\Resources\Knowledge\Schemas;

use App\Enums\KnowledgeKind;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KnowledgeDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Documento')->columnSpanFull()->columns(2)->schema([
                TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(255),

                Select::make('kind')
                    ->label('Tipo')
                    ->options(collect(KnowledgeKind::cases())->mapWithKeys(fn (KnowledgeKind $kind) => [$kind->value => $kind->label()]))
                    ->required(),

                Textarea::make('content')
                    ->label('Conteúdo')
                    ->helperText('Separe os assuntos por linhas em branco: cada parágrafo vira um trecho pesquisável. Depois de salvar, use "Treinar índice" na lista.')
                    ->rows(16)
                    ->required()
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
