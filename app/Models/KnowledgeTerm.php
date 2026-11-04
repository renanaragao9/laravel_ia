<?php

namespace App\Models;

class KnowledgeTerm extends BaseModel
{
    protected $fillable = [
        'term',
        'document_frequency',
        'idf',
    ];

    protected function casts(): array
    {
        return [
            'idf' => 'float',
        ];
    }
}
