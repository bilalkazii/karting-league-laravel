<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualifyingAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'race_id',
        'driver_id',
        'attempt_number',
        'time_ms',
        'status',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'time_ms' => 'integer',
            'status' => \App\Enums\QualifyingStatus::class,
            'recorded_at' => 'datetime',
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