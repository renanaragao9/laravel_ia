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

#[Name('frequencia')]
#[Description('Conta quantas vezes cada animal ou dezena saiu nos últimos N dias, com a participação percentual. É estatística descritiva do passado.')]
class FrequencyTool extends BichoTool
{
    public function __construct(private readonly DrawStatsService $drawStatsService) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'dias' => $schema->integer()->min(1)->max(3650)->description('Tamanho da janela em dias. Padrão: 90.'),
            'por' => $schema->string()->enum(['animal', 'dezena', 'centena', 'milhar'])->description('Agrupar por animal (grupo), dezena (2 dígitos), centena (3) ou milhar (4 dígitos, ex.: 1802). Padrão: animal.'),
            'premio' => $schema->integer()->min(1)->max(10)->description('Considerar só um prêmio (1 a 10). Omita para considerar do 1º ao 5º.'),
            'limite' => $schema->integer()->min(1)->max(25)->description('Quantos itens devolver. Padrão: 10.'),
            'ate' => $schema->string()->description('Data final da análise no formato AAAA-MM-DD. Padrão: hoje.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'dias' => ['nullable', 'integer', 'between:1,3650'],
            'por' => ['nullable', Rule::in(['animal', 'dezena', 'centena', 'milhar'])],
            'premio' => ['nullable', 'integer', 'between:1,10'],
            'limite' => ['nullable', 'integer', 'between:1,25'],
            'ate' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $days = (int) ($request->get('dias') ?? 90);
        $until = $this->date($request, 'ate');

        return Response::json([
            'periodo' => ['dias' => $days, 'de' => $until->copy()->subDays($days - 1)->toDateString(), 'ate' => $until->toDateString()],
            'agrupado_por' => match ($this->groupBy($request)) {
                'ten' => 'dezena',
                'hundred' => 'centena',
                'thousand' => 'milhar',
                default => 'animal',
            },
            'premio' => $this->position($request) ?? '1 a '.config('bicho.analysis.max_position'),
            'itens' => $this->drawStatsService->frequency($days, $this->groupBy($request), $this->position($request), $until, (int) ($request->get('limite') ?? 10)),
            'aviso' => self::NOTE,
        ]);
    }
}
