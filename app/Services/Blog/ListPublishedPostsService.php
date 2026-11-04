<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPublishedPostsService
{
    public function run(int $perPage = 10): LengthAwarePaginator
    {
        return Post::query()->published()->orderByDesc('post_date')->orderByDesc('id')->paginate($perPage);
    }
}
