<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

abstract class BichoTool extends Tool
{
    protected const NOTE = 'Dados históricos descritivos. Cada sorteio é independente e aleatório: isto não é uma previsão.';

    protected function date(Request $request, string $key): CarbonInterface
    {
        $value = $request->get($key);

        return $value === null || $value === ''
            ? Carbon::now((string) config('bicho.timezone'))->startOfDay()
            : Carbon::createFromFormat('Y-m-d', (string) $value, (string) config('bicho.timezone'))->startOfDay();
    }

    protected function groupBy(Request $request): string
    {
        return match ($request->get('por')) {
            'dezena' => 'ten',
            'centena' => 'hundred',
            'milhar' => 'thousand',
            default => 'animal',
        };
    }

    protected function position(Request $request): ?int
    {
        $position = $request->get('premio');

        return $position === null || $position === '' ? null : (int) $position;
    }
}
