<?php

namespace App\Providers;

use App\Models\Driver;
use App\Models\Group;
use App\Policies\DriverPolicy;
use App\Policies\GroupPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Group::class => GroupPolicy::class,
        Driver::class => DriverPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
