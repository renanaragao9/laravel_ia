<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Contracts\LlmClient;
use App\Contracts\ToolGateway;
use App\Enums\KnowledgeKind;
use App\Services\Rag\RetrieveKnowledgeService;
use App\Support\Bicho\Bicho;
use App\Support\Llm\LlmResponse;
use Carbon\CarbonInterface;
use JsonException;
use RuntimeException;

class PostAgentService
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly ToolGateway $tools,
        private readonly RetrieveKnowledgeService $retrieve,
    ) {}

    public function run(CarbonInterface $date): array
    {
        $style = $this->retrieve->run('guia de estilo tom estrutura regras editoriais do post', 2, [KnowledgeKind::Style->value, KnowledgeKind::Rules->value]);

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($style)],
            ['role' => 'user', 'content' => $this->userPrompt($date)],
        ];

        $log = ['tool_calls' => [], 'style_sources' => array_values(array_unique(array_column($style, 'title')))];
        $usage = ['prompt' => 0, 'completion' => 0];
        $model = (string) config('bicho.openrouter.model');

        try {
            $definitions = $this->tools->tools();
            $final = null;

            for ($step = 0; $step < (int) config('bicho.openrouter.max_steps'); $step++) {
                $response = $this->llm->chat($messages, $definitions);
                $this->account($response, $usage, $model);

                if ($response->toolCalls === []) {
                    $final = $response;

                    break;
                }

                $messages[] = $response->message;

                foreach ($response->toolCalls as $call) {
                    $result = $this->tools->call($call->name, $call->arguments);
                    $log['tool_calls'][] = ['name' => $call->name, 'arguments' => $call->arguments, 'result' => mb_substr($result, 0, 2500)];
                    $messages[] = ['role' => 'tool', 'tool_call_id' => $call->id, 'content' => $result];
                }
            }

            if ($final === null) {
                $messages[] = ['role' => 'user', 'content' => 'Chega de consultas. Escreva agora o post final, somente com o JSON pedido.'];
                $final = $this->llm->chat($messages);
                $this->account($final, $usage, $model);
            }

            $parsed = $this->parse((string) $final->content);

            if ($parsed === null) {
                $messages[] = $final->message;
                $messages[] = ['role' => 'user', 'content' => 'Sua resposta não era um JSON válido. Responda somente com o JSON do formato pedido, sem texto antes ou depois.'];
                $retry = $this->llm->chat($messages);
                $this->account($retry, $usage, $model);
                $parsed = $this->parse((string) $retry->content);
            }
        } finally {
            $this->tools->close();
        }

        if ($parsed === null) {
            throw new RuntimeException('O modelo não devolveu um post em JSON válido.');
        }

        return [
            'title' => $parsed['title'],
            'excerpt' => $parsed['excerpt'],
            'body' => $parsed['body'],
            'guesses' => $parsed['guesses'],
            'context' => $log,
            'model' => $model,
            'prompt_tokens' => $usage['prompt'],
            'completion_tokens' => $usage['completion'],
        ];
    }

    public function parse(string $content): ?array
    {
        $text = trim($content);

        if (preg_match('/```(?:json)?\s*(.*?)```/s', $text, $match) === 1) {
            $text = trim($match[1]);
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        try {
            $data = json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body_markdown'] ?? $data['body'] ?? ''));

        if ($title === '' || $body === '') {
            return null;
        }

        return [
            'title' => mb_substr($title, 0, 160),
            'excerpt' => mb_substr(trim((string) ($data['excerpt'] ?? '')), 0, 300),
            'body' => $body,
            'guesses' => [
                'animals' => $this->animals((array) ($data['guesses']['animals'] ?? [])),
                'tens' => $this->tens((array) ($data['guesses']['tens'] ?? [])),
            ],
        ];
    }

    private function animals(array $values): array
    {
        return array_slice(array_values(array_unique(array_filter(
            array_map(fn (mixed $value): int => (int) $value, array_filter($values, 'is_numeric')),
            fn (int $number): bool => isset(Bicho::ANIMALS[$number]),
        ))), 0, 5);
    }

    private function tens(array $values): array
    {
        $tens = [];

        foreach ($values as $value) {
            if (is_numeric($value) && (int) $value >= 0 && (int) $value <= 99) {
                $tens[] = str_pad((string) (int) $value, 2, '0', STR_PAD_LEFT);
            }
        }

        return array_slice(array_values(array_unique($tens)), 0, 10);
    }

    private function account(LlmResponse $response, array &$usage, string &$model): void
    {
        $usage['prompt'] += $response->promptTokens;
        $usage['completion'] += $response->completionTokens;
        $model = $response->model;
    }

    private function systemPrompt(array $style): string
    {
        $guide = $style === [] ? '' : "\n\nDiretrizes editoriais recuperadas da base de conhecimento:\n".collect($style)->map(fn (array $chunk): string => '- '.$chunk['content'])->implode("\n");

        return <<<PROMPT
Você é o colunista de um blog brasileiro de estatística e curiosidades sobre os resultados históricos do jogo do bicho. Escreva sempre em português do Brasil.

Você tem ferramentas (via MCP) para consultar o histórico e uma base de conhecimento. Use-as: nunca invente resultados, datas ou contagens. Todo número citado precisa ter vindo de uma ferramenta.

Verdade acima de tudo: cada sorteio é aleatório e independente. Frequências e atrasos descrevem o passado e não preveem nada. Apresente os "palpites" apenas como curiosidade baseada em estatística, sem prometer ou sugerir ganho, sem citar casas de apostas, links ou valores. O conteúdo é para maiores de 18 anos e o jogo é proibido no Brasil; inclua esse aviso no fim do texto.

Quando terminar de consultar, responda SOMENTE com um JSON, sem texto fora dele, neste formato:
{"title": "...", "excerpt": "resumo de até 200 caracteres", "body_markdown": "texto em Markdown com subtítulos ##", "guesses": {"animals": [números dos grupos, no máximo 5], "tens": ["dezenas com dois dígitos, no máximo 10"]}}{$guide}
PROMPT;
    }

    private function userPrompt(CarbonInterface $date): string
    {
        $day = $date->copy()->locale('pt_BR')->translatedFormat('l, d \\d\\e F \\d\\e Y');

        return "Hoje é {$date->toDateString()} ({$day}). Escreva o post do dia. Antes de escrever, consulte: o resumo da base, os resultados mais recentes (ontem e anteontem), a frequência dos animais em 30 e em 365 dias, os mais atrasados, o perfil deste dia da semana, o histórico desta mesma data em anos anteriores e, na base de conhecimento, o significado popular dos animais que você pretende destacar.";
    }
}
