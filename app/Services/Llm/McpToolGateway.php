<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Contracts\ToolGateway;
use Laravel\Mcp\Client;
use Throwable;

class McpToolGateway implements ToolGateway
{
    private ?Client $client = null;

    public function tools(): array
    {
        return $this->client()->tools()->map(fn ($tool): array => $this->toFunction($tool->name, $tool->description, $tool->inputSchema))->values()->all();
    }

    public function call(string $name, array $arguments): string
    {
        try {
            return $this->client()->callTool($name, $arguments)->text();
        } catch (Throwable $e) {
            return json_encode(['erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE) ?: '{"erro":"falha ao chamar a ferramenta"}';
        }
    }

    public function close(): void
    {
        $this->client?->disconnect();
        $this->client = null;
    }

    public function toFunction(string $name, ?string $description, array $schema): array
    {
        $schema['type'] = 'object';
        $schema['properties'] = (object) ($schema['properties'] ?? []);
        unset($schema['$schema']);

        return ['name' => $name, 'description' => (string) $description, 'parameters' => $schema];
    }

    private function client(): Client
    {
        if ($this->client === null) {
            $url = (string) config('bicho.mcp.url');

            $this->client = $url !== ''
                ? Client::web($url)->withToken((string) config('bicho.mcp.token'))
                : Client::local(PHP_BINARY, [base_path('artisan'), 'mcp:start', 'bicho']);
        }

        return $this->client;
    }
}
