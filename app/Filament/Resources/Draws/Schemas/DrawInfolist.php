<?php

namespace App\Filament\Resources\Draws\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DrawInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Sorteio')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('drawn_on')
                            ->label('Data')
                            ->date('d/m/Y'),

                        TextEntry::make('lottery')
                            ->label('Loteria')
                            ->badge(),

                        TextEntry::make('draw_time')
                            ->label('Horário')
                            ->placeholder('-'),

                        TextEntry::make('source')
                            ->label('Origem')
                            ->placeholder('-'),
                    ]),

                Section::make('Resultados')
                    ->schema([
                        RepeatableEntry::make('results')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('position')
                                    ->label('Prêmio')
                                    ->formatStateUsing(fn ($state) => $state.'º'),

                                TextEntry::make('thousand')
                                    ->label('Milhar'),

                                TextEntry::make('ten')
                                    ->label('Dezena'),

                                TextEntry::make('animal.name')
                                    ->label('Animal')
                                    ->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}
