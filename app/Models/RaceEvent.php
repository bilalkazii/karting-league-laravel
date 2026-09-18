<?php

namespace App\Models;

use App\Enums\RaceEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaceEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'race_id',
        'driver_id',
        'type',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'type' => RaceEventType::class,
            'occurred_at' => 'datetime',
            'payload' => 'array',
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