<?php

namespace App\Jobs;

use App\Services\Posts\GeneratePostService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class GeneratePostJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly string $date,
        public readonly bool $force = false,
    ) {}

    public function handle(GeneratePostService $generatePostService): void
    {
        $generatePostService->run(Carbon::parse($this->date, (string) config('bicho.timezone')), $this->force);
    }
}
