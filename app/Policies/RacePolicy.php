<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Race;
use App\Models\User;

class RacePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->driver;
    }

    public function view(User $user, Race $race): bool
    {
        return $user->driver?->groups()->where('group_id', $race->group_id)->exists();
    }

    public function create(User $user, Group $group): bool
    {
        return (new GroupPolicy)->update($user, $group);
    }

    public function update(User $user, Race $race): bool
    {
        return (new GroupPolicy)->update($user, $race->group);
    }

    /**
     * Session-managing authority (setup, lobby, qualifying, grid, control,
     * penalties, results — everything that mutates a race while it runs).
     */
    public function manage(User $user, Race $race): bool
    {
        return $this->update($user, $race);
    }

    public function delete(User $user, Race $race): bool
    {
        return $this->update($user, $race);
    }
}
