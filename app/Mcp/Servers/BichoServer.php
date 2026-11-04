<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AnimalLookupTool;
use App\Mcp\Tools\DatabaseSummaryTool;
use App\Mcp\Tools\FrequencyTool;
use App\Mcp\Tools\OverdueTool;
use App\Mcp\Tools\ResultsOfDayTool;
use App\Mcp\Tools\SameDateHistoryTool;
use App\Mcp\Tools\SearchKnowledgeTool;
use App\Mcp\Tools\WeekdayProfileTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Bicho - histórico e conhecimento')]
#[Version('1.0.0')]
#[Instructions('Ferramentas para consultar o histórico de resultados do jogo do bicho e uma base de conhecimento (RAG). Comece por resumo_da_base para saber o intervalo de datas. Use resultados_do_dia, frequencia, atrasados, perfil_dia_da_semana e historico_mesma_data para estatística descritiva, consultar_animal para a tabela dos grupos e buscar_conhecimento para regras, significados e estilo. Todos os números são históricos: cada sorteio é aleatório e nada aqui prevê resultados.')]
class BichoServer extends Server
{
    protected array $tools = [
        DatabaseSummaryTool::class,
        ResultsOfDayTool::class,
        FrequencyTool::class,
        OverdueTool::class,
        WeekdayProfileTool::class,
        SameDateHistoryTool::class,
        AnimalLookupTool::class,
        SearchKnowledgeTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
