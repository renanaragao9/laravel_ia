<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use App\Support\Llm\LlmResponse;
use App\Support\Llm\ToolCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterClient implements LlmClient
{
    public function chat(array $messages, array $tools = []): LlmResponse
    {
        $config = (array) config('bicho.openrouter');

        if (empty($config['api_key'])) {
            throw new RuntimeException('OPENROUTER_API_KEY não configurada.');
        }

        $models = array_values(array_filter(array_map('trim', explode(',', (string) $config['model']))));

        $payload = [
            'model' => $models[0] ?? 'openrouter/auto',
            'messages' => $messages,
            'temperature' => $config['temperature'],
        ];

        if (count($models) > 1) {
            $payload['models'] = array_slice($models, 0, 3);
        }

        if ($tools !== []) {
            $payload['tools'] = array_map(fn (array $tool): array => ['type' => 'function', 'function' => $tool], $tools);
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withToken((string) $config['api_key'])
                ->withHeaders(['HTTP-Referer' => (string) $config['referer'], 'X-Title' => (string) $config['title']])
                ->acceptJson()
                ->timeout((int) $config['timeout'])
                ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Falha de conexão com o OpenRouter: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed() || $response->json('error') !== null) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException('OpenRouter retornou erro '.$response->status().': '.$message);
        }

        $message = (array) $response->json('choices.0.message');

        if ($message === []) {
            throw new RuntimeException('Resposta do OpenRouter sem mensagem.');
        }

        $calls = [];

        foreach ((array) ($message['tool_calls'] ?? []) as $call) {
            $arguments = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);

            $calls[] = new ToolCall(
                (string) ($call['id'] ?? uniqid('call_')),
                (string) ($call['function']['name'] ?? ''),
                is_array($arguments) ? $arguments : [],
            );
        }

        return new LlmResponse(
            content: isset($message['content']) ? (string) $message['content'] : null,
            toolCalls: $calls,
            message: $message,
            model: (string) ($response->json('model') ?? $config['model']),
            promptTokens: (int) $response->json('usage.prompt_tokens', 0),
            completionTokens: (int) $response->json('usage.completion_tokens', 0),
        );
    }
}
