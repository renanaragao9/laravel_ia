<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Draw extends BaseModel
{
    protected $fillable = [
        'drawn_on',
        'lottery',
        'draw_time',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'drawn_on' => 'date:Y-m-d',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(DrawResult::class)->orderBy('position');
    }
}
