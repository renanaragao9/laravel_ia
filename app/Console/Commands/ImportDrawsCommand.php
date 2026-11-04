<?php

namespace App\Console\Commands;

use App\Services\Draws\ImportDrawsService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportDrawsCommand extends Command
{
    protected $signature = 'draws:import {file : Caminho de um arquivo .csv ou .xlsx}';

    protected $description = 'Importa resultados do histórico de uma planilha';

    public function handle(ImportDrawsService $importDrawsService): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        try {
            $summary = $importDrawsService->run($path);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$summary['draws']} sorteio(s) e {$summary['results']} resultado(s) importados; {$summary['skipped']} linha(s) ignorada(s).");

        foreach (array_slice($summary['errors'], 0, 10) as $error) {
            $this->warn($error);
        }

        return $summary['draws'] > 0 || $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
