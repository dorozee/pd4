<?php

namespace App\Models;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'height',
        'age',
        'weight',
        'gender',
        'activity_level',
    ];

    protected function casts(): array
    {
        return [
            'height' => 'integer',
            'age' => 'integer',
            'weight' => 'decimal:1',
            'gender' => Gender::class,
            'activity_level' => ActivityLevel::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
