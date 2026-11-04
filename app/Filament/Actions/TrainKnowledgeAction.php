<?php

namespace App\Filament\Actions;

use App\Services\Rag\TrainKnowledgeService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class TrainKnowledgeAction
{
    public static function make(): Action
    {
        return Action::make('train')
            ->label('Treinar índice')
            ->icon(Heroicon::OutlinedCpuChip)
            ->requiresConfirmation()
            ->modalDescription('Reconstrói o índice de busca (RAG) com todos os documentos. Faça isso depois de criar ou editar documentos.')
            ->action(function (): void {
                $result = app(TrainKnowledgeService::class)->run();

                Notification::make()
                    ->title('Índice treinado')
                    ->body("{$result['documents']} documento(s), {$result['chunks']} trecho(s), {$result['terms']} termo(s).")
                    ->success()
                    ->send();
            });
    }
}
