<?php

namespace App\Models;

use App\Enums\ProgramPhase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramDay extends Model
{
    protected $fillable = [
        'program_id',
        'day_number',
        'title',
        'phase',
        'nutrition',
        'workout',
        'tips',
    ];

    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
            'phase' => ProgramPhase::class,
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
