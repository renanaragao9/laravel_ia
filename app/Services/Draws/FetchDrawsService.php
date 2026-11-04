<?php

declare(strict_types=1);

namespace App\Services\Draws;

use App\Contracts\ResultSource;
use Carbon\CarbonInterface;

class FetchDrawsService
{
    public function __construct(
        private readonly ResultSource $resultSource,
        private readonly StoreDrawService $storeDrawService,
    ) {}

    public function run(CarbonInterface $date): int
    {
        $stored = 0;

        foreach ($this->resultSource->fetch($date) as $draw) {
            $this->storeDrawService->run($date, $draw['lottery'], $draw['time'], $draw['thousands'], $this->resultSource->name());
            $stored++;
        }

        return $stored;
    }
}
