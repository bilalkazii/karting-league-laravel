<?php

namespace App\Models;

use App\Enums\ScoringMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonScoring extends Model
{
    protected $table = 'season_scoring';
    protected $fillable = [
        'season_id',
        'mode',
        'pole_position_points',
        'fastest_lap_points',
        'participation_points',
        'dnf_points',
        'dns_points',
        'penalty_adjustment_enabled',
    ];

    protected function casts(): array
    {
        return [
            'mode' => ScoringMode::class,
            'pole_position_points' => 'integer',
            'fastest_lap_points' => 'integer',
            'participation_points' => 'integer',
            'dnf_points' => 'integer',
            'dns_points' => 'integer',
            'penalty_adjustment_enabled' => 'boolean',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}