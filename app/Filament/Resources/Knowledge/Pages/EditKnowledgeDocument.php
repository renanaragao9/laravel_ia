<?php

namespace App\Filament\Resources\Knowledge\Pages;

use App\Filament\Resources\Knowledge\KnowledgeDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditKnowledgeDocument extends EditRecord
{
    protected static string $resource = KnowledgeDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon(Heroicon::OutlinedTrash),
        ];
    }
}
