<?php

namespace Tests\Unit\Services;

use App\Contracts\LlmClient;
use App\Contracts\ToolGateway;
use App\Services\Posts\PostAgentService;
use App\Services\Rag\TrainKnowledgeService;
use Database\Seeders\KnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Support\FakeGateway;
use Tests\Support\FakeLlm;
use Tests\TestCase;

class PostAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bicho.openrouter.max_steps' => 4, 'bicho.openrouter.model' => 'config/model']);
    }

    private function agent(FakeLlm $llm, ?FakeGateway $gateway = null): PostAgentService
    {
        $this->app->instance(LlmClient::class, $llm);
        $this->app->instance(ToolGateway::class, $gateway ?? new FakeGateway);

        return app(PostAgentService::class);
    }

    public function test_runs_the_tool_loop_and_returns_the_final_post(): void
    {
        $llm = new FakeLlm([
            FakeLlm::tools(['c1' => ['name' => 'resumo_da_base'], 'c2' => ['name' => 'frequencia', 'arguments' => ['dias' => 30]]], 100, 10),
            FakeLlm::text(FakeLlm::post('Título legal'), 200, 50),
        ]);
        $gateway = new FakeGateway(['frequencia' => '{"itens":[{"label":"9 - Cobra"}]}']);

        $post = $this->agent($llm, $gateway)->run(Carbon::parse('2026-11-04'));

        $this->assertSame('Título legal', $post['title']);
        $this->assertSame('Resumo curto', $post['excerpt']);
        $this->assertSame([9, 12], $post['guesses']['animals']);
        $this->assertSame(['33', '45'], $post['guesses']['tens']);
        $this->assertSame(300, $post['prompt_tokens']);
        $this->assertSame(60, $post['completion_tokens']);
        $this->assertSame('fake/model', $post['model']);
        $this->assertSame(['resumo_da_base', 'frequencia'], array_column($gateway->calls, 'name'));
        $this->assertSame(['dias' => 30], $gateway->calls[1]['arguments']);
        $this->assertSame(['resumo_da_base', 'frequencia'], array_column($post['context']['tool_calls'], 'name'));
        $this->assertTrue($gateway->closed);
    }

    public function test_the_tool_results_are_fed_back_to_the_model_in_openai_format(): void
    {
        $llm = new FakeLlm([FakeLlm::tools(['c1' => ['name' => 'frequencia']]), FakeLlm::text(FakeLlm::post())]);

        $this->agent($llm, new FakeGateway(['frequencia' => '{"x":1}']))->run(Carbon::parse('2026-11-04'));

        $second = $llm->calls[1]['messages'];
        $this->assertSame('assistant', $second[2]['role']);
        $this->assertSame('tool', $second[3]['role']);
        $this->assertSame('c1', $second[3]['tool_call_id']);
        $this->assertSame('{"x":1}', $second[3]['content']);
        $this->assertCount(2, $llm->calls[0]['tools']);
    }

    public function test_the_prompts_carry_the_date_the_rules_and_the_retrieved_style_guide(): void
    {
        $this->seed(KnowledgeSeeder::class);
        app(TrainKnowledgeService::class)->run();
        $llm = new FakeLlm([FakeLlm::text(FakeLlm::post())]);

        $this->agent($llm)->run(Carbon::parse('2026-11-04'));

        $system = $llm->calls[0]['messages'][0]['content'];
        $user = $llm->calls[0]['messages'][1]['content'];

        $this->assertStringContainsString('aleatório', $system);
        $this->assertStringContainsString('maiores de 18 anos', $system);
        $this->assertStringContainsString('Diretrizes editoriais recuperadas', $system);
        $this->assertStringContainsString('2026-11-04', $user);
        $this->assertStringContainsString('quarta-feira', $user);
    }

    public function test_accepts_json_wrapped_in_a_code_fence_and_extra_text(): void
    {
        $content = "Claro! Aqui está:\n```json\n".FakeLlm::post('Com cerca')."\n```\nEspero que goste.";

        $post = $this->agent(new FakeLlm([FakeLlm::text($content)]))->run(Carbon::parse('2026-11-04'));

        $this->assertSame('Com cerca', $post['title']);
    }

    public function test_asks_once_more_when_the_first_answer_is_not_json_and_then_gives_up(): void
    {
        $llm = new FakeLlm([FakeLlm::text('texto solto'), FakeLlm::text(FakeLlm::post('Corrigido'))]);
        $this->assertSame('Corrigido', $this->agent($llm)->run(Carbon::parse('2026-11-04'))['title']);
        $this->assertStringContainsString('JSON válido', $llm->calls[1]['messages'][3]['content']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JSON válido');
        $this->agent(new FakeLlm([FakeLlm::text('ruim'), FakeLlm::text('ainda ruim')]))->run(Carbon::parse('2026-11-04'));
    }

    public function test_forces_a_final_answer_without_tools_when_the_step_limit_is_reached(): void
    {
        $llm = new FakeLlm([
            ...array_map(fn (int $i) => FakeLlm::tools(["c{$i}" => ['name' => 'frequencia']]), range(1, 4)),
            FakeLlm::text(FakeLlm::post('Forçado')),
        ]);

        $post = $this->agent($llm)->run(Carbon::parse('2026-11-04'));

        $this->assertSame('Forçado', $post['title']);
        $this->assertCount(5, $llm->calls);
        $this->assertSame([], $llm->calls[4]['tools']);
        $this->assertStringContainsString('Chega de consultas', end($llm->calls[4]['messages'])['content']);
    }

    public function test_guesses_are_sanitized(): void
    {
        $guesses = ['animals' => [9, '12', 9, 0, 26, 'x', 1, 2, 3, 4, 5, 6], 'tens' => ['33', 5, '100', -1, 'ab', '33', 7, 8, 9, 10, 11, 12, 13, 14, 15]];

        $post = $this->agent(new FakeLlm([FakeLlm::text(FakeLlm::post('T', 'corpo', $guesses))]))->run(Carbon::parse('2026-11-04'));

        $this->assertSame([9, 12, 1, 2, 3], $post['guesses']['animals']);
        $this->assertSame(['33', '05', '07', '08', '09', '10', '11', '12', '13', '14'], $post['guesses']['tens']);
    }

    public function test_hundred_and_thousand_guesses_are_sanitized(): void
    {
        $guesses = ['animals' => [], 'tens' => [], 'hundreds' => ['802', 12, 'abcd', '12345', '802'], 'thousands' => ['1802', '0050', 7, '99999', '1.802', 1, 2, 3, 4, 5]];

        $post = $this->agent(new FakeLlm([FakeLlm::text(FakeLlm::post('T', 'corpo', $guesses))]))->run(Carbon::parse('2026-11-04'));

        $this->assertSame(['802', '012'], $post['guesses']['hundreds']);
        $this->assertSame(['1802', '0050', '0007', '0001', '0002'], $post['guesses']['thousands']);
    }

    public function test_rejects_posts_without_title_or_body(): void
    {
        $this->assertNull(app(PostAgentService::class)->parse('{"title":"","body_markdown":"x"}'));
        $this->assertNull(app(PostAgentService::class)->parse('{"title":"x","body_markdown":""}'));
        $this->assertNull(app(PostAgentService::class)->parse('[]'));
        $this->assertNull(app(PostAgentService::class)->parse(''));
        $this->assertSame('corpo', app(PostAgentService::class)->parse('{"title":"x","body":"corpo"}')['body']);
    }

    public function test_the_gateway_is_closed_even_when_the_model_fails(): void
    {
        $gateway = new FakeGateway;

        try {
            $this->agent(new FakeLlm([]), $gateway)->run(Carbon::parse('2026-11-04'));
            $this->fail('Deveria falhar sem respostas.');
        } catch (RuntimeException) {
            $this->assertTrue($gateway->closed);
        }
    }
}
