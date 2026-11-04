<?php

namespace App\Filament\Actions;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\Posts\PublishPostService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class PublishPostAction
{
    public static function make(): Action
    {
        return Action::make('publish')
            ->label('Publicar')
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('O post passa a aparecer no blog público. Confira os números e o aviso legal antes.')
            ->visible(fn (Post $record) => $record->status === PostStatus::Draft)
            ->action(function (Post $record): void {
                app(PublishPostService::class)->publish($record);

                Notification::make()->title('Post publicado')->success()->send();
            });
    }
}
