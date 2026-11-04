<?php

declare(strict_types=1);

namespace App\Contracts;

use Carbon\CarbonInterface;

interface ResultSource
{
    public function isConfigured(): bool;

    public function fetch(CarbonInterface $date): array;
}
