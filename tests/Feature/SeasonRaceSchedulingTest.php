<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Group;
use App\Models\Race;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeasonRaceSchedulingTest extends TestCase
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

    private function makeSeason(Group $group, string $name = 'Scheduling Season'): Season
    {
        return Season::create([
            'group_id' => $group->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function makeRace(Group $group, string $name = 'Scheduling GP', string $status = 'draft'): Race
    {
        return Race::factory()->create([
            'group_id' => $group->id,
            'name' => $name,
            'status' => $status,
        ]);
    }

    public function test_organizer_can_schedule_same_group_race(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);
        $race = $this->makeRace($group, 'Schedule Me');

        $this->actingAs($user)
            ->post(route('seasons.races.store', $season), ['race_id' => $race->id])
            ->assertRedirect(route('championship.show', $season))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('season_races', [
            'season_id' => $season->id,
            'race_id' => $race->id,
            'round_number' => 1,
        ]);
    }

    public function test_round_number_is_correct_when_rounds_exist(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);

        $first = $this->makeRace($group, 'Round One');
        $season->races()->attach($first->id, ['round_number' => 1]);

        $next = $this->makeRace($group, 'Round Two');

        $this->actingAs($user)
            ->post(route('seasons.races.store', $season), ['race_id' => $next->id])
            ->assertRedirect(route('championship.show', $season));

        $this->assertDatabaseHas('season_races', [
            'season_id' => $season->id,
            'race_id' => $next->id,
            'round_number' => 2,
        ]);
    }

    public function test_gaps_in_round_numbering_are_filled_with_the_lowest_available(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);

        $roundOne = $this->makeRace($group, 'Round One');
        $roundThree = $this->makeRace($group, 'Round Three');
        $season->races()->attach($roundOne->id, ['round_number' => 1]);
        $season->races()->attach($roundThree->id, ['round_number' => 3]);

        $newcomer = $this->makeRace($group, 'Round Two');

        $this->actingAs($user)
            ->post(route('seasons.races.store', $season), ['race_id' => $newcomer->id])
            ->assertRedirect(route('championship.show', $season));

        $this->assertDatabaseHas('season_races', [
            'season_id' => $season->id,
            'race_id' => $newcomer->id,
            'round_number' => 2,
        ]);
    }

    public function test_race_from_another_group_is_rejected(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);

        $stranger = Driver::factory()->create();
        $foreignGroup = Group::factory()->create(['created_by' => $stranger->id]);
        $foreignRace = $this->makeRace($foreignGroup, 'Foreign Race');

        $this->actingAs($user)
            ->post(route('seasons.races.store', $season), ['race_id' => $foreignRace->id])
            ->assertSessionHasErrors('race_id');

        $this->assertDatabaseMissing('season_races', [
            'season_id' => $season->id,
            'race_id' => $foreignRace->id,
        ]);
    }

    public function test_unauthorized_member_cannot_schedule_race(): void
    {
        $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);
        $race = $this->makeRace($group, 'Member Target');

        $member = User::where('email', 'drv3@karting.app')->firstOrFail();

        $this->actingAs($member)
            ->post(route('seasons.races.store', $season), ['race_id' => $race->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('season_races', [
            'season_id' => $season->id,
            'race_id' => $race->id,
        ]);
    }

    public function test_duplicate_scheduling_is_rejected_without_changing_records(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);
        $race = $this->makeRace($group, 'Only Once');

        $this->actingAs($user)
            ->post(route('seasons.races.store', $season), ['race_id' => $race->id])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('seasons.races.store', $season), ['race_id' => $race->id])
            ->assertSessionHasErrors('race_id');

        $this->assertSame(
            1,
            DB::table('season_races')->where('season_id', $season->id)->where('race_id', $race->id)->count()
        );
        $this->assertSame(1, DB::table('season_races')->where('season_id', $season->id)->count());
    }

    public function test_duplicate_round_numbers_cannot_be_introduced(): void
    {
        $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);
        $first = $this->makeRace($group, 'Duplicate A');
        $second = $this->makeRace($group, 'Duplicate B');

        DB::table('season_races')->insert([
            'season_id' => $season->id,
            'race_id' => $first->id,
            'round_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        DB::table('season_races')->insert([
            'season_id' => $season->id,
            'race_id' => $second->id,
            'round_number' => 1,
        ]);
    }

    public function test_cancelled_race_cannot_be_attached(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);
        $cancelled = $this->makeRace($group, 'Cancelled GP', 'cancelled');

        $this->actingAs($user)
            ->post(route('seasons.races.store', $season), ['race_id' => $cancelled->id])
            ->assertSessionHasErrors('race_id');

        $this->assertDatabaseMissing('season_races', [
            'season_id' => $season->id,
            'race_id' => $cancelled->id,
        ]);
    }

    public function test_championship_page_displays_scheduled_race_and_round(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);
        $scheduled = $this->makeRace($group, 'Displayed Round');
        $eligible = $this->makeRace($group, 'Still Eligible');

        $this->actingAs($user)
            ->post(route('seasons.races.store', $season), ['race_id' => $scheduled->id])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('championship.show', $season))
            ->assertOk()
            ->assertSee('Add race to season')
            ->assertSee('Displayed Round')
            ->assertSee('R1')
            ->assertSee('Still Eligible')
            // The scheduled race must not remain selectable in the form.
            ->assertDontSee('Displayed Round ·', false);
    }

    public function test_plain_member_does_not_see_scheduling_control(): void
    {
        $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);
        $this->makeRace($group, 'Hidden Target');

        $member = User::where('email', 'drv3@karting.app')->firstOrFail();

        $this->actingAs($member)
            ->get(route('championship.show', $season))
            ->assertOk()
            ->assertDontSee('Add race to season');
    }

    public function test_completed_scheduled_race_feeds_standings(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $season = $this->makeSeason($group);

        $driverA = Driver::factory()->create(['nickname' => 'PA']);
        $driverB = Driver::factory()->create(['nickname' => 'PB']);

        $race = Race::factory()->create([
            'group_id' => $group->id,
            'name' => 'Scored Round',
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

        $race->entries()->create($entry($driverA, 1, 30000));
        $race->entries()->create($entry($driverB, 2, 31000));

        $this->actingAs($user)
            ->post(route('seasons.races.store', $season), ['race_id' => $race->id])
            ->assertRedirect();

        // Driver A: 25 + 1 (pole) = 26. Driver B: 18.
        $this->actingAs($user)
            ->get(route('championship.show', $season))
            ->assertOk()
            ->assertSee('PA')
            ->assertSee('PB')
            ->assertSee('26')
            ->assertSee('18');
    }
}
