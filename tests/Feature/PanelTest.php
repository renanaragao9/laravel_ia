<?php

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Enums\PostStatus;
use App\Filament\Resources\Draws\Pages\ListDraws;
use App\Filament\Resources\Knowledge\Pages\CreateKnowledgeDocument;
use App\Filament\Resources\Knowledge\Pages\EditKnowledgeDocument;
use App\Filament\Resources\Knowledge\Pages\ListKnowledge;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Filament\Widgets\OverviewStats;
use App\Jobs\GeneratePostJob;
use App\Models\Draw;
use App\Models\KnowledgeDocument;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\AnimalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\MakesDraws;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use MakesDraws;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_master' => true]));
        $this->seed(AnimalSeeder::class);
    }

    private function newPost(array $attributes = []): Post
    {
        static $n = 0;
        $n++;

        return Post::create($attributes + [
            'post_date' => '2026-11-0'.(($n % 9) + 1), 'title' => "Post {$n}", 'slug' => "post-{$n}", 'excerpt' => 'Resumo',
            'body' => "## Corpo\n\nTexto.", 'status' => PostStatus::Draft, 'guesses' => ['animals' => [9], 'tens' => ['33']],
            'context' => ['tool_calls' => [['name' => 'frequencia', 'arguments' => ['dias' => 30], 'result' => '{}']], 'style_sources' => ['Guia de estilo']],
            'model' => 'm/x', 'prompt_tokens' => 10, 'completion_tokens' => 5,
        ]);
    }

    public function test_every_panel_page_renders(): void
    {
        $draw = $this->draw('2026-11-04', [1 => '7345', 2 => '0033']);
        $post = $this->newPost();
        $doc = KnowledgeDocument::create(['title' => 'Doc', 'kind' => KnowledgeKind::Rules, 'content' => 'texto']);

        foreach ([
            '/admin', '/admin/sorteios', "/admin/sorteios/{$draw->id}",
            '/admin/posts', "/admin/posts/{$post->id}", "/admin/posts/{$post->id}/edit",
            '/admin/conhecimento', '/admin/conhecimento/create', "/admin/conhecimento/{$doc->id}/edit",
            '/admin/users',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get("/admin/sorteios/{$draw->id}")->assertSee('04/11/2026 14:00')->assertSee('7345')->assertSee('Elefante');
        $this->get("/admin/posts/{$post->id}")->assertSee('frequencia')->assertSee('9 - Cobra')->assertSee('Guia de estilo');
    }

    public function test_draws_cannot_be_created_or_edited_in_the_panel(): void
    {
        $draw = $this->draw('2026-11-04', [1 => '7345']);

        $this->get('/admin/sorteios/create')->assertNotFound();
        $this->get("/admin/sorteios/{$draw->id}/edit")->assertNotFound();
    }

    public function test_the_import_action_reads_a_csv_and_removes_the_uploaded_file(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('historico.csv', "data;horario;premio;milhar\n01/11/2026;14:00;1;7345\n01/11/2026;14:00;2;0033\n");

        Livewire::test(ListDraws::class)
            ->callAction('import', ['file' => $file])
            ->assertHasNoActionErrors()
            ->assertNotified('Importação concluída');

        $this->assertSame(2, Draw::firstOrFail()->results()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_import_action_warns_about_bad_rows_and_rejects_bad_headers(): void
    {
        Storage::fake('local');

        Livewire::test(ListDraws::class)
            ->callAction('import', ['file' => UploadedFile::fake()->createWithContent('a.csv', "data;horario;premio;milhar\n01/11/2026;14:00;1;7345\n31/02/2026;14:00;1;1\n")])
            ->assertNotified('Importação concluída com avisos');

        Livewire::test(ListDraws::class)
            ->callAction('import', ['file' => UploadedFile::fake()->createWithContent('b.csv', "foo;bar\n1;2\n")])
            ->assertNotified('Importação concluída com avisos');
    }

    public function test_the_fetch_action_only_exists_when_the_scraper_is_configured(): void
    {
        config(['bicho.results.scraper.url' => null]);
        Livewire::test(ListDraws::class)->assertActionHidden('fetch');

        config(['bicho.results.scraper.url' => 'https://resultados.test/{date}']);
        Http::fake(['resultados.test/*' => Http::response('<table><tr><td>14:00</td><td>1111</td><td>2222</td><td>3333</td><td>4444</td><td>5555</td></tr></table>')]);

        Livewire::test(ListDraws::class)
            ->assertActionVisible('fetch')
            ->callAction('fetch', ['date' => '2026-11-04'])
            ->assertNotified('1 sorteio(s) gravado(s)');

        $this->assertSame(1, Draw::count());
    }

    public function test_the_fetch_action_reports_source_errors(): void
    {
        config(['bicho.results.scraper.url' => 'https://resultados.test/{date}']);
        Http::fake(['resultados.test/*' => Http::response('x', 500)]);

        Livewire::test(ListDraws::class)->callAction('fetch', ['date' => '2026-11-04'])->assertNotified('Falha na coleta');
    }

    public function test_the_generate_action_queues_the_job_or_asks_for_the_api_key(): void
    {
        Queue::fake();
        config(['bicho.openrouter.api_key' => '']);

        Livewire::test(ListPosts::class)->callAction('generate', ['date' => '2026-11-04'])->assertNotified('OPENROUTER_API_KEY não configurada');
        Queue::assertNothingPushed();

        config(['bicho.openrouter.api_key' => 'sk-or-teste']);

        Livewire::test(ListPosts::class)->callAction('generate', ['date' => '2026-11-04', 'force' => true])->assertNotified('Geração iniciada');
        Queue::assertPushed(GeneratePostJob::class, fn (GeneratePostJob $job) => $job->date === '2026-11-04' && $job->force === true);
    }

    public function test_publish_and_unpublish_actions_change_the_status_and_the_blog_visibility(): void
    {
        $post = $this->newPost(['slug' => 'meu-post']);

        $this->get(route('blog.show', 'meu-post'))->assertNotFound();

        Livewire::test(ViewPost::class, ['record' => $post->getRouteKey()])
            ->assertActionVisible('publish')
            ->assertActionHidden('unpublish')
            ->callAction('publish')
            ->assertNotified('Post publicado');

        $this->assertSame(PostStatus::Published, $post->fresh()->status);
        $this->get(route('blog.show', 'meu-post'))->assertOk();

        Livewire::test(ViewPost::class, ['record' => $post->getRouteKey()])
            ->assertActionHidden('publish')
            ->callAction('unpublish');

        $this->assertSame(PostStatus::Draft, $post->fresh()->status);
        $this->get(route('blog.show', 'meu-post'))->assertNotFound();
    }

    public function test_the_post_can_be_edited_and_validates_the_slug(): void
    {
        $post = $this->newPost(['slug' => 'um']);
        $other = $this->newPost(['slug' => 'outro']);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['title' => 'Título revisado', 'slug' => 'revisado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Título revisado', $post->fresh()->title);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->fillForm(['slug' => 'outro'])->call('save')->assertHasFormErrors(['slug' => 'unique']);
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->fillForm(['slug' => 'Com Espaço'])->call('save')->assertHasFormErrors(['slug']);
        $this->assertNotNull($other);
    }

    public function test_knowledge_documents_can_be_created_edited_and_the_index_trained(): void
    {
        Livewire::test(CreateKnowledgeDocument::class)
            ->fillForm(['title' => 'Novo documento', 'kind' => 'dreams', 'content' => "Sonhar com gato.\n\nSonhar com cão."])
            ->call('create')
            ->assertHasNoFormErrors();

        $doc = KnowledgeDocument::firstOrFail();
        $this->assertNull($doc->trained_at);

        Livewire::test(ListKnowledge::class)->callAction('train')->assertNotified('Índice treinado');
        $this->assertNotNull($doc->fresh()->trained_at);

        Livewire::test(EditKnowledgeDocument::class, ['record' => $doc->getRouteKey()])
            ->fillForm(['title' => 'Renomeado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renomeado', $doc->fresh()->title);
    }

    public function test_knowledge_requires_title_kind_and_content(): void
    {
        Livewire::test(CreateKnowledgeDocument::class)
            ->fillForm(['title' => '', 'kind' => null, 'content' => ''])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required', 'kind' => 'required', 'content' => 'required']);
    }

    public function test_the_overview_widget_summarizes_the_work(): void
    {
        $this->draw('2026-11-04', [1 => '7345']);
        $this->newPost();

        Livewire::test(OverviewStats::class)
            ->assertSee('Sorteios na base')
            ->assertSee('Último: 04/11/2026')
            ->assertSee('Posts aguardando revisão')
            ->assertSee('rascunhos gerados pela IA');
    }

    public function test_guests_cannot_reach_the_panel_and_the_blog_is_public(): void
    {
        auth()->logout();

        foreach (['/admin', '/admin/posts', '/admin/sorteios', '/admin/conhecimento'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $this->get('/')->assertOk();
    }
}
