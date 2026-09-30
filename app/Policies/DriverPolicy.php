<?php

namespace App\Policies;

use App\Enums\DriverProfileVisibility;
use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->driver !== null;
    }

    public function view(User $user, Driver $driver): bool
    {
        $viewer = $user->driver;

        if ($viewer === null) {
            return false;
        }

        if ($viewer->id === $driver->id) {
            return true;
        }

        $visibility = $driver->profile?->user?->driverProfileVisibility()
            ?? DriverProfileVisibility::Public;

        if ($visibility === DriverProfileVisibility::Public) {
            return true;
        }

        return $driver->groups()
            ->whereIn('groups.id', $viewer->groups()->pluck('groups.id'))
            ->exists();
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->driver?->id === $driver->id;
    }
}
