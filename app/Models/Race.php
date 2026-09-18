<?php

namespace App\Models;

use App\Enums\RaceFormat;
use App\Enums\RaceStatus;
use Database\Factories\RaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Race extends Model
{
    /** @use HasFactory<RaceFactory> */
    use HasFactory;

    protected $fillable = [
        'group_id',
        'name',
        'venue_name',
        'date',
        'start_time',
        'format',
        'status',
        'organizer_id',
        'qualifying_lap_count',
        'rules',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'string',
            'format' => RaceFormat::class,
            'status' => RaceStatus::class,
            'qualifying_lap_count' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'organizer_id');
    }

    /**
     * Drivers taking part in the race, via race_entries.
     */
    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'race_entries')
            ->withPivot([
                'kart_number', 'status', 'confirmed', 'ready', 'grid_position',
                'grid_penalty_seconds', 'qualifying_time_ms', 'qualifying_status',
                'finish_position', 'penalty_total_seconds', 'notes',
            ]);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RaceEntry::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(RacePenalty::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RaceEvent::class);
    }

    public function qualifyingAttempts(): HasMany
    {
        return $this->hasMany(QualifyingAttempt::class);
    }

    public function raceAwards(): HasOne
    {
        return $this->hasOne(RaceAward::class);
    }

    public function seasons(): BelongsToMany
    {
        return $this->belongsToMany(Season::class, 'season_races')
            ->withPivot('round_number');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
