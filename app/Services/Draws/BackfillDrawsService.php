<?php

declare(strict_types=1);

namespace App\Services\Draws;

use App\Contracts\ResultSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BackfillDrawsService
{
    public function __construct(private readonly ResultSource $resultSource) {}

    public function run(CarbonInterface $from, CarbonInterface $to, int $delayMs = 1000, bool $refresh = false, ?callable $progress = null): array
    {
        $summary = ['days' => 0, 'fetched' => 0, 'cached' => 0, 'with_results' => 0, 'failed' => [], 'draws' => []];
        $directory = storage_path('app/private/draw-cache/'.$this->resultSource->name());
        File::ensureDirectoryExists($directory);

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day = $day->copy()->addDay()) {
            $file = $directory.'/'.$day->toDateString().'.json';
            $summary['days']++;

            if (! $refresh && $day->lt(now()->startOfDay()) && is_file($file)) {
                $draws = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
                $summary['cached']++;
            } else {
                try {
                    $draws = $this->resultSource->fetch($day);
                } catch (RuntimeException $e) {
                    $summary['failed'][] = $day->toDateString().': '.$e->getMessage();
                    Log::warning('Backfill de resultados falhou.', ['date' => $day->toDateString(), 'error' => $e->getMessage()]);
                    $progress && $progress($day, null);

                    continue;
                }

                $summary['fetched']++;

                if ($day->lt(now()->startOfDay())) {
                    file_put_contents($file, json_encode($draws, JSON_UNESCAPED_UNICODE));
                }

                $delayMs > 0 && usleep($delayMs * 1000);
            }

            if ($draws !== []) {
                $summary['with_results']++;
            }

            foreach ($draws as $draw) {
                $summary['draws'][] = ['date' => $day->toDateString()] + $draw;
            }

            $progress && $progress($day, $draws);
        }

        return $summary;
    }
}
