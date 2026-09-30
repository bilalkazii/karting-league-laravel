<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Group;
use App\Models\Race;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChampionshipStandingsTest extends TestCase
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

    private function completedRace(Group $group, int $round, Driver $winner, Driver $runnerUp): Race
    {
        $race = Race::factory()->create([
            'group_id' => $group->id,
            'name' => "Round {$round}",
            'status' => 'completed',
        ]);

        $entry = fn (Driver $driver, int $position, int $quali) => [
            'driver_id' => $driver->id,
            'kart_number' => 0,
            'status' => 'finished',
            'confirmed' => true,
            'ready' => true,
            'grid_position' => $position,
            'grid_penalty_seconds' => 0,
            'qualifying_time_ms' => $quali,
            'qualifying_status' => 'completed',
            'finish_position' => $position,
            'penalty_total_seconds' => 0,
            'notes' => '',
        ];

        $race->entries()->create($entry($winner, 1, 30000));
        $race->entries()->create($entry($runnerUp, 2, 31000));

        return $race;
    }

    public function test_championship_page_renders_standings_with_points_and_order(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $driverA = Driver::factory()->create(['nickname' => 'AA']);
        $driverB = Driver::factory()->create(['nickname' => 'BB']);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Standings Cup',
            'status' => 'active',
        ]);

        $round1 = $this->completedRace($group, 1, $driverA, $driverB);
        $round2 = $this->completedRace($group, 2, $driverA, $driverB);
        $season->races()->attach($round1->id, ['round_number' => 1]);
        $season->races()->attach($round2->id, ['round_number' => 2]);

        // Driver A: 25 + 1 (pole) twice = 52. Driver B: 18 twice = 36.
        $this->actingAs($user)
            ->get(route('championship.show', $season))
            ->assertOk()
            ->assertSee('Standings')
            ->assertSee('AA')
            ->assertSee('BB')
            ->assertSee('52')
            ->assertSee('36')
            ->assertSeeInOrder(['AA', 'BB']);
    }

    public function test_championship_page_shows_empty_state_without_completed_races(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Fresh Season',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('championship.show', $season))
            ->assertOk()
            ->assertSee('No standings yet');
    }
}
