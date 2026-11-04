<?php

namespace Tests\Feature;

use App\Contracts\LlmClient;
use App\Contracts\ToolGateway;
use App\Enums\KnowledgeKind;
use App\Enums\PostStatus;
use App\Exceptions\PostAlreadyExistsException;
use App\Jobs\GeneratePostJob;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeTerm;
use App\Models\Post;
use App\Services\Posts\GeneratePostService;
use App\Services\Posts\PublishPostService;
use App\Support\Blog\Disclaimer;
use Database\Seeders\KnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\FakeGateway;
use Tests\Support\FakeLlm;
use Tests\TestCase;

class PostGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function fake(string ...$contents): FakeLlm
    {
        $llm = new FakeLlm(array_map(fn (string $content) => FakeLlm::text($content, 100, 20), $contents));
        $this->app->instance(LlmClient::class, $llm);
        $this->app->instance(ToolGateway::class, new FakeGateway);

        return $llm;
    }

    public function test_generates_a_draft_with_metadata_slug_disclaimer_and_index_training(): void
    {
        $this->seed(KnowledgeSeeder::class);
        $this->assertSame(0, KnowledgeTerm::count());
        $this->fake(FakeLlm::post('Palpites de quarta', "## Hoje\n\nTexto sem aviso."));

        $post = app(GeneratePostService::class)->run(Carbon::parse('2026-11-04'));

        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertNull($post->published_at);
        $this->assertSame('2026-11-04-palpites-de-quarta', $post->slug);
        $this->assertSame('Resumo curto', $post->excerpt);
        $this->assertSame(['animals' => [9, 12], 'tens' => ['33', '45']], $post->guesses);
        $this->assertSame('fake/model', $post->model);
        $this->assertSame(100, $post->prompt_tokens);
        $this->assertStringContainsString('maiores de 18 anos', $post->body);
        $this->assertStringContainsString('proibido no Brasil', $post->body);
        $this->assertGreaterThan(0, KnowledgeTerm::count());
    }

    public function test_the_disclaimer_is_not_duplicated_when_the_model_already_wrote_it(): void
    {
        $body = 'Conteúdo para maiores de 18 anos. O jogo é proibido no Brasil.';

        $this->assertSame($body, Disclaimer::ensureIn($body));
        $this->assertSame(1, substr_count(Disclaimer::ensureIn('texto'), '18 anos'));
    }

    public function test_refuses_to_generate_twice_for_the_same_date_unless_forced(): void
    {
        $this->fake(FakeLlm::post('Primeiro'), FakeLlm::post('Segundo'));
        $first = app(GeneratePostService::class)->run(Carbon::parse('2026-11-04'));

        try {
            app(GeneratePostService::class)->run(Carbon::parse('2026-11-04'));
            $this->fail('Deveria recusar.');
        } catch (PostAlreadyExistsException $e) {
            $this->assertStringContainsString('--force', $e->getMessage());
        }

        $second = app(GeneratePostService::class)->run(Carbon::parse('2026-11-04'), true);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Segundo', $second->title);
        $this->assertSame(1, Post::count());
    }

    public function test_a_published_post_is_never_overwritten(): void
    {
        $this->fake(FakeLlm::post('Publicado'));
        $post = app(GeneratePostService::class)->run(Carbon::parse('2026-11-04'));
        app(PublishPostService::class)->publish($post);

        $this->expectException(PostAlreadyExistsException::class);
        $this->expectExceptionMessage('despublique');
        app(GeneratePostService::class)->run(Carbon::parse('2026-11-04'), true);
    }

    public function test_slugs_stay_unique_even_with_deleted_posts(): void
    {
        $this->fake(FakeLlm::post('Mesmo título'), FakeLlm::post('Mesmo título'));
        $one = app(GeneratePostService::class)->run(Carbon::parse('2026-11-04'));
        $one->update(['post_date' => '2026-11-03']);
        $two = app(GeneratePostService::class)->run(Carbon::parse('2026-11-04'));

        $this->assertNotSame($one->slug, $two->slug);
        $this->assertStringEndsWith('-2', $two->slug);
    }

    public function test_publishing_feeds_the_knowledge_base_and_unpublishing_removes_it(): void
    {
        $this->fake(FakeLlm::post('Post sobre cobras'));
        $post = app(GeneratePostService::class)->run(Carbon::parse('2026-11-04'));
        $service = app(PublishPostService::class);

        $published = $service->publish($post);

        $this->assertSame(PostStatus::Published, $published->status);
        $this->assertNotNull($published->published_at);
        $document = KnowledgeDocument::where('source', 'post:'.$post->id)->firstOrFail();
        $this->assertSame(KnowledgeKind::Post, $document->kind);
        $this->assertStringContainsString('Post de 04/11/2026', $document->title);

        $service->publish($published);
        $this->assertSame(1, KnowledgeDocument::where('source', 'post:'.$post->id)->count());

        $service->unpublish($published);
        $this->assertSame(PostStatus::Draft, $post->fresh()->status);
        $this->assertNull($post->fresh()->published_at);
        $this->assertSame(0, KnowledgeDocument::where('source', 'post:'.$post->id)->count());
    }

    public function test_only_published_posts_not_in_the_future_are_visible(): void
    {
        $base = ['post_date' => '2026-11-01', 'title' => 'T', 'body' => 'b'];
        Post::create($base + ['slug' => 'rascunho', 'status' => PostStatus::Draft]);
        Post::create($base + ['slug' => 'publicado', 'status' => PostStatus::Published, 'published_at' => now()->subHour()]);
        Post::create($base + ['slug' => 'agendado', 'status' => PostStatus::Published, 'published_at' => now()->addDay()]);

        $this->assertSame(['publicado'], Post::published()->pluck('slug')->all());
    }

    public function test_the_job_generates_for_the_given_date(): void
    {
        $this->fake(FakeLlm::post('Pelo job'));

        (new GeneratePostJob('2026-11-04'))->handle(app(GeneratePostService::class));

        $this->assertSame('Pelo job', Post::firstOrFail()->title);
        $this->assertSame('2026-11-04', Post::firstOrFail()->post_date->toDateString());
    }
}
