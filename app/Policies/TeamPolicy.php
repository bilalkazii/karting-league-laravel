<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->driver !== null;
    }

    public function view(User $user, Team $team): bool
    {
        return $user->driver?->groups()->where('groups.id', $team->group_id)->exists();
    }

    /**
     * Creating a team requires group admin/organizer, mirroring seasons. Takes
     * the group directly because no Team row exists yet.
     */
    public function createInGroup(User $user, Group $group): bool
    {
        return (new GroupPolicy)->update($user, $group);
    }

    /**
     * Renaming a team or changing its membership is a group administration
     * action. It never touches drivers or race results, so history is intact.
     */
    public function update(User $user, Team $team): bool
    {
        return $this->createInGroup($user, $team->group);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }
}
