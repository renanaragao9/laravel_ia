<?php

namespace App\Models;

use App\Enums\KnowledgeKind;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends BaseModel
{
    protected $fillable = [
        'title',
        'kind',
        'source',
        'content',
        'trained_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => KnowledgeKind::class,
            'trained_at' => 'datetime',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class)->orderBy('position');
    }
}
