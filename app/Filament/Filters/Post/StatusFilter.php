<?php

namespace App\Filament\Filters\Post;

use App\Enums\PostStatus;
use Filament\Tables\Filters\SelectFilter;

class StatusFilter
{
    public static function make(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label('Status')
            ->options(collect(PostStatus::cases())->mapWithKeys(fn (PostStatus $case) => [$case->value => $case->label()]));
    }
}
