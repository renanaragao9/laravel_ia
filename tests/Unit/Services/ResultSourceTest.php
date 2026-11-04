<?php

namespace Tests\Unit\Services;

use App\Contracts\ResultSource;
use App\Models\Draw;
use App\Services\Draws\FetchDrawsService;
use App\Services\Draws\HtmlTableResultSource;
use App\Services\Draws\LoteceResultSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ResultSourceTest extends TestCase
{
    use RefreshDatabase;

    private const HTML = '<html><body><table><tr><th>Horário</th><th>1º</th><th>2º</th><th>3º</th><th>4º</th><th>5º</th></tr>'
        .'<tr><td>14:00</td><td>7.345</td><td>0012</td><td>9997</td><td>1234</td><td>5678</td></tr>'
        .'<tr><td>18:00</td><td>1111</td><td>2222</td><td>3333</td><td>4444</td><td>5555</td></tr>'
        .'<tr><td>21:00</td><td>aguardando</td><td></td><td></td><td></td><td></td></tr></table></body></html>';

    protected function setUp(): void
    {
        parent::setUp();

        config(['bicho.results.driver' => 'html', 'bicho.results.scraper.url' => 'https://resultados.test/{Y}/{m}/{d}']);
    }

    public function test_parses_the_table_and_ignores_incomplete_rows(): void
    {
        $draws = app(HtmlTableResultSource::class)->parse(self::HTML, config('bicho.results.scraper'));

        $this->assertCount(2, $draws);
        $this->assertSame('14:00', $draws[0]['time']);
        $this->assertSame([1 => '7345', 2 => '0012', 3 => '9997', 4 => '1234', 5 => '5678'], $draws[0]['thousands']);
    }

    public function test_fetch_builds_the_url_from_the_date_and_stores_the_draws(): void
    {
        Http::fake(['resultados.test/*' => Http::response(self::HTML)]);

        $stored = app(FetchDrawsService::class)->run(Carbon::parse('2026-11-04'));

        $this->assertSame(2, $stored);
        $this->assertSame(2, Draw::count());
        $this->assertSame('scraper', Draw::first()->source);
        Http::assertSent(fn ($request) => $request->url() === 'https://resultados.test/2026/11/04');
    }

    public function test_fetching_twice_updates_instead_of_duplicating(): void
    {
        Http::fake(['resultados.test/*' => Http::response(self::HTML)]);

        app(FetchDrawsService::class)->run(Carbon::parse('2026-11-04'));
        app(FetchDrawsService::class)->run(Carbon::parse('2026-11-04'));

        $this->assertSame(2, Draw::count());
    }

    public function test_http_errors_and_missing_configuration_raise_clear_exceptions(): void
    {
        Http::fake(['resultados.test/*' => Http::response('erro', 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('erro 500');
        app(HtmlTableResultSource::class)->fetch(Carbon::parse('2026-11-04'));
    }

    public function test_unconfigured_source_reports_it_and_refuses_to_fetch(): void
    {
        config(['bicho.results.scraper.url' => null]);
        $source = app(HtmlTableResultSource::class);

        $this->assertFalse($source->isConfigured());
        $this->expectException(RuntimeException::class);
        $source->fetch(Carbon::parse('2026-11-04'));
    }

    public function test_the_container_resolves_the_source_from_the_configured_driver(): void
    {
        $this->assertInstanceOf(HtmlTableResultSource::class, app(ResultSource::class));

        config(['bicho.results.driver' => 'lotece']);
        $this->assertInstanceOf(LoteceResultSource::class, app(ResultSource::class));
    }
}
