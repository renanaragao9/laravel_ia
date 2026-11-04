<?php

namespace Tests\Unit\Services;

use App\Services\Draws\LoteceResultSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\Support\LoteceHtml;
use Tests\TestCase;

class LoteceResultSourceTest extends TestCase
{
    use LoteceHtml;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bicho.results.lotece' => [
            'url' => 'https://lotece.test/resultados/', 'lottery' => 'ce', 'user_agent' => 'teste-ia/1.0', 'timeout' => 10, 'delay_ms' => 0,
        ]]);
    }

    public function test_parses_extractions_normalizes_labels_and_orders_them_by_time_of_day(): void
    {
        $html = $this->loteceHtml([
            ['da Noite', '15/01/2026', $this->ten(4)],
            ['Tarde II', '15/01/2026', $this->ten(3)],
            ['Tarde', '15/01/2026', $this->ten(2)],
            ['da Manhã', '15/01/2026', $this->ten(1)],
        ]);

        $draws = app(LoteceResultSource::class)->parse($html, Carbon::parse('2026-01-15'));

        $this->assertSame(['Manhã', 'Tarde', 'Tarde II', 'Noite'], array_column($draws, 'time'));
        $this->assertSame(['ce', 'ce', 'ce', 'ce'], array_column($draws, 'lottery'));
        $this->assertCount(10, $draws[0]['thousands']);
        $this->assertSame($this->ten(1)[0], $draws[0]['thousands'][1]);
        $this->assertSame($this->ten(1)[9], $draws[0]['thousands'][10]);
    }

    public function test_keeps_leading_zeros_and_accepts_other_name_variations(): void
    {
        $html = $this->loteceHtml([['do Noite', '15/01/2026', ['0089', '0000', '0928', '1234', '5678']], ['Manhã', '15/01/2026', ['0001']]]);

        $draws = app(LoteceResultSource::class)->parse($html, Carbon::parse('2026-01-15'));

        $this->assertSame(['Manhã', 'Noite'], array_column($draws, 'time'));
        $this->assertSame('0089', $draws[1]['thousands'][1]);
        $this->assertSame('0000', $draws[1]['thousands'][2]);
    }

    public function test_ignores_blocks_from_other_days_pages_without_results_and_malformed_numbers(): void
    {
        $source = app(LoteceResultSource::class);

        $other = $this->loteceHtml([['Noite', '03/11/2026', $this->ten(1)], ['Manhã', '31/10/2026', $this->ten(2)]]);
        $this->assertSame([], $source->parse($other, Carbon::parse('2026-01-15')));

        $this->assertSame([], $source->parse($this->loteceHtml([]), Carbon::parse('2026-01-15')));
        $this->assertSame([], $source->parse('<html><body>RESULTADOS Confira os resultados</body></html>', Carbon::parse('2026-01-15')));

        $bad = $this->loteceHtml([['Noite', '15/01/2026', ['12', 'abcd', '12345', '']]]);
        $this->assertSame([], $source->parse($bad, Carbon::parse('2026-01-15')));

        $partial = $this->loteceHtml([['Noite', '15/01/2026', ['1111', 'xx', '3333']]]);
        $this->assertSame([1 => '1111', 3 => '3333'], $source->parse($partial, Carbon::parse('2026-01-15'))[0]['thousands']);
    }

    public function test_fetch_sends_the_date_in_the_site_format_with_the_user_agent(): void
    {
        Http::fake(['lotece.test/*' => Http::response($this->loteceHtml([['Manhã', '15/01/2026', $this->ten(1)]]))]);

        $draws = app(LoteceResultSource::class)->fetch(Carbon::parse('2026-01-15'));

        $this->assertCount(1, $draws);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://lotece.test/resultados/')
            && str_contains($request->url(), 'data=15%2F01%2F2026')
            && $request->hasHeader('User-Agent', 'teste-ia/1.0'));
    }

    public function test_retries_on_server_errors_and_then_succeeds(): void
    {
        Sleep::fake();
        Http::fake(['lotece.test/*' => Http::sequence()->push('erro', 503)->push('erro', 502)->push($this->loteceHtml([['Manhã', '15/01/2026', $this->ten(1)]]))]);

        $this->assertCount(1, app(LoteceResultSource::class)->fetch(Carbon::parse('2026-01-15')));
        Http::assertSentCount(3);
    }

    public function test_gives_up_with_a_clear_message_after_repeated_failures_and_does_not_retry_client_errors(): void
    {
        Sleep::fake();
        Http::fake(['lotece.test/*' => Http::response('erro', 500)]);

        try {
            app(LoteceResultSource::class)->fetch(Carbon::parse('2026-01-15'));
            $this->fail('Deveria falhar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('erro 500', $e->getMessage());
            $this->assertStringContainsString('2026-01-15', $e->getMessage());
        }

        Http::assertSentCount(3);

        Http::fake(['lotece.test/*' => Http::response('não achei', 404)]);
        $this->expectException(RuntimeException::class);
        app(LoteceResultSource::class)->fetch(Carbon::parse('2026-01-16'));
    }

    public function test_identity_and_configuration(): void
    {
        $source = app(LoteceResultSource::class);

        $this->assertSame('lotece', $source->name());
        $this->assertTrue($source->isConfigured());

        config(['bicho.results.lotece.url' => '']);
        $this->assertFalse($source->isConfigured());
    }
}
