<?php

namespace App\Filament\Resources\Knowledge\Pages;

use App\Filament\Resources\Knowledge\KnowledgeDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKnowledgeDocument extends CreateRecord
{
    protected static string $resource = KnowledgeDocumentResource::class;
}
