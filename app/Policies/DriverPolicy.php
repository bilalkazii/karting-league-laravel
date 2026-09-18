<?php

namespace App\Policies;

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
        return $user->driver !== null;
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->driver?->id === $driver->id;
    }
}
