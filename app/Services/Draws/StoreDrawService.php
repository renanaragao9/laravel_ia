<?php

declare(strict_types=1);

namespace App\Services\Draws;

use App\Models\Draw;
use App\Models\DrawResult;
use App\Support\Bicho\Bicho;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class StoreDrawService
{
    public function run(CarbonInterface $date, string $lottery, string $time, array $thousands, ?string $source = null): Draw
    {
        return DB::transaction(function () use ($date, $lottery, $time, $thousands, $source): Draw {
            $draw = Draw::withTrashed()->firstOrNew([
                'drawn_on' => $date->toDateString(),
                'lottery' => $lottery,
                'draw_time' => $time,
            ]);

            $draw->source = $source ?? $draw->source;
            $draw->save();

            if ($draw->trashed()) {
                $draw->restore();
            }

            $now = now();
            $rows = [];

            foreach ($thousands as $position => $value) {
                if ($position < 1 || $position > 5) {
                    continue;
                }

                $thousand = Bicho::normalizeThousand($value);
                $ten = Bicho::ten($thousand);

                $rows[] = [
                    'draw_id' => $draw->id,
                    'position' => $position,
                    'thousand' => $thousand,
                    'hundred' => Bicho::hundred($thousand),
                    'ten' => $ten,
                    'animal_number' => Bicho::animalNumber($ten),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }

            if ($rows !== []) {
                DrawResult::withTrashed()->upsert($rows, ['draw_id', 'position'], ['thousand', 'hundred', 'ten', 'animal_number', 'updated_at', 'deleted_at']);
            }

            return $draw;
        });
    }
}
