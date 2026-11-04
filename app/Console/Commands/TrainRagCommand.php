<?php

namespace App\Console\Commands;

use App\Services\Rag\TrainKnowledgeService;
use Illuminate\Console\Command;

class TrainRagCommand extends Command
{
    protected $signature = 'rag:train';

    protected $description = 'Treina o índice local (TF-IDF) da base de conhecimento';

    public function handle(TrainKnowledgeService $trainKnowledgeService): int
    {
        $result = $trainKnowledgeService->run();

        $this->info("Índice treinado: {$result['documents']} documento(s), {$result['chunks']} trecho(s), {$result['terms']} termo(s).");

        return self::SUCCESS;
    }
}
