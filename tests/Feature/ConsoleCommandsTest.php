<?php

namespace Tests\Feature;

use App\Contracts\LlmClient;
use App\Contracts\ToolGateway;
use App\Models\Draw;
use App\Models\KnowledgeDocument;
use App\Models\Post;
use Database\Seeders\KnowledgeSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;
use Tests\Support\FakeLlm;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rag_train_reports_the_index_size(): void
    {
        $this->seed(KnowledgeSeeder::class);

        $this->artisan('rag:train')->expectsOutputToContain('4 documento(s)')->assertSuccessful();
    }

    public function test_draws_import_reads_a_file_and_reports_the_summary(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, "data;horario;premio;milhar\n01/11/2026;14:00;1;7345\n99/99/2026;14:00;1;1\n");

        $this->artisan('draws:import', ['file' => $path])
            ->expectsOutputToContain('1 sorteio(s) e 1 resultado(s)')
            ->expectsOutputToContain('Linha 3')
            ->assertSuccessful();

        $this->assertSame(1, Draw::count());
    }

    public function test_draws_import_fails_for_missing_files_and_unknown_formats(): void
    {
        $this->artisan('draws:import', ['file' => '/nao/existe.csv'])->expectsOutputToContain('não encontrado')->assertFailed();

        $path = tempnam(sys_get_temp_dir(), 'imp').'.pdf';
        file_put_contents($path, 'x');
        $this->artisan('draws:import', ['file' => $path])->expectsOutputToContain('Formato não suportado')->assertFailed();
    }

    public function test_draws_fetch_is_silent_when_unconfigured_and_stores_when_configured(): void
    {
        config(['bicho.results.scraper.url' => null]);
        $this->artisan('draws:fetch')->expectsOutputToContain('desligada')->assertSuccessful();

        config(['bicho.results.scraper.url' => 'https://resultados.test/{date}']);
        Http::fake(['resultados.test/*' => Http::sequence()
            ->push('<table><tr><td>14:00</td><td>1111</td><td>2222</td><td>3333</td><td>4444</td><td>5555</td></tr></table>')
            ->push('x', 500)]);

        $this->artisan('draws:fetch', ['date' => '2026-11-04'])->expectsOutputToContain('1 sorteio(s) de 2026-11-04')->assertSuccessful();
        $this->assertSame(1, Draw::count());

        $this->artisan('draws:fetch', ['date' => '2026-11-05'])->expectsOutputToContain('erro 500')->assertFailed();
    }

    public function test_posts_generate_creates_a_draft_and_then_reports_that_it_exists(): void
    {
        $this->app->instance(LlmClient::class, new FakeLlm([FakeLlm::text(FakeLlm::post('Pelo comando'))]));
        $this->app->instance(ToolGateway::class, new FakeGateway);

        $this->artisan('posts:generate', ['date' => '2026-11-04'])->expectsOutputToContain('Rascunho criado')->assertSuccessful();
        $this->artisan('posts:generate', ['date' => '2026-11-04'])->expectsOutputToContain('--force')->assertSuccessful();

        $this->assertSame(1, Post::count());
    }

    public function test_posts_generate_fails_cleanly_when_the_model_errors(): void
    {
        $this->app->instance(LlmClient::class, new FakeLlm([]));
        $this->app->instance(ToolGateway::class, new FakeGateway);

        $this->artisan('posts:generate', ['date' => '2026-11-04'])->assertFailed();
        $this->assertSame(0, Post::count());
        $this->assertSame(0, KnowledgeDocument::count());
    }

    public function test_the_daily_pipeline_is_scheduled_in_the_right_order(): void
    {
        $events = collect(app(Schedule::class)->events())->mapWithKeys(fn ($event) => [str_replace(["'", PHP_BINARY, base_path('artisan')], '', $event->command) => $event->expression]);

        $fetch = $events->first(fn ($expression, $command) => str_contains($command, 'draws:fetch'));
        $train = $events->first(fn ($expression, $command) => str_contains($command, 'rag:train'));
        $generate = $events->first(fn ($expression, $command) => str_contains($command, 'posts:generate'));

        $this->assertSame('30 6 * * *', $fetch);
        $this->assertSame('50 6 * * *', $train);
        $this->assertSame('0 7 * * *', $generate);
    }
}
