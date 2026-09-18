<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaceAward extends Model
{
    protected $table = 'race_awards';

    protected $fillable = [
        'race_id',
        'driver_of_race_id',
        'most_improved_id',
        'cleanest_driver_id',
    ];

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    public function driverOfRace(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_of_race_id');
    }

    public function mostImproved(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'most_improved_id');
    }

    public function cleanestDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'cleanest_driver_id');
    }
}