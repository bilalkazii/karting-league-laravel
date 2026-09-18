<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatLastRead extends Model
{
    protected $fillable = [
        'driver_id',
        'group_id',
        'race_id',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }
}
