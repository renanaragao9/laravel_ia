<?php

namespace App\Console\Commands;

use App\Services\Draws\BackfillDrawsService;
use App\Services\Draws\ExportDrawsXlsxService;
use App\Services\Draws\ImportDrawsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackfillDrawsCommand extends Command
{
    protected $signature = 'draws:backfill
        {from? : Data inicial AAAA-MM-DD (padrão: 1º de janeiro deste ano)}
        {to? : Data final AAAA-MM-DD (padrão: ontem)}
        {--xlsx= : Caminho do .xlsx de saída (padrão: storage/app/private/exports)}
        {--import : Também importa o arquivo gerado no banco configurado no .env}
        {--delay= : Pausa entre requisições, em milissegundos (padrão: LOTECE_DELAY_MS)}
        {--refresh : Ignora o cache e consulta tudo de novo}';

    protected $description = 'Coleta o histórico de resultados da fonte configurada e monta uma planilha .xlsx';

    public function handle(BackfillDrawsService $backfillDrawsService, ExportDrawsXlsxService $exportDrawsXlsxService, ImportDrawsService $importDrawsService): int
    {
        $timezone = (string) config('bicho.timezone');
        $from = $this->argument('from') ? Carbon::createFromFormat('Y-m-d', (string) $this->argument('from'), $timezone)->startOfDay() : Carbon::now($timezone)->startOfYear();
        $to = $this->argument('to') ? Carbon::createFromFormat('Y-m-d', (string) $this->argument('to'), $timezone)->startOfDay() : Carbon::now($timezone)->subDay()->startOfDay();

        if ($from->gt($to)) {
            $this->error('A data inicial é depois da final.');

            return self::FAILURE;
        }

        $delay = $this->option('delay') !== null ? (int) $this->option('delay') : (int) config('bicho.results.lotece.delay_ms');
        $total = (int) $from->diffInDays($to) + 1;
        $this->info("Coletando {$from->toDateString()} a {$to->toDateString()} ({$total} dias), pausa de {$delay} ms entre consultas...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $summary = $backfillDrawsService->run($from, $to, $delay, (bool) $this->option('refresh'), fn () => $bar->advance());

        $bar->finish();
        $this->newLine(2);

        $path = (string) ($this->option('xlsx') ?: storage_path("app/private/exports/resultados-{$from->toDateString()}_{$to->toDateString()}.xlsx"));
        $rows = $exportDrawsXlsxService->run($summary['draws'], $path);

        $this->info("{$summary['with_results']} dia(s) com resultado, ".count($summary['draws'])." extração(ões), {$rows} linha(s) em {$path}");
        $this->line("Consultados: {$summary['fetched']} | do cache: {$summary['cached']} | falhas: ".count($summary['failed']));

        foreach (array_slice($summary['failed'], 0, 10) as $failure) {
            $this->warn($failure);
        }

        if ($this->option('import')) {
            $result = $importDrawsService->run($path);
            $this->info("Importado: {$result['draws']} sorteio(s) e {$result['results']} resultado(s); {$result['skipped']} linha(s) ignorada(s).");
        }

        return $summary['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
