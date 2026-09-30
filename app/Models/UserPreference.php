<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single key/value preference for a user. Composite primary key
 * (user_id, key) — this model has no surrogate id.
 */
class UserPreference extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'user_id';

    public function getKey(): mixed
    {
        return $this->getAttribute('user_id').':'.$this->getAttribute('key');
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        $query->where('user_id', $this->getOriginal('user_id'))
            ->where('key', $this->getOriginal('key'));

        return $query;
    }

    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
