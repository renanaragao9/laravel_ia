<?php

namespace Tests\Support;

use App\Models\Draw;
use App\Services\Draws\StoreDrawService;
use Illuminate\Support\Carbon;

trait MakesDraws
{
    protected function draw(string $date, array $thousands, string $time = '14:00', string $lottery = 'rio'): Draw
    {
        return app(StoreDrawService::class)->run(Carbon::parse($date), $lottery, $time, $thousands, 'test');
    }
}
