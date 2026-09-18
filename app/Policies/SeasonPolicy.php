<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Season;
use App\Models\User;

class SeasonPolicy
{
    public function view(User $user, Season $season): bool
    {
        return $user->driver?->groups()->where('group_id', $season->group_id)->exists();
    }

    public function create(User $user, Group $group): bool
    {
        return (new GroupPolicy)->update($user, $group);
    }

    public function update(User $user, Season $season): bool
    {
        return (new GroupPolicy)->update($user, $season->group);
    }

    public function delete(User $user, Season $season): bool
    {
        return $this->update($user, $season);
    }
}
