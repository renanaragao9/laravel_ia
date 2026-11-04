<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Enums\KnowledgeKind;
use App\Enums\PostStatus;
use App\Models\KnowledgeDocument;
use App\Models\Post;

class PublishPostService
{
    public function publish(Post $post): Post
    {
        $post->update(['status' => PostStatus::Published, 'published_at' => $post->published_at ?? now()]);

        KnowledgeDocument::query()->updateOrCreate(
            ['source' => $this->source($post)],
            [
                'title' => 'Post de '.$post->post_date->format('d/m/Y').': '.$post->title,
                'kind' => KnowledgeKind::Post,
                'content' => $post->title."\n\n".$post->excerpt."\n\n".$post->body,
            ],
        );

        return $post->refresh();
    }

    public function unpublish(Post $post): Post
    {
        $post->update(['status' => PostStatus::Draft, 'published_at' => null]);

        KnowledgeDocument::query()->where('source', $this->source($post))->delete();

        return $post->refresh();
    }

    private function source(Post $post): string
    {
        return 'post:'.$post->id;
    }
}
