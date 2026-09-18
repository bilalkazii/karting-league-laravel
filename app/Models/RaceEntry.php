<?php

namespace App\Models;

use App\Enums\QualifyingStatus;
use App\Enums\RaceDriverStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A driver's participation in a race. Composite primary key
 * (race_id, driver_id) — this model has no surrogate id.
 */
class RaceEntry extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'race_id';

    public function getKey(): mixed
    {
        return $this->getAttribute('race_id').':'.$this->getAttribute('driver_id');
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        $query->where('race_id', $this->getOriginal('race_id'))
            ->where('driver_id', $this->getOriginal('driver_id'));

        return $query;
    }

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
            'status' => RaceDriverStatus::class,
            'confirmed' => 'boolean',
            'ready' => 'boolean',
            'kart_number' => 'integer',
            'grid_position' => 'integer',
            'grid_penalty_seconds' => 'integer',
            'qualifying_time_ms' => 'integer',
            'qualifying_status' => QualifyingStatus::class,
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
