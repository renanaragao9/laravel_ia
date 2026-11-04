<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Post')->columnSpanFull()->columns(2)->schema([
                TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(160)
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label('Endereço (slug)')
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[a-z0-9\-]+$/')
                    ->unique(ignoreRecord: true),

                DatePicker::make('post_date')
                    ->label('Data do post')
                    ->required(),

                Textarea::make('excerpt')
                    ->label('Resumo')
                    ->rows(2)
                    ->maxLength(300)
                    ->columnSpanFull(),

                MarkdownEditor::make('body')
                    ->label('Texto (Markdown)')
                    ->required()
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
