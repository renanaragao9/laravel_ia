<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Stats\DrawStatsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('historico_mesma_data')]
#[Description('Mostra os resultados que saíram no mesmo dia e mês em anos anteriores.')]
class SameDateHistoryTool extends BichoTool
{
    public function __construct(private readonly DrawStatsService $drawStatsService) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->string()->description('Data de referência AAAA-MM-DD. Padrão: hoje.'),
            'anos' => $schema->integer()->min(1)->max(30)->description('Quantos anos voltar. Padrão: 10.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'data' => ['nullable', 'date_format:Y-m-d'],
            'anos' => ['nullable', 'integer', 'between:1,30'],
        ]);

        $date = $this->date($request, 'data');

        return Response::json([
            'referencia' => $date->toDateString(),
            'anos' => (int) ($request->get('anos') ?? 10),
            'historico' => $this->drawStatsService->sameDateHistory($date, (int) ($request->get('anos') ?? 10)),
            'aviso' => self::NOTE,
        ]);
    }
}
