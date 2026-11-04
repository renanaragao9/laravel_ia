<?php

namespace App\Filament\Filters\User;

use Filament\Tables\Filters\TernaryFilter;

class ProfileFilter
{
    public static function make(): TernaryFilter
    {
        return TernaryFilter::make('is_master')
            ->label('Perfil')
            ->placeholder('Todos')
            ->trueLabel('Super admin')
            ->falseLabel('Usuário comum');
    }
}
