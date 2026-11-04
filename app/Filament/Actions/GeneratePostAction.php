<?php

namespace App\Filament\Actions;

use App\Jobs\GeneratePostJob;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class GeneratePostAction
{
    public static function make(): Action
    {
        return Action::make('generate')
            ->label('Gerar post')
            ->icon(Heroicon::OutlinedSparkles)
            ->modalHeading('Gerar o post de um dia com a IA')
            ->modalDescription('A IA consulta o histórico e a base de conhecimento e cria um rascunho para você revisar. Roda em segundo plano (a fila precisa estar ativa).')
            ->modalSubmitActionLabel('Gerar')
            ->schema([
                DatePicker::make('date')
                    ->label('Data do post')
                    ->default(fn () => Carbon::now((string) config('bicho.timezone'))->toDateString())
                    ->required(),

                Toggle::make('force')
                    ->label('Substituir o rascunho existente desta data')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                if (empty(config('bicho.openrouter.api_key'))) {
                    Notification::make()->title('OPENROUTER_API_KEY não configurada')->body('Defina a chave no .env para gerar posts.')->danger()->send();

                    return;
                }

                GeneratePostJob::dispatch(Carbon::parse($data['date'])->toDateString(), (bool) ($data['force'] ?? false));

                Notification::make()->title('Geração iniciada')->body('O rascunho aparece na lista quando terminar.')->success()->send();
            });
    }
}
