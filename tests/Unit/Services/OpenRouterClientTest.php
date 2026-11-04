<?php

namespace Tests\Unit\Services;

use App\Services\Llm\McpToolGateway;
use App\Services\Llm\OpenRouterClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenRouterClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['bicho.openrouter' => [
            'api_key' => 'sk-or-teste', 'base_url' => 'https://openrouter.test/api/v1', 'model' => 'anthropic/claude-teste',
            'referer' => 'https://meusite.test', 'title' => 'Laravel IA', 'timeout' => 30, 'max_steps' => 8, 'temperature' => 0.4,
        ]]);
    }

    public function test_sends_the_request_in_the_openrouter_format_and_parses_text_and_usage(): void
    {
        Http::fake(['openrouter.test/*' => Http::response([
            'model' => 'anthropic/claude-teste',
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Olá!']]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3],
        ])]);

        $response = app(OpenRouterClient::class)->chat([['role' => 'user', 'content' => 'oi']]);

        $this->assertSame('Olá!', $response->content);
        $this->assertSame([], $response->toolCalls);
        $this->assertSame([12, 3], [$response->promptTokens, $response->completionTokens]);
        $this->assertSame('anthropic/claude-teste', $response->model);

        Http::assertSent(fn ($request) => $request->url() === 'https://openrouter.test/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-or-teste')
            && $request->hasHeader('HTTP-Referer', 'https://meusite.test')
            && $request->hasHeader('X-Title', 'Laravel IA')
            && $request['model'] === 'anthropic/claude-teste'
            && $request['temperature'] === 0.4
            && ! isset($request['tools'])
            && ! isset($request['tool_choice']));
    }

    public function test_wraps_tools_as_functions_and_parses_tool_calls_with_json_arguments(): void
    {
        Http::fake(['openrouter.test/*' => Http::response([
            'choices' => [['message' => [
                'role' => 'assistant', 'content' => null,
                'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'frequencia', 'arguments' => '{"dias":30,"por":"animal"}']]],
            ]]],
        ])]);

        $response = app(OpenRouterClient::class)->chat(
            [['role' => 'user', 'content' => 'x']],
            [['name' => 'frequencia', 'description' => 'd', 'parameters' => ['type' => 'object', 'properties' => (object) []]]],
        );

        $this->assertNull($response->content);
        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('call_1', $response->toolCalls[0]->id);
        $this->assertSame('frequencia', $response->toolCalls[0]->name);
        $this->assertSame(['dias' => 30, 'por' => 'animal'], $response->toolCalls[0]->arguments);
        $this->assertSame('call_1', $response->message['tool_calls'][0]['id']);

        Http::assertSent(fn ($request) => $request['tools'][0]['type'] === 'function'
            && $request['tools'][0]['function']['name'] === 'frequencia'
            && $request['tool_choice'] === 'auto');
    }

    public function test_malformed_tool_arguments_become_an_empty_array(): void
    {
        Http::fake(['openrouter.test/*' => Http::response([
            'choices' => [['message' => ['tool_calls' => [['id' => 'c', 'function' => ['name' => 'x', 'arguments' => 'não é json']]]]]],
        ])]);

        $this->assertSame([], app(OpenRouterClient::class)->chat([['role' => 'user', 'content' => 'x']])->toolCalls[0]->arguments);
    }

    public function test_errors_are_reported_with_the_provider_message(): void
    {
        Http::fake(['openrouter.test/*' => Http::response(['error' => ['message' => 'Sem créditos', 'code' => 402]], 402)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sem créditos');
        app(OpenRouterClient::class)->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_an_error_inside_a_200_response_is_also_an_error(): void
    {
        Http::fake(['openrouter.test/*' => Http::response(['error' => ['message' => 'Modelo indisponível']])]);

        $this->expectExceptionMessage('Modelo indisponível');
        app(OpenRouterClient::class)->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_requires_an_api_key_and_a_message_in_the_response(): void
    {
        config(['bicho.openrouter.api_key' => '']);
        Http::fake();

        try {
            app(OpenRouterClient::class)->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail('Deveria exigir a chave.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('OPENROUTER_API_KEY', $e->getMessage());
        }

        Http::assertNothingSent();

        config(['bicho.openrouter.api_key' => 'k']);
        Http::fake(['openrouter.test/*' => Http::response(['choices' => []])]);
        $this->expectExceptionMessage('sem mensagem');
        app(OpenRouterClient::class)->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_the_mcp_gateway_converts_tools_to_function_definitions(): void
    {
        $function = app(McpToolGateway::class)->toFunction('frequencia', 'Conta ocorrências', ['properties' => [], '$schema' => 'x']);

        $this->assertSame('frequencia', $function['name']);
        $this->assertSame('Conta ocorrências', $function['description']);
        $this->assertSame('object', $function['parameters']['type']);
        $this->assertEquals((object) [], $function['parameters']['properties']);
        $this->assertArrayNotHasKey('$schema', $function['parameters']);
        $this->assertSame('', app(McpToolGateway::class)->toFunction('x', null, [])['description']);
    }
}
