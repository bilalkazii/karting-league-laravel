<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringPoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'season_id',
        'position',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'points' => 'integer',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}