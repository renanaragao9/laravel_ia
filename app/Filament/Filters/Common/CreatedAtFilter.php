<?php

namespace App\Filament\Filters\Common;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class CreatedAtFilter
{
    public static function make(string $column = 'created_at'): Filter
    {
        return Filter::make($column)
            ->label('Criado em')
            ->schema([
                DatePicker::make('from')
                    ->label('Criado a partir de'),

                DatePicker::make('until')
                    ->label('Criado até'),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate($column, '<=', $date)));
    }
}
