<?php

namespace App\Filament\Actions;

use App\Services\Draws\ImportDrawsService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ImportDrawsAction
{
    public static function make(): Action
    {
        return Action::make('import')
            ->label('Importar planilha')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->modalHeading('Importar resultados (.csv ou .xlsx)')
            ->modalDescription('Colunas na primeira linha: data, loteria (opcional), horario (opcional), premio (1 a 5) e milhar. Também vale uma linha por sorteio com as colunas data, horario e p1 a p5. Reimportar atualiza os valores, sem duplicar.')
            ->modalSubmitActionLabel('Importar')
            ->schema([
                FileUpload::make('file')
                    ->label('Planilha')
                    ->disk('local')
                    ->directory('imports/draws')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(20480)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $path = Storage::disk('local')->path($data['file']);

                try {
                    $summary = app(ImportDrawsService::class)->run($path);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Não foi possível importar')->body($e->getMessage())->danger()->send();

                    return;
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }

                $text = "{$summary['draws']} sorteio(s) e {$summary['results']} resultado(s); {$summary['skipped']} linha(s) ignorada(s).";

                if ($summary['errors'] === []) {
                    Notification::make()->title('Importação concluída')->body($text)->success()->send();

                    return;
                }

                Notification::make()->title('Importação concluída com avisos')->body($text.' '.implode(' | ', array_slice($summary['errors'], 0, 5)))->warning()->send();
            });
    }
}
