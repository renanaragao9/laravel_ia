<?php

namespace Tests\Unit\Services;

use App\Contracts\ResultSource;
use App\Services\Draws\BackfillDrawsService;
use App\Services\Draws\ExportDrawsXlsxService;
use App\Services\Draws\ImportDrawsService;
use App\Services\Draws\LoteceResultSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\LoteceHtml;
use Tests\TestCase;

class BackfillDrawsServiceTest extends TestCase
{
    use LoteceHtml;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-02-10 12:00:00');
        config(['bicho.results.lotece' => ['url' => 'https://lotece.test/resultados/', 'lottery' => 'ce', 'user_agent' => 't', 'timeout' => 5, 'delay_ms' => 0]]);
        File::deleteDirectory(storage_path('app/private/draw-cache'));
        File::deleteDirectory(storage_path('app/private/exports-test'));

        Http::fake(['lotece.test/*' => function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $date = (string) ($query['data'] ?? '');

            return match ($date) {
                '05/01/2026' => Http::response($this->loteceHtml([['Manhã', '05/01/2026', $this->ten(1)], ['da Noite', '05/01/2026', $this->ten(2)]])),
                '06/01/2026' => Http::response($this->loteceHtml([['Tarde', '06/01/2026', $this->ten(3)]])),
                '08/01/2026' => Http::response('erro', 500),
                default => Http::response($this->loteceHtml([])),
            };
        }]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/private/draw-cache'));
        File::deleteDirectory(storage_path('app/private/exports-test'));

        parent::tearDown();
    }

    private function backfill(string $from, string $to, bool $refresh = false, ?callable $progress = null): array
    {
        return app(BackfillDrawsService::class)->run(Carbon::parse($from), Carbon::parse($to), 0, $refresh, $progress);
    }

    public function test_walks_every_day_collects_draws_and_skips_days_without_results(): void
    {
        $summary = $this->backfill('2026-01-04', '2026-01-07');

        $this->assertSame(4, $summary['days']);
        $this->assertSame(4, $summary['fetched']);
        $this->assertSame(2, $summary['with_results']);
        $this->assertSame([], $summary['failed']);
        $this->assertSame(['2026-01-05', '2026-01-05', '2026-01-06'], array_column($summary['draws'], 'date'));
        $this->assertSame(['Manhã', 'Noite', 'Tarde'], array_column($summary['draws'], 'time'));
    }

    public function test_past_days_are_cached_so_a_second_run_does_not_hit_the_site_again(): void
    {
        $this->backfill('2026-01-05', '2026-01-06');
        Http::assertSentCount(2);

        $second = $this->backfill('2026-01-05', '2026-01-06');

        $this->assertSame(0, $second['fetched']);
        $this->assertSame(2, $second['cached']);
        $this->assertSame(2, $second['with_results']);
        Http::assertSentCount(2);

        $refreshed = $this->backfill('2026-01-05', '2026-01-06', true);
        $this->assertSame(2, $refreshed['fetched']);
        Http::assertSentCount(4);
    }

    public function test_today_is_never_cached_because_the_results_are_still_coming_in(): void
    {
        $this->backfill('2026-02-10', '2026-02-10');
        $this->backfill('2026-02-10', '2026-02-10');

        Http::assertSentCount(2);
    }

    public function test_a_failing_day_is_reported_and_does_not_stop_the_run_or_get_cached(): void
    {
        Sleep::fake();

        $first = $this->backfill('2026-01-07', '2026-01-09');

        $this->assertSame(3, $first['days']);
        $this->assertCount(1, $first['failed']);
        $this->assertStringContainsString('2026-01-08', $first['failed'][0]);
        $this->assertSame(2, $first['fetched']);

        $second = $this->backfill('2026-01-07', '2026-01-09');
        $this->assertCount(1, $second['failed']);
        $this->assertSame(2, $second['cached']);
    }

    public function test_reports_progress_for_every_day(): void
    {
        $seen = [];

        $this->backfill('2026-01-05', '2026-01-07', false, function ($day, ?array $draws) use (&$seen) {
            $seen[$day->toDateString()] = $draws === null ? null : count($draws);
        });

        $this->assertSame(['2026-01-05' => 2, '2026-01-06' => 1, '2026-01-07' => 0], $seen);
    }

    public function test_the_xlsx_export_round_trips_through_the_importer_with_ten_prizes_and_leading_zeros(): void
    {
        $summary = $this->backfill('2026-01-05', '2026-01-06');
        $path = storage_path('app/private/exports-test/historico.xlsx');

        $rows = app(ExportDrawsXlsxService::class)->run($summary['draws'], $path);

        $this->assertFileExists($path);
        $this->assertSame(30, $rows);

        $imported = app(ImportDrawsService::class)->run($path);

        $this->assertSame(['draws' => 3, 'results' => 30, 'skipped' => 0, 'errors' => []], $imported);
        $this->assertDatabaseHas('draws', ['drawn_on' => '2026-01-05', 'lottery' => 'ce', 'draw_time' => 'Manhã']);
        $this->assertDatabaseHas('draw_results', ['position' => 10, 'thousand' => $this->ten(1)[9]]);
    }

    public function test_leading_zeros_survive_the_xlsx_round_trip(): void
    {
        $path = storage_path('app/private/exports-test/zeros.xlsx');
        app(ExportDrawsXlsxService::class)->run([['date' => '2026-01-05', 'lottery' => 'ce', 'time' => 'Manhã', 'thousands' => [1 => '0089', 2 => '0000']]], $path);

        app(ImportDrawsService::class)->run($path);

        $this->assertDatabaseHas('draw_results', ['position' => 1, 'thousand' => '0089', 'ten' => '89']);
        $this->assertDatabaseHas('draw_results', ['position' => 2, 'thousand' => '0000', 'animal_number' => 25]);
    }

    public function test_the_default_source_for_the_service_is_lotece(): void
    {
        $this->assertInstanceOf(LoteceResultSource::class, app(ResultSource::class));
    }
}
