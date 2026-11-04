<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Dados do Usuário')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nome'),

                        TextEntry::make('email')
                            ->label('E-mail'),

                        IconEntry::make('is_master')
                            ->label('Super admin')
                            ->boolean(),

                        TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Atualizado em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
