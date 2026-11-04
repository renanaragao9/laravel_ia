<?php

namespace App\Console\Commands;

use App\Exceptions\PostAlreadyExistsException;
use App\Services\Posts\GeneratePostService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RuntimeException;

class GeneratePostCommand extends Command
{
    protected $signature = 'posts:generate {date? : Data AAAA-MM-DD (padrão: hoje)} {--force : Gera de novo se já existir um rascunho}';

    protected $description = 'Gera o rascunho do post do dia com o agente de IA (LLM + MCP + RAG)';

    public function handle(GeneratePostService $service): int
    {
        $date = $this->argument('date')
            ? Carbon::createFromFormat('Y-m-d', (string) $this->argument('date'), (string) config('bicho.timezone'))
            : Carbon::now((string) config('bicho.timezone'));

        try {
            $post = $service->run($date->startOfDay(), (bool) $this->option('force'));
        } catch (PostAlreadyExistsException $e) {
            $this->warn($e->getMessage());

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Rascunho criado: \"{$post->title}\" ({$post->post_date->format('d/m/Y')}). Revise no painel antes de publicar.");

        return self::SUCCESS;
    }
}
