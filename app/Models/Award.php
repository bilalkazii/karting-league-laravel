<?php

namespace App\Models;

use App\Enums\AwardType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    protected $fillable = [
        'season_id',
        'type',
        'label',
        'description',
        'driver_id',
        'team_id',
        'race_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'type' => AwardType::class,
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }
}