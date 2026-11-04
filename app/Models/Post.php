<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;

class Post extends BaseModel
{
    protected $fillable = [
        'post_date',
        'title',
        'slug',
        'excerpt',
        'body',
        'status',
        'published_at',
        'guesses',
        'context',
        'model',
        'prompt_tokens',
        'completion_tokens',
    ];

    protected function casts(): array
    {
        return [
            'post_date' => 'date:Y-m-d',
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'guesses' => 'array',
            'context' => 'array',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published->value)->where('published_at', '<=', now());
    }
}
