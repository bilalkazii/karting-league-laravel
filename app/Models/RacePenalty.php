<?php

namespace App\Models;

use App\Enums\RacePenaltyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RacePenalty extends Model
{
    use HasFactory;

    protected $fillable = [
        'race_id',
        'driver_id',
        'seconds',
        'reason',
        'issued_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'seconds' => 'integer',
            'status' => RacePenaltyStatus::class,
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

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'issued_by');
    }
}
