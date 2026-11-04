<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Stats\DrawStatsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('resultados_do_dia')]
#[Description('Devolve todos os resultados (extrações, prêmios, milhar, dezena e animal) de uma data.')]
class ResultsOfDayTool extends BichoTool
{
    public function __construct(private readonly DrawStatsService $stats) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->string()->description('Data no formato AAAA-MM-DD. Padrão: hoje.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'data' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = $this->date($request, 'data');
        $results = $this->stats->dayResults($date);

        return Response::json([
            'data' => $date->toDateString(),
            'dia_da_semana' => $date->locale('pt_BR')->dayName,
            'extracoes' => $results === [] ? [] : $results[0]['draws'],
            'aviso' => $results === [] ? 'Não há resultados cadastrados para esta data.' : null,
        ]);
    }
}
