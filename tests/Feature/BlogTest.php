<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $attributes = []): Post
    {
        static $n = 0;
        $n++;

        return Post::create($attributes + [
            'post_date' => '2026-11-0'.(($n % 9) + 1),
            'title' => "Post {$n}",
            'slug' => "post-{$n}",
            'excerpt' => "Resumo {$n}",
            'body' => "## Subtítulo\n\nTexto do post {$n}.",
            'status' => PostStatus::Published,
            'published_at' => now()->subHour(),
            'guesses' => ['animals' => [9, 12], 'tens' => ['33', '05']],
        ]);
    }

    public function test_the_home_lists_only_published_posts_newest_first_with_the_legal_notice(): void
    {
        $old = $this->makePost(['title' => 'Antigo', 'slug' => 'antigo', 'post_date' => '2026-11-01']);
        $new = $this->makePost(['title' => 'Recente', 'slug' => 'recente', 'post_date' => '2026-11-03']);
        $this->makePost(['title' => 'Rascunho', 'slug' => 'rascunho', 'status' => PostStatus::Draft, 'published_at' => null]);
        $this->makePost(['title' => 'Agendado', 'slug' => 'agendado', 'published_at' => now()->addDay()]);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Recente', 'Antigo'])
            ->assertDontSee('Rascunho')
            ->assertDontSee('Agendado')
            ->assertSee('maiores de 18 anos')
            ->assertSee('Loteria Estadual do Ceará')
            ->assertSee(route('blog.show', 'recente'), false)
            ->assertSee('Cobra')
            ->assertSee('Elefante');

        $this->assertNotNull($old->id.$new->id);
    }

    public function test_the_home_has_a_friendly_empty_state_and_paginates(): void
    {
        $this->get('/')->assertOk()->assertSee('Ainda não há posts publicados');

        foreach (range(1, 12) as $i) {
            $this->makePost(['title' => "Paginado {$i}", 'slug' => "paginado-{$i}", 'post_date' => now()->subDays(20 - $i)->toDateString()]);
        }

        $this->get('/')->assertOk()->assertSee('Anteriores');
        $this->get('/?page=2')->assertOk()->assertSee('Mais recentes');
    }

    public function test_a_post_page_renders_markdown_guesses_and_the_disclaimer(): void
    {
        $post = $this->makePost(['title' => 'Cobra em alta', 'slug' => 'cobra-em-alta', 'body' => "## Números\n\nO grupo **Cobra** liderou.\n\n- item um\n- item dois"]);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertSee('<h2>Números</h2>', false)
            ->assertSee('<strong>Cobra</strong>', false)
            ->assertSee('<li>item um</li>', false)
            ->assertSee('Palpites do dia (só curiosidade)')
            ->assertSee('9 - Cobra')
            ->assertSee('33')
            ->assertSee('não aumenta a chance de acerto')
            ->assertSee('maiores de 18 anos');
    }

    public function test_html_and_unsafe_links_in_the_body_are_neutralized(): void
    {
        $post = $this->makePost(['slug' => 'xss', 'body' => "<script>alert(1)</script>\n\n<img src=x onerror=alert(2)>\n\n[clique](javascript:alert(3))"]);

        $html = $this->get(route('blog.show', $post->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_drafts_future_posts_and_unknown_slugs_return_404(): void
    {
        $draft = $this->makePost(['slug' => 'rascunho', 'status' => PostStatus::Draft, 'published_at' => null]);
        $future = $this->makePost(['slug' => 'futuro', 'published_at' => now()->addDay()]);

        $this->get(route('blog.show', $draft->slug))->assertNotFound();
        $this->get(route('blog.show', $future->slug))->assertNotFound();
        $this->get('/posts/nao-existe')->assertNotFound();
        $this->get('/posts/Com_Maiusculas')->assertNotFound();
    }

    public function test_a_post_without_guesses_hides_the_box(): void
    {
        $post = $this->makePost(['slug' => 'sem-palpites', 'guesses' => ['animals' => [], 'tens' => []]]);

        $this->get(route('blog.show', $post->slug))->assertOk()->assertDontSee('Palpites do dia');
    }
}
