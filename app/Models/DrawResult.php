<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawResult extends BaseModel
{
    protected $fillable = [
        'draw_id',
        'position',
        'thousand',
        'hundred',
        'ten',
        'animal_number',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'animal_number' => 'integer',
        ];
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'animal_number', 'number');
    }
}
