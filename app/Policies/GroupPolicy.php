<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Group $group): bool
    {
        return $user->driver?->groups()->where('group_id', $group->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Group $group): bool
    {
        $pivot = $user->driver?->groups()->where('group_id', $group->id)->first()?->pivot;

        return $pivot && in_array($pivot->role, ['admin', 'organizer']);
    }

    public function manageMembers(User $user, Group $group): bool
    {
        return $this->update($user, $group);
    }
}