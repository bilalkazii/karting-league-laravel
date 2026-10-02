<?php

namespace Tests\Unit;

use App\Models\Driver;
use App\Models\Profile;
use App\Models\Race;
use App\Models\RaceEntry;
use App\Models\Season;
use App\Models\SeasonScoring;
use App\Models\Team;
use App\Support\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection as SupportCollection;
use Tests\TestCase;

/**
 * Locks in the league's confirmed scoring rules against the defaults shipped in
 * StandingsService. Every test builds its own completed race so the expected
 * numbers are derived from the race results, never from a stored total.
 */
class F1ScoringRulesTest extends TestCase
{
    use RefreshDatabase;

    private function driver(string $name): Driver
    {
        return Driver::factory()->for(Profile::factory()->create(['full_name' => $name]))->create();
    }

    private function season(): Season
    {
        $season = Season::factory()->create();
        SeasonScoring::create([
            'season_id' => $season->id,
            'mode' => 'automatic',
            'pole_position_points' => 0,
            'participation_points' => 0,
            'dnf_points' => 0,
            'dns_points' => 0,
        ]);

        return $season;
    }

    private function entry(Race $race, Driver $driver, array $fields = []): RaceEntry
    {
        return RaceEntry::create(array_merge([
            'race_id' => $race->id,
            'driver_id' => $driver->id,
            'kart_number' => 1,
            'status' => 'finished',
            'confirmed' => true,
            'ready' => true,
            'grid_position' => null,
            'grid_penalty_seconds' => 0,
            'qualifying_time_ms' => null,
            'qualifying_status' => 'not_started',
            'finish_position' => null,
            'penalty_total_seconds' => 0,
            'notes' => '',
        ], $fields));
    }

    /**
     * Build one completed race in the given round, where each driver either
     * finishes at the given position or records a non-finishing status.
     *
     * @param  list<array{0: Driver, 1: int|null}>  $results
     */
    private function raceWithResults(Season $season, array $results, int $round = 1): Race
    {
        $race = Race::factory()->create(['status' => 'completed', 'date' => '2026-03-14']);
        $season->races()->attach($race->id, ['round_number' => $round]);

        foreach ($results as [$driver, $position]) {
            $this->entry($race, $driver, ['finish_position' => $position]);
        }

        return $race;
    }

    private function points(Race $race, Season $season): SupportCollection
    {
        $computed = StandingsService::computeRacePoints(
            StandingsService::raceEntries($race->entries()->get()),
            StandingsService::scoringFor($season),
        );

        return collect($computed['entries'])->keyBy('driver_id');
    }

    public function test_default_scale_is_the_current_f1_scale(): void
    {
        $this->assertSame(
            [25, 18, 15, 12, 10, 8, 6, 4, 2, 1],
            array_values(StandingsService::F1_POINTS_BY_POSITION),
        );
        $this->assertSame(0, StandingsService::scoringFor($this->season())['fastest_lap_points']);
    }

    public function test_every_position_on_the_scale_awards_exactly_its_f1_value(): void
    {
        $season = $this->season();

        $expected = [1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 4, 9 => 2, 10 => 1];
        $drivers = [];
        $results = [];

        foreach (array_keys($expected) as $position) {
            $driver = $this->driver("P{$position} Driver");
            $drivers[$position] = $driver;
            $results[] = [$driver, $position];
        }

        $byDriver = $this->points($this->raceWithResults($season, $results), $season);

        foreach ($expected as $position => $points) {
            $this->assertSame(
                $points,
                $byDriver[$drivers[$position]->id]['points'],
                "P{$position} must score {$points}",
            );
        }
    }

    public function test_positions_beyond_tenth_score_zero(): void
    {
        $season = $this->season();
        $eleventh = $this->driver('Eleventh');
        $twelfth = $this->driver('Twelfth');

        $race = $this->raceWithResults($season, [[$eleventh, 11], [$twelfth, 12]]);
        $byDriver = $this->points($race, $season);

        $this->assertSame(0, $byDriver[$eleventh->id]['points']);
        $this->assertSame(0, $byDriver[$twelfth->id]['points']);
    }

    public function test_non_finishing_results_score_zero(): void
    {
        $season = $this->season();

        $dnf = $this->driver('DNF Driver');
        $retired = $this->driver('Retired Driver');
        $withdrawn = $this->driver('Withdrawn Driver');
        $dns = $this->driver('DNS Driver');

        $race = Race::factory()->create(['status' => 'completed', 'date' => '2026-03-14']);
        $season->races()->attach($race->id, ['round_number' => 1]);

        foreach (['dnf' => $dnf, 'retired' => $retired, 'withdrawn' => $withdrawn, 'dns' => $dns] as $status => $driver) {
            $this->entry($race, $driver, ['status' => $status, 'finish_position' => null]);
        }

        $byDriver = $this->points($race, $season);

        foreach (['dnf' => $dnf, 'retired' => $retired, 'withdrawn' => $withdrawn, 'dns' => $dns] as $status => $driver) {
            $this->assertSame(
                0,
                $byDriver[$driver->id]['points'],
                "a {$status} result must score zero",
            );
        }
    }

    public function test_disqualification_scores_zero(): void
    {
        $season = $this->season();
        $winner = $this->driver('Race Winner');
        $disqualified = $this->driver('Disqualified Driver');

        $race = $this->raceWithResults($season, [[$winner, 1], [$disqualified, 2]]);
        $disqualifiedEntry = $race->entries()->where('driver_id', $disqualified->id)->first();
        $disqualifiedEntry->update(['status' => 'disqualified', 'finish_position' => null]);

        $byDriver = $this->points($race->fresh(), $season);

        $this->assertSame(25, $byDriver[$winner->id]['points']);
        $this->assertSame(0, $byDriver[$disqualified->id]['points']);
    }

    public function test_no_fastest_lap_bonus_is_ever_awarded(): void
    {
        $season = $this->season();
        $winner = $this->driver('Race Winner');

        $race = Race::factory()->create(['status' => 'completed', 'date' => '2026-03-14']);
        $season->races()->attach($race->id, ['round_number' => 1]);

        // A pole sitter who also set the fastest qualifying lap is still only
        // worth the P1 value: F1 has no fastest-lap bonus and this league
        // configures no pole bonus.
        $this->entry($race, $winner, [
            'finish_position' => 1,
            'grid_position' => 1,
            'qualifying_time_ms' => 41000,
            'qualifying_status' => 'completed',
        ]);

        $scoring = StandingsService::scoringFor($season);
        $this->assertSame(0, $scoring['fastest_lap_points']);
        $this->assertSame(0, $scoring['pole_position_points']);

        $this->assertSame(25, $this->points($race, $season)[$winner->id]['points']);
    }

    public function test_season_scoring_rows_override_defaults_and_unset_positions_fall_back(): void
    {
        $season = $this->season();

        // A league may document a different scale; positions it has not
        // configured still fall back to the F1 value rather than to zero.
        $season->scoringPoints()->create(['position' => 1, 'points' => 20]);

        $this->assertSame(20, StandingsService::scoringFor($season->fresh())['points_by_position'][1]);
        $this->assertSame(18, StandingsService::scoringFor($season->fresh())['points_by_position'][2]);
        $this->assertSame(1, StandingsService::scoringFor($season->fresh())['points_by_position'][10]);
    }

    public function test_points_accumulate_across_rounds(): void
    {
        $season = $this->season();
        $winner = $this->driver('Two Time Winner');
        $rival = $this->driver('Rival');

        $this->raceWithResults($season, [[$winner, 1], [$rival, 2]], 1);
        $this->raceWithResults($season, [[$winner, 1], [$rival, 2]], 2);

        $standings = StandingsService::computeStandings(
            StandingsService::scoredRaces($season->races()->get()),
            StandingsService::scoringFor($season),
        )['final'];

        $byDriver = collect($standings)->keyBy('driver_id');

        $this->assertSame(50, $byDriver[$winner->id]['points']);
        $this->assertSame(36, $byDriver[$rival->id]['points']);
        $this->assertSame(2, $byDriver[$winner->id]['wins']);
    }

    public function test_equal_points_are_ordered_by_wins(): void
    {
        $season = $this->season();
        $moreWins = $this->driver('More Wins');
        $morePodiums = $this->driver('More Podiums');

        // 25+8 = 33 and 18+15 = 33, so the totals tie and the win decides it.
        $this->raceWithResults($season, [[$moreWins, 1], [$morePodiums, 2]], 1);
        $this->raceWithResults($season, [[$moreWins, 6], [$morePodiums, 3]], 2);

        $final = StandingsService::computeStandings(
            StandingsService::scoredRaces($season->races()->get()),
            StandingsService::scoringFor($season),
        )['final'];

        $this->assertSame(33, $final[0]['points']);
        $this->assertSame(33, $final[1]['points']);
        $this->assertSame($moreWins->id, $final[0]['driver_id'], 'more wins must win the tie');
        $this->assertSame(1, $final[0]['wins']);
        $this->assertSame(0, $final[1]['wins']);
    }

    public function test_team_points_are_the_sum_of_member_season_points(): void
    {
        $season = $this->season();

        $a = $this->driver('Driver A');
        $b = $this->driver('Driver B');
        $c = $this->driver('Driver C');

        // Round 1: A 25, B 18, C 15. Round 2: A 18, B 15, C 25.
        $this->raceWithResults($season, [[$a, 1], [$b, 2], [$c, 3]], 1);
        $this->raceWithResults($season, [[$a, 2], [$b, 3], [$c, 1]], 2);

        $standings = StandingsService::computeStandings(
            StandingsService::scoredRaces($season->races()->get()),
            StandingsService::scoringFor($season),
        )['final'];

        $teamA = Team::factory()->create(['name' => 'Team A']);
        $teamA->members()->attach([$a->id, $b->id]);
        $teamB = Team::factory()->create(['name' => 'Team B']);
        $teamB->members()->attach([$c->id]);

        $byName = collect(StandingsService::teamStandings(
            Team::with('members.profile')->get(),
            $standings,
        ))->keyBy('name');

        // A: 43+33 = 76. B: 40.
        $this->assertSame(76, $byName['Team A']['points']);
        $this->assertSame(40, $byName['Team B']['points']);
        $this->assertSame(1, $byName['Team A']['position']);
        $this->assertSame(2, $byName['Team B']['position']);
        $this->assertSame(0, $byName['Team A']['points_gap']);
        $this->assertSame(36, $byName['Team B']['points_gap']);
        $this->assertSame(2, $byName['Team A']['member_count']);
        $this->assertSame(1, $byName['Team B']['member_count']);
    }

    public function test_team_points_reconcile_with_the_driver_leaderboard(): void
    {
        $season = $this->season();

        $drivers = [$this->driver('Solo A'), $this->driver('Solo B')];
        $this->raceWithResults($season, [[$drivers[0], 1], [$drivers[1], 2]], 1);
        $this->raceWithResults($season, [[$drivers[0], 3], [$drivers[1], 1]], 2);

        $standings = StandingsService::computeStandings(
            StandingsService::scoredRaces($season->races()->get()),
            StandingsService::scoringFor($season),
        )['final'];

        $team = Team::factory()->create(['name' => 'Solo Team']);
        $team->members()->attach($drivers[0]->id);

        $driverTotals = collect($standings)->keyBy('driver_id');
        $teamRow = StandingsService::teamStandings(
            Team::with('members.profile')->get(),
            $standings,
        )[0];

        $this->assertSame($driverTotals[$drivers[0]->id]['points'], $teamRow['points']);
        $this->assertSame(
            collect($standings)->sum('points'),
            $teamRow['points'] + $driverTotals[$drivers[1]->id]['points'],
        );
    }

    public function test_a_team_with_no_season_drivers_scores_zero(): void
    {
        $season = $this->season();
        $driver = $this->driver('Only Driver');

        $this->raceWithResults($season, [[$driver, 1]], 1);

        $standings = StandingsService::computeStandings(
            StandingsService::scoredRaces($season->races()->get()),
            StandingsService::scoringFor($season),
        )['final'];

        $idle = Team::factory()->create(['name' => 'Idle Team']);

        $row = StandingsService::teamStandings(
            Team::with('members.profile')->get(),
            $standings,
        )[0];

        $this->assertSame($idle->id, $row['team_id']);
        $this->assertSame(0, $row['points']);
        $this->assertSame(0, $row['member_count']);
    }
}
