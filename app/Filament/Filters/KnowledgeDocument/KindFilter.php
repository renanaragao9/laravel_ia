<?php

namespace App\Filament\Filters\KnowledgeDocument;

use App\Enums\KnowledgeKind;
use Filament\Tables\Filters\SelectFilter;

class KindFilter
{
    public static function make(): SelectFilter
    {
        return SelectFilter::make('kind')
            ->label('Tipo')
            ->options(collect(KnowledgeKind::cases())->mapWithKeys(fn (KnowledgeKind $case) => [$case->value => $case->label()]));
    }
}
