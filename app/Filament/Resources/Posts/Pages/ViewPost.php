<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Actions\PublishPostAction;
use App\Filament\Actions\UnpublishPostAction;
use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPost extends ViewRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PublishPostAction::make(),
            UnpublishPostAction::make(),
            EditAction::make()
                ->icon(Heroicon::OutlinedPencil),
        ];
    }
}
