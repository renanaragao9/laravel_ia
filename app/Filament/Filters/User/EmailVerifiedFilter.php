<?php

namespace App\Filament\Filters\User;

use Filament\Tables\Filters\TernaryFilter;

class EmailVerifiedFilter
{
    public static function make(): TernaryFilter
    {
        return TernaryFilter::make('email_verified_at')
            ->label('E-mail verificado')
            ->placeholder('Todos')
            ->trueLabel('Verificado')
            ->falseLabel('Não verificado')
            ->nullable();
    }
}
