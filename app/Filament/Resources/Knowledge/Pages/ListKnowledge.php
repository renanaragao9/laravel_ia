<?php

namespace App\Filament\Resources\Knowledge\Pages;

use App\Filament\Actions\TrainKnowledgeAction;
use App\Filament\Resources\Knowledge\KnowledgeDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListKnowledge extends ListRecords
{
    protected static string $resource = KnowledgeDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TrainKnowledgeAction::make(),
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
