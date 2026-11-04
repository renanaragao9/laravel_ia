<?php

namespace Tests\Unit;

use App\Services\Draws\ImportDrawsService;
use App\Services\Stats\DrawStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\MakesDraws;
use Tests\TestCase;

class PrizeRangeTest extends TestCase
{
    use MakesDraws;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');
    }

    public function test_stores_up_to_ten_prizes_and_ignores_beyond(): void
    {
        $draw = $this->draw('2026-11-09', array_combine(range(1, 12), array_map(fn (int $i) => str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 12))));

        $this->assertSame(range(1, 10), $draw->results()->pluck('position')->all());
    }

    public function test_import_accepts_prizes_one_to_ten_in_both_formats_and_rejects_eleven(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'p').'.csv';
        file_put_contents($file, "data;horario;premio;milhar\n01/11/2026;A;10;1234\n01/11/2026;A;11;1234\n");
        $summary = app(ImportDrawsService::class)->run($file);

        $this->assertSame(1, $summary['results']);
        $this->assertStringContainsString('de 1 a 10', $summary['errors'][0]);

        $wide = tempnam(sys_get_temp_dir(), 'p').'.csv';
        file_put_contents($wide, "data;horario;p1;p2;p3;p4;p5;p6;p7;p8;p9;p10\n02/11/2026;B;1;2;3;4;5;6;7;8;9;10\n");
        $summary = app(ImportDrawsService::class)->run($wide);

        $this->assertSame(10, $summary['results']);
    }

    public function test_analyses_only_look_at_the_first_five_prizes_by_default(): void
    {
        $this->draw('2026-11-09', [1 => '0033', 2 => '0034', 3 => '0035', 4 => '0036', 5 => '0001', 6 => '0050', 7 => '0051', 8 => '0052', 9 => '0053', 10 => '0054']);
        $stats = new DrawStatsService;

        $this->assertSame(5, array_sum(array_column($stats->frequency(7), 'count')));
        $this->assertSame(['9 - Cobra'], [$stats->frequency(7)[0]['label']]);
        $this->assertSame(1, array_sum(array_column($stats->frequency(7, 'animal', 7), 'count')));
        $this->assertSame('13 - Galo', $stats->frequency(7, 'animal', 7)[0]['label']);
    }

    public function test_the_default_window_can_be_changed_by_configuration(): void
    {
        $this->draw('2026-11-09', [1 => '0033', 2 => '0034', 6 => '0050', 7 => '0051']);

        config(['bicho.analysis.max_position' => 10]);
        $this->assertSame(4, array_sum(array_column((new DrawStatsService)->frequency(7), 'count')));

        config(['bicho.analysis.max_position' => 5]);
        $this->assertSame(2, array_sum(array_column((new DrawStatsService)->frequency(7), 'count')));
    }
}
