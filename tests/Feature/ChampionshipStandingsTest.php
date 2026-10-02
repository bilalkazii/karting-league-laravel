<?php

namespace Tests\Feature;

use App\Enums\RaceDriverStatus;
use App\Enums\RacePenaltyStatus;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Profile;
use App\Models\Race;
use App\Models\RacePenalty;
use App\Models\Season;
use App\Models\Team;
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

        $driverA = Driver::factory()->create([
            'nickname' => 'AA',
            'profile_id' => Profile::factory()->create(['full_name' => 'Zahid Khatib'])->id,
        ]);
        $driverB = Driver::factory()->create([
            'nickname' => 'BB',
            'profile_id' => Profile::factory()->create(['full_name' => 'Shoaib Khan'])->id,
        ]);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Standings Cup',
            'status' => 'active',
        ]);

        $round1 = $this->completedRace($group, 1, $driverA, $driverB);
        $round2 = $this->completedRace($group, 2, $driverA, $driverB);
        $season->races()->attach($round1->id, ['round_number' => 1]);
        $season->races()->attach($round2->id, ['round_number' => 2]);

        // No season scoring config exists, so there is no pole bonus: A takes
        // 25 twice and B takes 18 twice. Assert on the view's own rows rather
        // than loose substrings, which match unrelated numbers in the markup.
        $response = $this->actingAs($user)->get(route('championship.show', $season));

        $response->assertOk()->assertSee('Standings')->assertSeeInOrder(['AA', 'BB']);

        $byDriver = collect($response->viewData('standings'))->keyBy('driver_id');

        $this->assertSame(50, $byDriver[$driverA->id]['points']);
        $this->assertSame(36, $byDriver[$driverB->id]['points']);
        $this->assertSame(1, $byDriver[$driverA->id]['position']);
        $this->assertSame(2, $byDriver[$driverB->id]['position']);
        $this->assertSame(2, $byDriver[$driverA->id]['wins']);
        $this->assertSame(0, $byDriver[$driverA->id]['poles']);
    }

    public function test_championship_page_shows_readable_driver_names_not_just_nicknames(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $driver = Driver::factory()->create([
            'nickname' => 'ZP',
            'profile_id' => Profile::factory()->create(['full_name' => 'Zahid Khatib'])->id,
        ]);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Readable Names Cup',
            'status' => 'active',
        ]);

        $round1 = $this->completedRace($group, 1, $driver, Driver::factory()->create([
            'nickname' => 'SK',
            'profile_id' => Profile::factory()->create(['full_name' => 'Shoaib Khan'])->id,
        ]));
        $season->races()->attach($round1->id, ['round_number' => 1]);

        $this->actingAs($user)
            ->get(route('championship.show', $season))
            ->assertOk()
            ->assertSee('Zahid Khatib')
            ->assertSee('Shoaib Khan');
    }

    public function test_championship_page_shows_per_event_columns_that_sum_to_total(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $driverA = Driver::factory()->create(['nickname' => 'AA']);
        $driverB = Driver::factory()->create(['nickname' => 'BB']);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Event Column Cup',
            'status' => 'active',
        ]);

        $round1 = $this->completedRace($group, 1, $driverA, $driverB);
        $round2 = $this->completedRace($group, 2, $driverB, $driverA);
        $season->races()->attach($round1->id, ['round_number' => 1]);
        $season->races()->attach($round2->id, ['round_number' => 2]);

        $response = $this->actingAs($user)
            ->get(route('championship.show', $season))
            ->assertOk();

        // Each event name appears as its own column header.
        $response->assertSee('Round 1');
        $response->assertSee('Round 2');
        // Driver A: 26 + 18 = 44, driver B: 18 + 26 = 44.
        $response->assertSee('44');
    }

    public function test_championship_page_shows_remaining_races_and_final_date(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $winner = Driver::factory()->create(['nickname' => 'AA']);
        $runnerUp = Driver::factory()->create(['nickname' => 'BB']);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Progress Cup',
            'status' => 'active',
        ]);

        $round1 = $this->completedRace($group, 1, $winner, $runnerUp);
        $season->races()->attach($round1->id, ['round_number' => 1]);

        $autumn = Race::factory()->create([
            'group_id' => $group->id,
            'name' => 'Autumn Sprint',
            'date' => '2026-10-03',
            'status' => 'draft',
        ]);
        $season->races()->attach($autumn->id, ['round_number' => 2]);

        $final = Race::factory()->create([
            'group_id' => $group->id,
            'name' => 'Champions Finale',
            'date' => '2026-11-21',
            'status' => 'draft',
        ]);
        $season->races()->attach($final->id, ['round_number' => 3]);

        $this->actingAs($user)
            ->get(route('championship.show', $season))
            ->assertOk()
            ->assertSee('2 remaining')
            ->assertSee('Final 21 Nov 2026')
            ->assertSee('Champions Finale');
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

    public function test_championship_page_shows_constructors_standings_summed_from_members(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $driverA = Driver::factory()->create(['nickname' => 'AA']);
        $driverB = Driver::factory()->create(['nickname' => 'BB']);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Constructors Cup',
            'status' => 'active',
        ]);

        $round1 = $this->completedRace($group, 1, $driverA, $driverB);
        $season->races()->attach($round1->id, ['round_number' => 1]);

        $team = Team::create([
            'group_id' => $group->id,
            'name' => 'Apex Racing',
            'logo_initials' => 'AR',
        ]);
        $team->members()->attach([$driverA->id, $driverB->id]);

        $response = $this->actingAs($user)->get(route('championship.show', $season));

        $response->assertOk()->assertSee('Apex Racing')->assertSee('Constructors');

        $teamStandings = $response->viewData('teamStandings');

        $apex = collect($teamStandings)->firstWhere('name', 'Apex Racing');

        $this->assertNotNull($apex, 'the team must appear in the constructors standings');
        $this->assertSame(2, $apex['member_count']);
        // A takes 25, B takes 18, so the team is 43 with no stored total.
        $this->assertSame(43, $apex['points']);

        // Every driver's season points are accounted for by exactly one team.
        $driverPoints = collect($response->viewData('standings'))->sum('points');
        $this->assertSame($driverPoints, collect($teamStandings)->sum('points'));
    }

    public function test_championship_page_flags_drivers_level_on_points_as_tied(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $driverA = Driver::factory()->create(['nickname' => 'AA']);
        $driverB = Driver::factory()->create(['nickname' => 'BB']);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Tie Cup',
            'status' => 'active',
        ]);

        // Two races that swap P1 and P2 leave both drivers on 43.
        $round1 = $this->completedRace($group, 1, $driverA, $driverB);
        $round2 = $this->completedRace($group, 2, $driverB, $driverA);
        $season->races()->attach($round1->id, ['round_number' => 1]);
        $season->races()->attach($round2->id, ['round_number' => 2]);

        $response = $this->actingAs($user)->get(route('championship.show', $season));

        $response->assertOk();

        $standings = collect($response->viewData('standings'));

        $this->assertSame([43, 43], $standings->pluck('points')->all());
        $this->assertFalse($standings[0]['tied'], 'the first row of a tie is not flagged');
        $this->assertTrue($standings[1]['tied'], 'the second row of a tie is flagged');
        $response->assertSee('Tied');
    }

    private function teamPoints(User $user, Season $season, string $teamName): int
    {
        $response = $this->actingAs($user)->get(route('championship.show', $season));

        $response->assertOk();

        $team = collect($response->viewData('teamStandings'))->firstWhere('name', $teamName);

        $this->assertNotNull($team, "{$teamName} must appear in the constructors standings");

        return $team['points'];
    }

    /**
     * Constructors' totals are derived on every request, so amending a result
     * has to move the team total rather than leave a stale number behind.
     */
    public function test_team_points_recalculate_when_a_result_is_corrected(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $driverA = Driver::factory()->create(['nickname' => 'AA']);
        $driverB = Driver::factory()->create(['nickname' => 'BB']);

        $season = Season::create(['group_id' => $group->id, 'name' => 'Recalc Cup', 'status' => 'active']);
        $round1 = $this->completedRace($group, 1, $driverA, $driverB);
        $season->races()->attach($round1->id, ['round_number' => 1]);

        $team = Team::create(['group_id' => $group->id, 'name' => 'Apex Racing', 'logo_initials' => 'AR']);
        $team->members()->attach([$driverA->id, $driverB->id]);

        // A takes P1 (25) and B takes P2 (18) = 43.
        $this->assertSame(43, $this->teamPoints($user, $season, 'Apex Racing'));

        // A is demoted to P4 (12) and B promoted to P1 (25): 37, so the team
        // total has to follow rather than repeat the old figure.
        $round1->entries()->where('driver_id', $driverA->id)->update(['finish_position' => 4]);
        $round1->entries()->where('driver_id', $driverB->id)->update(['finish_position' => 1]);

        $this->assertSame(37, $this->teamPoints($user, $season, 'Apex Racing'));

        // A driver is disqualified after the fact: their points go, the team
        // total follows, and the remaining member's 25 stands alone.
        $round1->entries()->where('driver_id', $driverA->id)->update([
            'status' => RaceDriverStatus::Disqualified,
            'finish_position' => null,
        ]);

        $this->assertSame(25, $this->teamPoints($user, $season, 'Apex Racing'));

        // The result is un-amended and the team is back to 43.
        $round1->entries()->where('driver_id', $driverA->id)->update([
            'status' => RaceDriverStatus::Finished,
            'finish_position' => 1,
        ]);
        $round1->entries()->where('driver_id', $driverB->id)->update(['finish_position' => 2]);

        $this->assertSame(43, $this->teamPoints($user, $season, 'Apex Racing'));
    }

    /**
     * A time penalty is recorded against a race entry and surfaced in the
     * driver stats, but it does not itself deduct championship points: the F1
     * scale awards points by finishing position. A penalty is enforced through
     * the entry's finish position, which the correction test above covers.
     *
     * This guards the constructors' table against being cached, and pins the
     * current penalty behaviour so a future rule change is deliberate.
     */
    public function test_penalties_are_reported_but_do_not_alter_championship_points(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $driverA = Driver::factory()->create(['nickname' => 'AA']);
        $driverB = Driver::factory()->create(['nickname' => 'BB']);

        $season = Season::create(['group_id' => $group->id, 'name' => 'Penalty Cup', 'status' => 'active']);
        $round1 = $this->completedRace($group, 1, $driverA, $driverB);
        $season->races()->attach($round1->id, ['round_number' => 1]);

        $team = Team::create(['group_id' => $group->id, 'name' => 'Apex Racing', 'logo_initials' => 'AR']);
        $team->members()->attach([$driverA->id, $driverB->id]);

        $before = $this->teamPoints($user, $season, 'Apex Racing');
        $this->assertSame(43, $before);

        $penalty = RacePenalty::create([
            'race_id' => $round1->id,
            'driver_id' => $driverA->id,
            'issued_by' => $user->driver->id,
            'seconds' => 5,
            'reason' => 'Five second penalty for a jump start.',
            'status' => RacePenaltyStatus::Issued,
        ]);

        // The penalty shows up on the leaderboard as a counted penalty, and the
        // race is no longer a clean one for that driver.
        $response = $this->actingAs($user)->get(route('championship.show', $season));
        $response->assertOk();

        $penalised = collect($response->viewData('standings'))
            ->firstWhere('driver_id', $driverA->id);

        $this->assertSame(1, $penalised['penalties']);
        $this->assertSame(5, $penalised['penalty_seconds']);
        $this->assertSame(0, $penalised['clean_races']);
        $this->assertSame(25, $penalised['points']);

        // The constructors' total is unchanged, and recomputed rather than
        // served from a stale snapshot.
        $this->assertSame(43, $this->teamPoints($user, $season, 'Apex Racing'));

        // A cancelled penalty stops counting.
        $penalty->update(['status' => RacePenaltyStatus::Cancelled]);

        $response = $this->actingAs($user)->get(route('championship.show', $season));
        $response->assertOk();

        $reinstated = collect($response->viewData('standings'))
            ->firstWhere('driver_id', $driverA->id);

        $this->assertSame(0, $reinstated['penalties']);
        $this->assertSame(0, $reinstated['penalty_seconds']);
        $this->assertSame(1, $reinstated['clean_races']);
        $this->assertSame(43, $this->teamPoints($user, $season, 'Apex Racing'));
    }
}
