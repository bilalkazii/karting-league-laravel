<?php

namespace Tests\Feature;

use Database\Seeders\ChampionshipContentSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_cannot_be_seeded_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => ChampionshipContentSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('groups', 0);
        $this->assertDatabaseCount('seasons', 0);
    }

    public function test_database_seeder_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('groups', 0);
    }

    public function test_demo_data_still_seeds_outside_production(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'demo@karting.app']);
    }
}
