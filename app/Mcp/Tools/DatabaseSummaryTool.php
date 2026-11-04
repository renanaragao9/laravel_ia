<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Stats\DrawStatsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('resumo_da_base')]
#[Description('Informa a data de hoje, o dia da semana, quantos sorteios e resultados existem e o intervalo de datas coberto pela base.')]
class DatabaseSummaryTool extends BichoTool
{
    public function __construct(private readonly DrawStatsService $stats) {}

    public function schema(JsonSchema $schema): array
    {
        return [
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate([
        ]);

        $now = $this->date($request, 'hoje');

        return Response::json([
            'hoje' => $now->toDateString(),
            'dia_da_semana' => $now->locale('pt_BR')->dayName,
            'base' => $this->stats->totals(),
        ]);
    }
}
