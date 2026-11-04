<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Models\Post;

class ShowPostService
{
    public function run(string $slug): Post
    {
        return Post::query()->published()->where('slug', $slug)->firstOrFail();
    }
}
