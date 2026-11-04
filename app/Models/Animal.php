<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Animal extends BaseModel
{
    protected $fillable = [
        'number',
        'name',
        'tens',
    ];

    protected function casts(): array
    {
        return [
            'tens' => 'array',
            'number' => 'integer',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(DrawResult::class, 'animal_number', 'number');
    }
}
