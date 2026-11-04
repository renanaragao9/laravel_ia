<?php

declare(strict_types=1);

namespace App\Contracts;

interface ToolGateway
{
    public function tools(): array;

    public function call(string $name, array $arguments): string;

    public function close(): void;
}
