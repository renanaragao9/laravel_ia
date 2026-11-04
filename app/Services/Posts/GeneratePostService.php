<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Enums\PostStatus;
use App\Exceptions\PostAlreadyExistsException;
use App\Models\KnowledgeTerm;
use App\Models\Post;
use App\Services\Rag\TrainKnowledgeService;
use App\Support\Blog\Disclaimer;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class GeneratePostService
{
    public function __construct(
        private readonly PostAgentService $postAgentService,
        private readonly TrainKnowledgeService $trainKnowledgeService,
    ) {}

    public function run(CarbonInterface $date, bool $force = false): Post
    {
        $existing = Post::query()->whereDate('post_date', $date->toDateString())->first();

        if ($existing !== null && (! $force || $existing->status === PostStatus::Published)) {
            throw new PostAlreadyExistsException($existing->status === PostStatus::Published
                ? 'Já existe um post publicado para esta data; despublique-o antes de gerar outro.'
                : 'Já existe um post para esta data (use --force para gerar de novo).');
        }

        if (! KnowledgeTerm::query()->exists()) {
            $this->trainKnowledgeService->run();
        }

        $draft = $this->postAgentService->run($date);

        $attributes = [
            'post_date' => $date->toDateString(),
            'title' => $draft['title'],
            'excerpt' => $draft['excerpt'],
            'body' => Disclaimer::ensureIn($draft['body']),
            'status' => PostStatus::Draft,
            'published_at' => null,
            'guesses' => $draft['guesses'],
            'context' => $draft['context'],
            'model' => $draft['model'],
            'prompt_tokens' => $draft['prompt_tokens'],
            'completion_tokens' => $draft['completion_tokens'],
        ];

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing->refresh();
        }

        return Post::create($attributes + ['slug' => $this->slug($date, $draft['title'])]);
    }

    private function slug(CarbonInterface $date, string $title): string
    {
        $base = $date->format('Y-m-d').'-'.Str::slug(Str::limit($title, 60, ''));
        $slug = $base;
        $suffix = 2;

        while (Post::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
