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

#[Name('perfil_dia_da_semana')]
#[Description('Frequência de animais ou dezenas considerando só os dias da mesma semana (por exemplo, só as segundas-feiras) na janela escolhida.')]
class WeekdayProfileTool extends BichoTool
{
    public function __construct(private readonly DrawStatsService $stats) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'dia_da_semana' => $schema->integer()->min(0)->max(6)->description('0=domingo até 6=sábado. Se omitido, usa o dia da semana de "ate".'),
            'dias' => $schema->integer()->min(7)->max(3650)->description('Tamanho da janela em dias. Padrão: 365.'),
            'por' => $schema->string()->enum(['animal', 'dezena'])->description('Agrupar por animal (grupo) ou por dezena. Padrão: animal.'),
            'premio' => $schema->integer()->min(1)->max(5)->description('Considerar só um prêmio (1 a 5). Omita para considerar todos.'),
            'limite' => $schema->integer()->min(1)->max(25)->description('Quantos itens devolver. Padrão: 10.'),
            'ate' => $schema->string()->description('Data final da análise no formato AAAA-MM-DD. Padrão: hoje.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'dia_da_semana' => ['nullable', 'integer', 'between:0,6'],
            'dias' => ['nullable', 'integer', 'between:7,3650'],
            'por' => ['nullable', Rule::in(['animal', 'dezena'])],
            'premio' => ['nullable', 'integer', 'between:1,5'],
            'limite' => ['nullable', 'integer', 'between:1,25'],
            'ate' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $until = $this->date($request, 'ate');
        $weekday = $request->get('dia_da_semana') === null ? $until->dayOfWeek : (int) $request->get('dia_da_semana');
        $days = (int) ($request->get('dias') ?? 365);

        return Response::json([
            'dia_da_semana' => $weekday,
            'periodo' => ['dias' => $days, 'ate' => $until->toDateString()],
            'agrupado_por' => $this->groupBy($request) === 'ten' ? 'dezena' : 'animal',
            'itens' => $this->stats->weekdayProfile($weekday, $days, $this->groupBy($request), $this->position($request), $until, (int) ($request->get('limite') ?? 10)),
            'aviso' => self::NOTE,
        ]);
    }
}
