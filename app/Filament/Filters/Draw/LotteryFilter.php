<?php

namespace App\Filament\Filters\Draw;

use App\Models\Draw;
use Filament\Tables\Filters\SelectFilter;

class LotteryFilter
{
    public static function make(): SelectFilter
    {
        return SelectFilter::make('lottery')
            ->label('Loteria')
            ->options(fn () => Draw::query()->distinct()->orderBy('lottery')->pluck('lottery', 'lottery'));
    }
}
