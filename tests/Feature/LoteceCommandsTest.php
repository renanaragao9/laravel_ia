<?php

namespace Tests\Feature;

use App\Models\Draw;
use App\Models\DrawResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\LoteceHtml;
use Tests\TestCase;

class LoteceCommandsTest extends TestCase
{
    use LoteceHtml;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-02-10 12:00:00');
        config([
            'bicho.timezone' => 'America/Sao_Paulo',
            'bicho.results.lotece' => ['url' => 'https://lotece.test/resultados/', 'lottery' => 'ce', 'user_agent' => 't', 'timeout' => 5, 'delay_ms' => 0],
        ]);
        File::deleteDirectory(storage_path('app/private/draw-cache'));

        Http::fake(['lotece.test/*' => function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $date = (string) ($query['data'] ?? '');

            if ($date === '15/03/2026') {
                return Http::response('erro', 500);
            }

            return in_array($date, ['05/01/2026', '06/01/2026', '09/02/2026', '10/02/2026'], true)
                ? Http::response($this->loteceHtml([['Manhã', $date, $this->ten((int) substr($date, 0, 2))], ['Noite', $date, $this->ten((int) substr($date, 0, 2) + 50)]]))
                : Http::response($this->loteceHtml([]));
        }]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/private/draw-cache'));
        File::deleteDirectory(storage_path('app/private/exports-test'));

        parent::tearDown();
    }

    public function test_backfill_builds_the_xlsx_for_the_period_without_touching_the_database(): void
    {
        $path = storage_path('app/private/exports-test/ano.xlsx');

        $this->artisan('draws:backfill', ['from' => '2026-01-04', 'to' => '2026-01-07', '--xlsx' => $path, '--delay' => 0])
            ->expectsOutputToContain('2 dia(s) com resultado, 4 extração(ões), 40 linha(s)')
            ->expectsOutputToContain('Consultados: 4')
            ->assertSuccessful();

        $this->assertFileExists($path);
        $this->assertSame(0, Draw::count());
    }

    public function test_backfill_can_import_the_generated_file(): void
    {
        $path = storage_path('app/private/exports-test/ano.xlsx');

        $this->artisan('draws:backfill', ['from' => '2026-01-05', 'to' => '2026-01-06', '--xlsx' => $path, '--delay' => 0, '--import' => true])
            ->expectsOutputToContain('Importado: 4 sorteio(s) e 40 resultado(s)')
            ->assertSuccessful();

        $this->assertSame(4, Draw::count());
        $this->assertSame(40, DrawResult::count());
        $this->assertSame(['ce'], Draw::pluck('lottery')->unique()->values()->all());
    }

    public function test_backfill_defaults_to_the_start_of_the_year_until_yesterday(): void
    {
        $this->artisan('draws:backfill', ['--xlsx' => storage_path('app/private/exports-test/padrao.xlsx'), '--delay' => 0])
            ->expectsOutputToContain('2026-01-01 a 2026-02-09 (40 dias)')
            ->assertSuccessful();

        Http::assertSentCount(40);
    }

    public function test_backfill_rejects_an_inverted_range_and_reports_failures_with_a_failing_status(): void
    {
        $this->artisan('draws:backfill', ['from' => '2026-02-01', 'to' => '2026-01-01'])->expectsOutputToContain('depois da final')->assertFailed();

        Sleep::fake();

        $this->artisan('draws:backfill', ['from' => '2026-03-15', 'to' => '2026-03-15', '--xlsx' => storage_path('app/private/exports-test/falha.xlsx'), '--delay' => 0])
            ->expectsOutputToContain('2026-03-15')
            ->assertFailed();
    }

    public function test_fetch_without_a_date_collects_yesterday_and_today(): void
    {
        $this->artisan('draws:fetch')
            ->expectsOutputToContain('2 sorteio(s) de 2026-02-09')
            ->expectsOutputToContain('2 sorteio(s) de 2026-02-10')
            ->assertSuccessful();

        $this->assertSame(4, Draw::count());
        $this->assertSame('lotece', Draw::first()->source);
    }

    public function test_fetch_with_a_date_collects_only_that_day_and_is_idempotent(): void
    {
        $this->artisan('draws:fetch', ['date' => '2026-01-05'])->assertSuccessful();
        $this->artisan('draws:fetch', ['date' => '2026-01-05'])->assertSuccessful();

        $this->assertSame(2, Draw::count());
        $this->assertSame(20, DrawResult::count());
    }
}
