<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'group_id',
        'race_id',
        'sender_id',
        'body',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'sender_id');
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
