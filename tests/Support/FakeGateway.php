<?php

namespace Tests\Support;

use App\Contracts\ToolGateway;

class FakeGateway implements ToolGateway
{
    public array $calls = [];

    public bool $closed = false;

    public function __construct(private readonly array $results = []) {}

    public function tools(): array
    {
        return [
            ['name' => 'frequencia', 'description' => 'Frequência', 'parameters' => ['type' => 'object', 'properties' => (object) []]],
            ['name' => 'resumo_da_base', 'description' => 'Resumo', 'parameters' => ['type' => 'object', 'properties' => (object) []]],
        ];
    }

    public function call(string $name, array $arguments): string
    {
        $this->calls[] = ['name' => $name, 'arguments' => $arguments];

        return $this->results[$name] ?? '{"ok":true}';
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
