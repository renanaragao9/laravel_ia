<?php

namespace Tests\Feature;

use App\Mcp\Servers\BichoServer;
use App\Mcp\Tools\AnimalLookupTool;
use App\Mcp\Tools\DatabaseSummaryTool;
use App\Mcp\Tools\FrequencyTool;
use App\Mcp\Tools\OverdueTool;
use App\Mcp\Tools\ResultsOfDayTool;
use App\Mcp\Tools\SameDateHistoryTool;
use App\Mcp\Tools\SearchKnowledgeTool;
use App\Mcp\Tools\WeekdayProfileTool;
use App\Services\Rag\TrainKnowledgeService;
use Database\Seeders\AnimalSeeder;
use Database\Seeders\KnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\MakesDraws;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use MakesDraws;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bicho.timezone' => 'America/Sao_Paulo']);
        Carbon::setTestNow('2026-11-10 12:00:00');
        $this->seed(AnimalSeeder::class);
        $this->draw('2026-11-09', [1 => '7345', 2 => '0033'], '14:00');
        $this->draw('2025-11-10', [1 => '0050'], '18:00');
    }

    public function test_the_server_lists_every_tool_with_a_description_and_schema(): void
    {
        config(['bicho.mcp.token' => 'segredo-mcp']);
        $headers = ['Accept' => 'application/json, text/event-stream', 'Authorization' => 'Bearer segredo-mcp'];
        $this->postJson('/mcp/bicho', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'teste', 'version' => '1']]], $headers);

        $tools = collect($this->postJson('/mcp/bicho', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $headers)->assertOk()->json('result.tools'));

        $this->assertEqualsCanonicalizing(
            ['resumo_da_base', 'resultados_do_dia', 'frequencia', 'atrasados', 'perfil_dia_da_semana', 'historico_mesma_data', 'consultar_animal', 'buscar_conhecimento'],
            $tools->pluck('name')->all(),
        );
        $this->assertTrue($tools->every(fn (array $tool) => $tool['description'] !== '' && isset($tool['inputSchema'])));

        foreach (['resumo_da_base', 'resultados_do_dia', 'frequencia', 'atrasados', 'perfil_dia_da_semana', 'historico_mesma_data', 'consultar_animal', 'buscar_conhecimento'] as $name) {
            BichoServer::tool(match ($name) {
                'resumo_da_base' => DatabaseSummaryTool::class,
                'resultados_do_dia' => ResultsOfDayTool::class,
                'frequencia' => FrequencyTool::class,
                'atrasados' => OverdueTool::class,
                'perfil_dia_da_semana' => WeekdayProfileTool::class,
                'historico_mesma_data' => SameDateHistoryTool::class,
                'consultar_animal' => AnimalLookupTool::class,
                'buscar_conhecimento' => SearchKnowledgeTool::class,
            }, $name === 'buscar_conhecimento' ? ['pergunta' => 'milhar'] : [])->assertName($name);
        }
    }

    public function test_summary_reports_today_and_the_covered_range(): void
    {
        BichoServer::tool(DatabaseSummaryTool::class)
            ->assertOk()
            ->assertSee(['2026-11-10', 'terça-feira', '2025-11-10', '2026-11-09']);
    }

    public function test_results_of_day_returns_the_draws_for_the_requested_date(): void
    {
        BichoServer::tool(ResultsOfDayTool::class, ['data' => '2026-11-09'])
            ->assertOk()
            ->assertSee(['7345', '12 - Elefante', '33', '9 - Cobra', '14:00']);

        BichoServer::tool(ResultsOfDayTool::class, ['data' => '2026-01-01'])->assertSee('Não há resultados');
    }

    public function test_frequency_overdue_weekday_and_same_date_tools_return_structured_data(): void
    {
        BichoServer::tool(FrequencyTool::class, ['dias' => 30, 'por' => 'animal', 'limite' => 5])
            ->assertOk()
            ->assertSee(['12 - Elefante', '9 - Cobra', 'não é uma previsão']);

        BichoServer::tool(FrequencyTool::class, ['dias' => 30, 'por' => 'dezena', 'premio' => 2])->assertSee('33 (Cobra)');

        BichoServer::tool(OverdueTool::class, ['por' => 'animal', 'limite' => 3])->assertOk()->assertSee('days_since');

        BichoServer::tool(WeekdayProfileTool::class, ['dia_da_semana' => 1, 'dias' => 30])->assertOk()->assertSee('12 - Elefante');

        BichoServer::tool(SameDateHistoryTool::class, ['data' => '2026-11-10', 'anos' => 3])->assertOk()->assertSee(['2025-11-10', '0050']);
    }

    public function test_invalid_arguments_are_rejected_with_errors(): void
    {
        BichoServer::tool(ResultsOfDayTool::class, ['data' => '10/11/2026'])->assertHasErrors();
        BichoServer::tool(FrequencyTool::class, ['dias' => 0])->assertHasErrors();
        BichoServer::tool(FrequencyTool::class, ['por' => 'milhar'])->assertHasErrors();
        BichoServer::tool(OverdueTool::class, ['premio' => 9])->assertHasErrors();
        BichoServer::tool(SearchKnowledgeTool::class, ['pergunta' => ''])->assertHasErrors();
    }

    public function test_animal_lookup_by_group_name_and_ten(): void
    {
        BichoServer::tool(AnimalLookupTool::class, ['grupo' => 25])->assertSee(['Vaca', '97', '00']);
        BichoServer::tool(AnimalLookupTool::class, ['nome' => 'cobra'])->assertSee(['"grupo":9', '33']);
        BichoServer::tool(AnimalLookupTool::class, ['dezena' => '00'])->assertSee('Vaca');
        BichoServer::tool(AnimalLookupTool::class, [])->assertHasErrors(['Animal não encontrado']);
        BichoServer::tool(AnimalLookupTool::class, ['nome' => 'dragão'])->assertHasErrors(['Animal não encontrado']);
    }

    public function test_knowledge_search_uses_the_rag_index(): void
    {
        $this->seed(KnowledgeSeeder::class);
        app(TrainKnowledgeService::class)->run();

        BichoServer::tool(SearchKnowledgeTool::class, ['pergunta' => 'o que é milhar e dezena', 'limite' => 2])
            ->assertOk()
            ->assertSee('Termos e regras do jogo do bicho');

        BichoServer::tool(SearchKnowledgeTool::class, ['pergunta' => 'cobra veneno', 'tipos' => ['dreams']])
            ->assertSee('Cobra (grupo 9)');

        BichoServer::tool(SearchKnowledgeTool::class, ['pergunta' => 'astrofísica quântica'])->assertSee('Nada relevante');
    }

    public function test_the_http_endpoint_requires_the_bearer_token(): void
    {
        $payload = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'teste', 'version' => '1']]];
        $headers = ['Accept' => 'application/json, text/event-stream'];

        config(['bicho.mcp.token' => '']);
        $this->postJson('/mcp/bicho', $payload, $headers)->assertForbidden();

        config(['bicho.mcp.token' => 'segredo-mcp']);
        $this->postJson('/mcp/bicho', $payload, $headers)->assertUnauthorized();
        $this->postJson('/mcp/bicho', $payload, $headers + ['Authorization' => 'Bearer errado'])->assertUnauthorized();

        $this->postJson('/mcp/bicho', $payload, $headers + ['Authorization' => 'Bearer segredo-mcp'])
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Bicho - histórico e conhecimento');
    }
}
