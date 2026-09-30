<?php

namespace App\Models;

use App\Enums\InviteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invite extends Model
{
    protected $fillable = [
        'group_id',
        'invited_by',
        'email',
        'token_hash',
        'role',
        'status',
        'accepted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InviteStatus::class,
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === InviteStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === InviteStatus::Accepted;
    }

    public function isRevoked(): bool
    {
        return $this->status === InviteStatus::Revoked;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAcceptable(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', InviteStatus::Pending->value);
    }

    public function scopeForGroup(Builder $query, Group|int $group): Builder
    {
        return $query->where('group_id', $group instanceof Group ? $group->getKey() : $group);
    }
}
