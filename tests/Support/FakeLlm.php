<?php

namespace Tests\Support;

use App\Contracts\LlmClient;
use App\Support\Llm\LlmResponse;
use App\Support\Llm\ToolCall;
use RuntimeException;

class FakeLlm implements LlmClient
{
    public array $calls = [];

    public function __construct(private array $responses) {}

    public function chat(array $messages, array $tools = []): LlmResponse
    {
        $this->calls[] = ['messages' => $messages, 'tools' => $tools];

        return array_shift($this->responses) ?? throw new RuntimeException('FakeLlm sem respostas restantes.');
    }

    public static function text(string $content, int $in = 10, int $out = 5): LlmResponse
    {
        return new LlmResponse($content, [], ['role' => 'assistant', 'content' => $content], 'fake/model', $in, $out);
    }

    public static function tools(array $calls, int $in = 10, int $out = 5): LlmResponse
    {
        $toolCalls = [];
        $raw = [];

        foreach ($calls as $id => $call) {
            $toolCalls[] = new ToolCall((string) $id, $call['name'], $call['arguments'] ?? []);
            $raw[] = ['id' => (string) $id, 'type' => 'function', 'function' => ['name' => $call['name'], 'arguments' => json_encode($call['arguments'] ?? [])]];
        }

        return new LlmResponse(null, $toolCalls, ['role' => 'assistant', 'content' => null, 'tool_calls' => $raw], 'fake/model', $in, $out);
    }

    public static function post(string $title = 'Palpites do dia', string $body = "## Resumo\n\nTexto do post.", array $guesses = ['animals' => [9, 12], 'tens' => ['33', '45']]): string
    {
        return json_encode(['title' => $title, 'excerpt' => 'Resumo curto', 'body_markdown' => $body, 'guesses' => $guesses], JSON_UNESCAPED_UNICODE);
    }
}
