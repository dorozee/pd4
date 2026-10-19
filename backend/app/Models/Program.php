<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $fillable = [
        'title',
        'description',
        'duration_days',
        'price_kopecks',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price_kopecks' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function days(): HasMany
    {
        return $this->hasMany(ProgramDay::class)->orderBy('day_number');
    }

    public function priceRubles(): int
    {
        return (int) round($this->price_kopecks / 100);
    }
}
