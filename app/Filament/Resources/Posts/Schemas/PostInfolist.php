<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Post;
use App\Support\Bicho\Bicho;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Post')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')
                            ->label('Título')
                            ->columnSpanFull(),

                        TextEntry::make('post_date')
                            ->label('Data do post')
                            ->date('d/m/Y'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => $state->value === 'published' ? 'success' : 'warning'),

                        TextEntry::make('published_at')
                            ->label('Publicado em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('excerpt')
                            ->label('Resumo')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('body')
                            ->label('Texto')
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                Section::make('Palpites (curiosidade)')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('guess_animals')
                            ->label('Animais')
                            ->state(fn (Post $record) => collect($record->guesses['animals'] ?? [])->map(fn ($number) => $number.' - '.Bicho::animalName((int) $number))->implode(', ') ?: '-'),

                        TextEntry::make('guess_tens')
                            ->label('Dezenas')
                            ->state(fn (Post $record) => implode(', ', $record->guesses['tens'] ?? []) ?: '-'),
                    ]),

                Section::make('Como a IA chegou a este texto')
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('model')
                            ->label('Modelo')
                            ->placeholder('-'),

                        TextEntry::make('prompt_tokens')
                            ->label('Tokens de entrada')
                            ->placeholder('-'),

                        TextEntry::make('completion_tokens')
                            ->label('Tokens de saída')
                            ->placeholder('-'),

                        TextEntry::make('tools')
                            ->label('Ferramentas MCP consultadas')
                            ->state(fn (Post $record) => collect($record->context['tool_calls'] ?? [])->map(fn (array $call) => $call['name'].' '.json_encode($call['arguments'], JSON_UNESCAPED_UNICODE))->all())
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('style')
                            ->label('Documentos de estilo recuperados (RAG)')
                            ->state(fn (Post $record) => $record->context['style_sources'] ?? [])
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
