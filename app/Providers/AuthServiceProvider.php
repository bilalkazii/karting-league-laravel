<?php

namespace App\Providers;

use App\Models\Driver;
use App\Models\Group;
use App\Models\Race;
use App\Models\Season;
use App\Models\Team;
use App\Policies\DriverPolicy;
use App\Policies\GroupPolicy;
use App\Policies\RacePolicy;
use App\Policies\SeasonPolicy;
use App\Policies\TeamPolicy;
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
        Season::class => SeasonPolicy::class,
        Race::class => RacePolicy::class,
        Team::class => TeamPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
