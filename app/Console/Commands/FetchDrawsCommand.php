<?php

namespace App\Console\Commands;

use App\Contracts\ResultSource;
use App\Services\Draws\FetchDrawsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RuntimeException;

class FetchDrawsCommand extends Command
{
    protected $signature = 'draws:fetch {date? : Data AAAA-MM-DD (padrão: hoje)}';

    protected $description = 'Coleta os resultados do dia na fonte configurada';

    public function handle(ResultSource $source, FetchDrawsService $service): int
    {
        if (! $source->isConfigured()) {
            $this->warn('Coleta automática desligada: defina RESULTS_SCRAPER_URL para ativá-la.');

            return self::SUCCESS;
        }

        $date = $this->argument('date')
            ? Carbon::createFromFormat('Y-m-d', (string) $this->argument('date'), (string) config('bicho.timezone'))
            : Carbon::now((string) config('bicho.timezone'));

        try {
            $stored = $service->run($date->startOfDay());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$stored} sorteio(s) de {$date->toDateString()} gravado(s).");

        return self::SUCCESS;
    }
}
