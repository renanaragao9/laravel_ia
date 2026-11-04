<?php

namespace App\Filament\Resources\Draws\Pages;

use App\Filament\Actions\FetchDrawsAction;
use App\Filament\Actions\ImportDrawsAction;
use App\Filament\Resources\Draws\DrawResource;
use Filament\Resources\Pages\ListRecords;

class ListDraws extends ListRecords
{
    protected static string $resource = DrawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportDrawsAction::make(),
            FetchDrawsAction::make(),
        ];
    }
}
