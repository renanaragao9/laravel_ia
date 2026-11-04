<?php

namespace App\Filament\Actions;

use App\Contracts\ResultSource;
use App\Services\Draws\FetchDrawsService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use RuntimeException;

class FetchDrawsAction
{
    public static function make(): Action
    {
        return Action::make('fetch')
            ->label('Coletar resultados')
            ->icon(Heroicon::OutlinedCloudArrowDown)
            ->color('gray')
            ->visible(fn () => app(ResultSource::class)->isConfigured())
            ->modalHeading('Coletar resultados de um dia')
            ->modalSubmitActionLabel('Coletar')
            ->schema([
                DatePicker::make('date')
                    ->label('Data')
                    ->default(fn () => Carbon::now((string) config('bicho.timezone'))->toDateString())
                    ->maxDate(fn () => Carbon::now((string) config('bicho.timezone')))
                    ->required(),
            ])
            ->action(function (array $data): void {
                try {
                    $stored = app(FetchDrawsService::class)->run(Carbon::parse($data['date'])->startOfDay());
                } catch (RuntimeException $e) {
                    Notification::make()->title('Falha na coleta')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title("{$stored} sorteio(s) gravado(s)")->success()->send();
            });
    }
}
