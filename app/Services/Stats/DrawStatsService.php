<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Models\Draw;
use App\Models\DrawResult;
use App\Support\Bicho\Bicho;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DrawStatsService
{
    public function frequency(int $days, string $by = 'animal', ?int $position = null, ?CarbonInterface $until = null, int $limit = 10, ?string $lottery = null): array
    {
        $until = $this->day($until);
        $query = $this->window($until->copy()->subDays($days - 1), $until, $position, $lottery);

        $total = (clone $query)->count();
        $column = $this->column($by);

        $rows = $query
            ->selectRaw("{$column} as item, count(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->orderBy('item')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row): array => [
            'key' => (string) $row->item,
            'label' => $this->label($by, (string) $row->item),
            'count' => (int) $row->total,
            'share' => $total === 0 ? 0.0 : round(((int) $row->total / $total) * 100, 2),
        ])->all();
    }

    public function overdue(string $by = 'animal', ?int $position = null, ?CarbonInterface $until = null, int $limit = 10, ?string $lottery = null): array
    {
        $until = $this->day($until);
        $column = $this->column($by);

        $query = $this->base($position, $lottery)->where('draws.drawn_on', '<=', $until->toDateString());

        $last = (clone $query)
            ->selectRaw("{$column} as item, max(draws.drawn_on) as last_seen")
            ->groupBy($column)
            ->pluck('last_seen', 'item');

        $drawDates = Draw::query()
            ->where('drawn_on', '<=', $until->toDateString())
            ->when($lottery, fn ($q) => $q->where('lottery', $lottery))
            ->pluck('drawn_on')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());

        $universe = $by === 'ten'
            ? array_map(fn (int $value): string => str_pad((string) $value, 2, '0', STR_PAD_LEFT), range(0, 99))
            : array_map('strval', array_keys(Bicho::ANIMALS));

        return collect($universe)->map(function (string $key) use ($last, $until, $drawDates, $by): array {
            $seen = $last[$key] ?? $last[(int) $key] ?? null;
            $seenDate = $seen === null ? null : Carbon::parse($seen)->toDateString();

            return [
                'key' => $key,
                'label' => $this->label($by, $key),
                'last_seen_on' => $seenDate,
                'days_since' => $seenDate === null ? null : (int) Carbon::parse($seenDate)->diffInDays($until),
                'draws_since' => $seenDate === null ? $drawDates->count() : $drawDates->filter(fn (string $date): bool => $date > $seenDate)->count(),
            ];
        })
            ->sortBy([
                fn (array $a, array $b): int => ($b['days_since'] ?? PHP_INT_MAX) <=> ($a['days_since'] ?? PHP_INT_MAX),
                fn (array $a, array $b): int => (int) $a['key'] <=> (int) $b['key'],
            ])
            ->take($limit)
            ->values()
            ->all();
    }

    public function weekdayProfile(int $weekday, int $days = 365, string $by = 'animal', ?int $position = null, ?CarbonInterface $until = null, int $limit = 10, ?string $lottery = null): array
    {
        $until = $this->day($until);
        $query = $this->window($until->copy()->subDays($days - 1), $until, $position, $lottery)
            ->whereRaw($this->weekdayExpression().' = ?', [$weekday]);

        $total = (clone $query)->count();
        $column = $this->column($by);

        return $query
            ->selectRaw("{$column} as item, count(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->orderBy('item')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'key' => (string) $row->item,
                'label' => $this->label($by, (string) $row->item),
                'count' => (int) $row->total,
                'share' => $total === 0 ? 0.0 : round(((int) $row->total / $total) * 100, 2),
            ])
            ->all();
    }

    public function periodSummary(CarbonInterface $from, CarbonInterface $until, ?int $position = null, int $limit = 10, ?string $lottery = null): array
    {
        $window = fn () => $this->window($from, $until, $position, $lottery);
        $top = function (string $by, int $minimum = 1) use ($window, $limit): array {
            $column = $this->column($by);

            return $window()
                ->selectRaw("{$column} as item, count(*) as total")
                ->groupBy($column)
                ->havingRaw('count(*) >= ?', [$minimum])
                ->orderByDesc('total')
                ->orderBy('item')
                ->limit($limit)
                ->get()
                ->map(fn ($row): array => ['key' => (string) $row->item, 'label' => $this->label($by, (string) $row->item), 'count' => (int) $row->total])
                ->all();
        };

        $firstPrize = $this->window($from, $until, 1, $lottery)
            ->orderByDesc('draws.drawn_on')
            ->orderByDesc('draws.draw_time')
            ->limit($limit)
            ->get(['draws.drawn_on', 'draws.draw_time', 'draw_results.thousand'])
            ->map(fn ($row): array => ['date' => Carbon::parse($row->drawn_on)->toDateString(), 'time' => $row->draw_time, 'thousand' => (string) $row->thousand])
            ->all();

        return [
            'from' => $from->toDateString(),
            'until' => $until->toDateString(),
            'draws' => (int) $this->window($from, $until, $position, $lottery)->distinct()->count('draws.id'),
            'results' => (int) $window()->count(),
            'top_animals' => $top('animal'),
            'top_tens' => $top('ten'),
            'top_hundreds' => $top('hundred', 2),
            'repeated_thousands' => $top('thousand', 2),
            'latest_first_prize_thousands' => $firstPrize,
        ];
    }

    public function sameDateHistory(CarbonInterface $date, int $years = 10): array
    {
        $dates = [];

        for ($i = 1; $i <= $years; $i++) {
            $candidate = Carbon::create($date->year - $i, $date->month, 1)->startOfDay();

            if ($date->day <= $candidate->daysInMonth) {
                $dates[] = $candidate->day($date->day)->toDateString();
            }
        }

        return $this->grouped(Draw::query()->whereIn('drawn_on', $dates));
    }

    public function dayResults(CarbonInterface $date): array
    {
        return $this->grouped(Draw::query()->whereDate('drawn_on', $date->toDateString()));
    }

    public function totals(): array
    {
        return [
            'draws' => Draw::query()->count(),
            'results' => DrawResult::query()->count(),
            'first_date' => Draw::query()->min('drawn_on') ? Carbon::parse(Draw::query()->min('drawn_on'))->toDateString() : null,
            'last_date' => Draw::query()->max('drawn_on') ? Carbon::parse(Draw::query()->max('drawn_on'))->toDateString() : null,
        ];
    }

    private function grouped(Builder $draws): array
    {
        return $draws
            ->with('results')
            ->orderBy('drawn_on')
            ->orderBy('draw_time')
            ->get()
            ->groupBy(fn (Draw $draw): string => $draw->drawn_on->toDateString())
            ->map(fn ($group, string $date): array => [
                'date' => $date,
                'draws' => $group->map(fn (Draw $draw): array => [
                    'lottery' => $draw->lottery,
                    'time' => $draw->draw_time,
                    'results' => $draw->results->map(fn (DrawResult $result): array => [
                        'position' => $result->position,
                        'thousand' => $result->thousand,
                        'ten' => $result->ten,
                        'animal' => $result->animal_number.' - '.Bicho::animalName($result->animal_number),
                    ])->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function base(?int $position, ?string $lottery): \Illuminate\Database\Query\Builder
    {
        return DB::table('draw_results')
            ->join('draws', 'draws.id', '=', 'draw_results.draw_id')
            ->whereNull('draw_results.deleted_at')
            ->whereNull('draws.deleted_at')
            ->when($position, fn ($q) => $q->where('draw_results.position', $position), fn ($q) => $q->where('draw_results.position', '<=', (int) config('bicho.analysis.max_position')))
            ->when($lottery, fn ($q) => $q->where('draws.lottery', $lottery));
    }

    private function window(CarbonInterface $from, CarbonInterface $to, ?int $position, ?string $lottery): \Illuminate\Database\Query\Builder
    {
        return $this->base($position, $lottery)
            ->where('draws.drawn_on', '>=', $from->toDateString())
            ->where('draws.drawn_on', '<=', $to->toDateString());
    }

    private function weekdayExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => '(DAYOFWEEK(draws.drawn_on) - 1)',
            'pgsql' => 'EXTRACT(DOW FROM draws.drawn_on)',
            default => "CAST(strftime('%w', draws.drawn_on) AS INTEGER)",
        };
    }

    private function column(string $by): string
    {
        return match ($by) {
            'ten' => 'draw_results.ten',
            'hundred' => 'draw_results.hundred',
            'thousand' => 'draw_results.thousand',
            default => 'draw_results.animal_number',
        };
    }

    private function label(string $by, string $key): string
    {
        if ($by !== 'animal') {
            return $key.' ('.Bicho::animalName(Bicho::animalNumber(substr($key, -2))).')';
        }

        return (int) $key.' - '.Bicho::animalName((int) $key);
    }

    private function day(?CarbonInterface $date): Carbon
    {
        return Carbon::instance($date ?? now())->startOfDay();
    }
}
