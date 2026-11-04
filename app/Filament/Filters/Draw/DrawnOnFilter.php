<?php

namespace App\Filament\Filters\Draw;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DrawnOnFilter
{
    public static function make(): Filter
    {
        return Filter::make('drawn_on')
            ->label('Data do sorteio')
            ->schema([
                DatePicker::make('from')
                    ->label('De'),

                DatePicker::make('until')
                    ->label('Até'),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('drawn_on', '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('drawn_on', '<=', $date)));
    }
}
