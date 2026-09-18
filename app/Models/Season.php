<?php

namespace App\Models;

use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'name',
        'status',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\SeasonStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function races(): BelongsToMany
    {
        return $this->belongsToMany(Race::class, 'season_races')
            ->withPivot('round_number');
    }

    public function scoring(): HasOne
    {
        return $this->hasOne(SeasonScoring::class);
    }

    public function scoringPoints(): HasMany
    {
        return $this->hasMany(ScoringPoint::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(SeasonRecord::class);
    }
}