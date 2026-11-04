<?php

namespace Tests\Unit\Services;

use App\Services\Stats\DrawStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\MakesDraws;
use Tests\TestCase;

class DrawStatsServiceTest extends TestCase
{
    use MakesDraws;
    use RefreshDatabase;

    private DrawStatsService $stats;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');
        $this->stats = new DrawStatsService;
    }

    public function test_frequency_by_animal_counts_orders_and_computes_share(): void
    {
        $this->draw('2026-11-09', [1 => '0033', 2 => '0034', 3 => '0050']);
        $this->draw('2026-11-08', [1 => '0035', 2 => '0010']);

        $frequency = $this->stats->frequency(7);

        $this->assertSame('9 - Cobra', $frequency[0]['label']);
        $this->assertSame(3, $frequency[0]['count']);
        $this->assertSame(60.0, $frequency[0]['share']);
        $this->assertCount(3, $frequency);
    }

    public function test_frequency_can_be_filtered_by_prize_by_ten_and_by_window(): void
    {
        $this->draw('2026-11-09', [1 => '0033', 2 => '0033']);
        $this->draw('2026-10-01', [1 => '0033']);

        $this->assertSame(2, $this->stats->frequency(7)[0]['count']);
        $this->assertSame(1, $this->stats->frequency(7, 'animal', 1)[0]['count']);
        $this->assertSame(3, $this->stats->frequency(60)[0]['count']);
        $this->assertSame('33 (Cobra)', $this->stats->frequency(7, 'ten')[0]['label']);
        $this->assertSame([], $this->stats->frequency(7, 'animal', null, Carbon::parse('2026-09-01')));
    }

    public function test_frequency_only_looks_at_the_requested_lottery(): void
    {
        $this->draw('2026-11-09', [1 => '0033'], '14:00', 'rio');
        $this->draw('2026-11-09', [1 => '0010'], '14:00', 'federal');

        $this->assertSame('9 - Cobra', $this->stats->frequency(7, 'animal', null, null, 10, 'rio')[0]['label']);
        $this->assertCount(1, $this->stats->frequency(7, 'animal', null, null, 10, 'federal'));
    }

    public function test_soft_deleted_draws_are_ignored(): void
    {
        $draw = $this->draw('2026-11-09', [1 => '0033']);
        $draw->delete();

        $this->assertSame([], $this->stats->frequency(7));
        $this->assertSame(0, $this->stats->totals()['draws']);
    }

    public function test_overdue_puts_never_seen_first_then_the_longest_absences(): void
    {
        $this->draw('2026-11-09', [1 => '0001']);
        $this->draw('2026-11-01', [1 => '0005']);
        $this->draw('2026-11-05', [1 => '0009']);

        $overdue = $this->stats->overdue('animal', null, null, 25);

        $neverSeen = array_filter($overdue, fn (array $row) => $row['last_seen_on'] === null);
        $this->assertCount(22, $neverSeen);
        $this->assertNull($overdue[0]['days_since']);

        $seen = array_values(array_filter($overdue, fn (array $row) => $row['last_seen_on'] !== null));
        $this->assertSame(['2 - Águia', '3 - Burro', '1 - Avestruz'], array_column($seen, 'label'));
        $this->assertSame([9, 5, 1], array_column($seen, 'days_since'));
        $this->assertSame([2, 1, 0], array_column($seen, 'draws_since'));
    }

    public function test_overdue_by_ten_lists_the_hundred_numbers_universe(): void
    {
        $this->draw('2026-11-09', [1 => '0033']);

        $overdue = $this->stats->overdue('ten', null, null, 200);

        $this->assertCount(100, $overdue);
        $this->assertSame('33 (Cobra)', collect($overdue)->firstWhere('key', '33')['label']);
        $this->assertSame(1, collect($overdue)->firstWhere('key', '33')['days_since']);
    }

    public function test_weekday_profile_only_counts_the_requested_weekday(): void
    {
        $this->draw('2026-11-02', [1 => '0033']);
        $this->draw('2026-11-09', [1 => '0034']);
        $this->draw('2026-11-03', [1 => '0010']);

        $monday = $this->stats->weekdayProfile(1, 30);

        $this->assertSame('9 - Cobra', $monday[0]['label']);
        $this->assertSame(2, $monday[0]['count']);
        $this->assertCount(1, $monday);
        $this->assertSame('3 - Burro', $this->stats->weekdayProfile(2, 30)[0]['label']);
        $this->assertSame([], $this->stats->weekdayProfile(0, 30));
    }

    public function test_same_date_history_looks_at_the_same_day_in_previous_years(): void
    {
        $this->draw('2025-11-10', [1 => '0033']);
        $this->draw('2024-11-10', [1 => '0010']);
        $this->draw('2025-11-11', [1 => '0050']);

        $history = $this->stats->sameDateHistory(Carbon::parse('2026-11-10'));

        $this->assertSame(['2024-11-10', '2025-11-10'], array_column($history, 'date'));
        $this->assertSame('9 - Cobra', $history[1]['draws'][0]['results'][0]['animal']);
    }

    public function test_same_date_history_handles_leap_days(): void
    {
        $this->draw('2024-02-29', [1 => '0033']);

        $history = $this->stats->sameDateHistory(Carbon::parse('2028-02-29'), 5);

        $this->assertSame(['2024-02-29'], array_column($history, 'date'));
    }

    public function test_day_results_groups_every_draw_of_the_day(): void
    {
        $this->draw('2026-11-09', [1 => '0033', 2 => '0034'], '14:00');
        $this->draw('2026-11-09', [1 => '0010'], '18:00');
        $this->draw('2026-11-08', [1 => '0050'], '14:00');

        $day = $this->stats->dayResults(Carbon::parse('2026-11-09'));

        $this->assertCount(1, $day);
        $this->assertSame(['14:00', '18:00'], array_column($day[0]['draws'], 'time'));
        $this->assertSame('0033', $day[0]['draws'][0]['results'][0]['thousand']);
    }

    public function test_totals(): void
    {
        $this->assertSame(['draws' => 0, 'results' => 0, 'first_date' => null, 'last_date' => null], $this->stats->totals());

        $this->draw('2026-11-01', [1 => '0001', 2 => '0002']);
        $this->draw('2026-11-09', [1 => '0003']);

        $this->assertSame(['draws' => 2, 'results' => 3, 'first_date' => '2026-11-01', 'last_date' => '2026-11-09'], $this->stats->totals());
    }
}
