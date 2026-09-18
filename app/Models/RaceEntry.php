<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A driver's participation in a race. Composite primary key
 * (race_id, driver_id) — this model has no surrogate id.
 */
class RaceEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'race_id',
        'driver_id',
        'kart_number',
        'status',
        'confirmed',
        'ready',
        'grid_position',
        'grid_penalty_seconds',
        'qualifying_time_ms',
        'qualifying_status',
        'finish_position',
        'penalty_total_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\RaceDriverStatus::class,
            'confirmed' => 'boolean',
            'ready' => 'boolean',
            'kart_number' => 'integer',
            'grid_position' => 'integer',
            'grid_penalty_seconds' => 'integer',
            'qualifying_time_ms' => 'integer',
            'qualifying_status' => \App\Enums\QualifyingStatus::class,
            'finish_position' => 'integer',
            'penalty_total_seconds' => 'integer',
        ];
    }

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}