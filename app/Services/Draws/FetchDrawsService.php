<?php

declare(strict_types=1);

namespace App\Services\Draws;

use App\Contracts\ResultSource;
use Carbon\CarbonInterface;

class FetchDrawsService
{
    public function __construct(
        private readonly ResultSource $source,
        private readonly StoreDrawService $store,
    ) {}

    public function run(CarbonInterface $date): int
    {
        $stored = 0;

        foreach ($this->source->fetch($date) as $draw) {
            $this->store->run($date, $draw['lottery'], $draw['time'], $draw['thousands'], 'scraper');
            $stored++;
        }

        return $stored;
    }
}
