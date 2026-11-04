<?php

namespace App\Filament\Actions;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\Posts\PublishPostService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class UnpublishPostAction
{
    public static function make(): Action
    {
        return Action::make('unpublish')
            ->label('Despublicar')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Post $record) => $record->status === PostStatus::Published)
            ->action(function (Post $record): void {
                app(PublishPostService::class)->unpublish($record);

                Notification::make()->title('Post voltou para rascunho')->success()->send();
            });
    }
}
