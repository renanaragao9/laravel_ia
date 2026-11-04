<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeChunk extends BaseModel
{
    protected $fillable = [
        'knowledge_document_id',
        'position',
        'content',
        'vector',
        'norm',
    ];

    protected function casts(): array
    {
        return [
            'vector' => 'array',
            'norm' => 'float',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }
}
