<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Support\StandingsService;
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

    /**
     * The seeded season awards quote points, so those numbers have to agree
     * with the standings the app actually computes from the seeded races. A
     * hardcoded figure here silently contradicts the leaderboard.
     */
    public function test_season_award_points_match_the_computed_standings(): void
    {
        $this->seed();

        $season = Season::where('name', 'Crew Championship 2026')->firstOrFail();

        $standings = StandingsService::computeStandings(
            $season->races()->with(['entries', 'penalties'])->get(),
            StandingsService::scoringFor($season),
        )['final'];

        $this->assertNotEmpty($standings, 'the demo season must produce standings');

        $leaderPoints = $standings[0]['points'];

        $winnerAward = Award::where('season_id', $season->id)
            ->where('type', 'championship_winner')
            ->firstOrFail();

        $this->assertSame($leaderPoints.' pts', $winnerAward->value);

        $apex = Team::where('name', 'Apex Racing')->firstOrFail();
        $apexPoints = collect(StandingsService::teamStandings(
            Team::with('members.profile')->where('group_id', $apex->group_id)->get(),
            $standings,
        ))->firstWhere('team_id', $apex->id)['points'];

        $teamAward = Award::where('season_id', $season->id)
            ->where('type', 'team_champion')
            ->firstOrFail();

        $this->assertSame($apexPoints.' pts', $teamAward->value);
    }

    /**
     * The leaderboard is the source of truth, so the demo page must render the
     * same figures the awards quote rather than two different sets of numbers.
     */
    public function test_the_demo_championship_page_reconciles_with_its_awards(): void
    {
        $this->seed();

        $user = User::where('email', 'demo@karting.app')->firstOrFail();
        $season = Season::where('name', 'Crew Championship 2026')->firstOrFail();

        $response = $this->actingAs($user)->get(route('championship.show', $season));
        $response->assertOk();

        $leader = collect($response->viewData('standings'))->first();
        $apex = collect($response->viewData('teamStandings'))->firstWhere('name', 'Apex Racing');

        $this->assertNotNull($leader);
        $this->assertNotNull($apex);

        $winnerAward = Award::where('season_id', $season->id)
            ->where('type', 'championship_winner')
            ->firstOrFail();
        $teamAward = Award::where('season_id', $season->id)
            ->where('type', 'team_champion')
            ->firstOrFail();

        $this->assertSame($leader['points'].' pts', $winnerAward->value);
        $this->assertSame($apex['points'].' pts', $teamAward->value);
    }
}
