<?php

namespace App\Console\Commands;

use App\Contracts\ResultSource;
use App\Services\Draws\FetchDrawsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RuntimeException;

class FetchDrawsCommand extends Command
{
    protected $signature = 'draws:fetch
        {date? : Data AAAA-MM-DD (sem data, coleta hoje e ontem)}';

    protected $description = 'Coleta os resultados recentes na fonte configurada';

    public function handle(ResultSource $resultSource, FetchDrawsService $fetchDrawsService): int
    {
        if (! $resultSource->isConfigured()) {
            $this->warn('Coleta automática desligada: configure a fonte de resultados para ativá-la.');

            return self::SUCCESS;
        }

        $timezone = (string) config('bicho.timezone');
        $dates = $this->argument('date')
            ? [Carbon::createFromFormat('Y-m-d', (string) $this->argument('date'), $timezone)->startOfDay()]
            : [Carbon::now($timezone)->subDay()->startOfDay(), Carbon::now($timezone)->startOfDay()];

        $status = self::SUCCESS;

        foreach ($dates as $date) {
            try {
                $stored = $fetchDrawsService->run($date);
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());
                $status = self::FAILURE;

                continue;
            }

            $this->info("{$stored} sorteio(s) de {$date->toDateString()} gravado(s).");
        }

        return $status;
    }
}
