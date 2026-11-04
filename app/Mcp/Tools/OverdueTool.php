<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Stats\DrawStatsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('atrasados')]
#[Description('Lista os animais ou dezenas que estão há mais tempo sem sair (atraso em dias e em extrações). É só uma contagem, o atraso não torna nada mais provável.')]
class OverdueTool extends BichoTool
{
    public function __construct(private readonly DrawStatsService $stats) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'por' => $schema->string()->enum(['animal', 'dezena'])->description('Agrupar por animal (grupo) ou por dezena. Padrão: animal.'),
            'premio' => $schema->integer()->min(1)->max(5)->description('Considerar só um prêmio (1 a 5). Omita para considerar todos.'),
            'limite' => $schema->integer()->min(1)->max(25)->description('Quantos itens devolver. Padrão: 10.'),
            'ate' => $schema->string()->description('Data final da análise no formato AAAA-MM-DD. Padrão: hoje.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'por' => ['nullable', Rule::in(['animal', 'dezena'])],
            'premio' => ['nullable', 'integer', 'between:1,5'],
            'limite' => ['nullable', 'integer', 'between:1,25'],
            'ate' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $until = $this->date($request, 'ate');

        return Response::json([
            'ate' => $until->toDateString(),
            'agrupado_por' => $this->groupBy($request) === 'ten' ? 'dezena' : 'animal',
            'premio' => $this->position($request) ?? 'todos',
            'itens' => $this->stats->overdue($this->groupBy($request), $this->position($request), $until, (int) ($request->get('limite') ?? 10)),
            'aviso' => self::NOTE,
        ]);
    }
}
