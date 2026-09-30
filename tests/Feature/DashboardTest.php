<?php

namespace Tests\Feature;

use App\Enums\RaceEventType;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Race;
use App\Models\RaceEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function demoActor(): User
    {
        $this->seed();

        return User::where('email', 'demo@karting.app')->firstOrFail();
    }

    private function kartingCrew(): Group
    {
        return Group::where('name', 'Karting Crew')->firstOrFail();
    }

    public function test_dashboard_shows_real_race_activity(): void
    {
        $user = $this->demoActor();
        $race = Race::factory()->create([
            'group_id' => $this->kartingCrew()->id,
            'name' => 'Real Activity GP',
        ]);

        RaceEvent::create([
            'race_id' => $race->id,
            'driver_id' => null,
            'type' => RaceEventType::RaceStart->value,
            'occurred_at' => now(),
            'payload' => [],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Race started')
            ->assertSee('Real Activity GP');
    }

    public function test_dashboard_does_not_show_fabricated_activity(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Umar joined the group')
            ->assertDontSee('Saturday GP was created')
            ->assertDontSee('Ahmad set a new personal best')
            ->assertSee('No race activity yet');
    }

    public function test_dashboard_hides_activity_from_foreign_groups(): void
    {
        $user = $this->demoActor();

        $stranger = Driver::factory()->create();
        $foreignGroup = Group::factory()->create([
            'name' => 'Foreign Crew Z',
            'created_by' => $stranger->id,
        ]);
        $race = Race::factory()->create([
            'group_id' => $foreignGroup->id,
            'name' => 'Foreign Activity GP',
        ]);

        RaceEvent::create([
            'race_id' => $race->id,
            'driver_id' => $stranger->id,
            'type' => RaceEventType::RaceFinish->value,
            'occurred_at' => now(),
            'payload' => [],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Foreign Activity GP');
    }

    public function test_dashboard_renders_for_user_without_driver(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No race activity yet');
    }
}
