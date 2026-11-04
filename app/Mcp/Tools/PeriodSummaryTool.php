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

#[Name('resumo_do_periodo')]
#[Description('Resumo de um período fechado (mês corrente, mês anterior ou ano corrente): total de sorteios, animais, dezenas e centenas mais frequentes, milhares que se repetiram (ex.: 1802) e os milhares do 1º prêmio mais recentes. Use para dar noção do histórico do mês e do ano.')]
class PeriodSummaryTool extends BichoTool
{
    public function __construct(private readonly DrawStatsService $drawStatsService) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'periodo' => $schema->string()->enum(['mes', 'mes_anterior', 'ano'])->description('mes = do dia 1 até "ate"; mes_anterior = o mês fechado anterior; ano = de 1º de janeiro até "ate". Padrão: mes.'),
            'premio' => $schema->integer()->min(1)->max(10)->description('Considerar só um prêmio (1 a 10). Omita para considerar do 1º ao 5º.'),
            'limite' => $schema->integer()->min(1)->max(25)->description('Quantos itens por lista. Padrão: 10.'),
            'ate' => $schema->string()->description('Data de referência AAAA-MM-DD. Padrão: hoje.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'periodo' => ['nullable', Rule::in(['mes', 'mes_anterior', 'ano'])],
            'premio' => ['nullable', 'integer', 'between:1,10'],
            'limite' => ['nullable', 'integer', 'between:1,25'],
            'ate' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $reference = $this->date($request, 'ate');
        $period = (string) ($request->get('periodo') ?? 'mes');

        [$from, $until] = match ($period) {
            'ano' => [$reference->copy()->startOfYear(), $reference],
            'mes_anterior' => [$reference->copy()->subMonthNoOverflow()->startOfMonth(), $reference->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            default => [$reference->copy()->startOfMonth(), $reference],
        };

        return Response::json([
            'periodo' => $period,
            'premio' => $this->position($request) ?? '1 a '.config('bicho.analysis.max_position'),
            'resumo' => $this->drawStatsService->periodSummary($from, $until, $this->position($request), (int) ($request->get('limite') ?? 10)),
            'aviso' => self::NOTE,
        ]);
    }
}
