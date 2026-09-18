<?php

namespace App\Models;

use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'nickname',
        'racing_number',
        'avatar_color',
        'avatar_text_color',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'racing_number' => 'integer',
            'rating' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role', 'availability', 'joined_at');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('joined_at');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RaceEntry::class);
    }

    public function races(): BelongsToMany
    {
        return $this->belongsToMany(Race::class, 'race_entries')
            ->withPivot([
                'kart_number', 'status', 'confirmed', 'ready', 'grid_position',
                'grid_penalty_seconds', 'qualifying_time_ms', 'qualifying_status',
                'finish_position', 'penalty_total_seconds', 'notes',
            ]);
    }

    public function organizedRaces(): HasMany
    {
        return $this->hasMany(Race::class, 'organizer_id');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(RacePenalty::class);
    }

    public function issuedPenalties(): HasMany
    {
        return $this->hasMany(RacePenalty::class, 'issued_by');
    }

    public function raceEvents(): HasMany
    {
        return $this->hasMany(RaceEvent::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?? $this->profile->full_name;
    }
}