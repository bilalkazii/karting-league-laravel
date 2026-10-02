<?php

namespace Tests\Unit;

use App\Models\Driver;
use App\Models\Profile;
use App\Models\Race;
use App\Models\RaceEntry;
use App\Models\RacePenalty;
use App\Models\Season;
use App\Models\SeasonScoring;
use App\Support\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function driver(): Driver
    {
        return Driver::factory()->for(Profile::factory()->create())->create();
    }

    private function seasonWithScoring(): Season
    {
        $season = Season::factory()->create();
        SeasonScoring::create([
            'season_id' => $season->id,
            'mode' => 'automatic',
            'pole_position_points' => 1,
            'participation_points' => 0,
            'dnf_points' => 0,
            'dns_points' => 0,
        ]);

        return $season;
    }

    private function completedRace(array $overrides = []): Race
    {
        return Race::factory()->create(array_merge(['status' => 'completed'], $overrides));
    }

    private function entry(Race $race, int $driverId, array $fields = []): void
    {
        RaceEntry::create(array_merge([
            'race_id' => $race->id,
            'driver_id' => $driverId,
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

    public function test_is_classified_recognizes_terminal_results(): void
    {
        $this->assertTrue(StandingsService::isClassified('finished'));
        $this->assertTrue(StandingsService::isClassified('dnf'));
        $this->assertTrue(StandingsService::isClassified('dns'));
        $this->assertTrue(StandingsService::isClassified('retired'));
        $this->assertFalse(StandingsService::isClassified('invited'));
        $this->assertFalse(StandingsService::isClassified('racing'));
    }

    public function test_race_pole_driver_id_returns_fastest_valid(): void
    {
        $entries = [
            ['driver_id' => 1, 'status' => 'finished', 'qualifying_time_ms' => 42000, 'qualifying_status' => 'invalid', 'finish_position' => 2, 'grid_position' => 1],
            ['driver_id' => 2, 'status' => 'finished', 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed', 'finish_position' => 1, 'grid_position' => 2],
            ['driver_id' => 3, 'status' => 'dnf', 'qualifying_time_ms' => null, 'qualifying_status' => 'not_started', 'finish_position' => null, 'grid_position' => null],
        ];

        $this->assertSame(2, StandingsService::racePoleDriverId($entries));
    }

    public function test_compute_race_points_awards_positions_and_pole_but_never_dnf_points(): void
    {
        $scoring = [
            'points_by_position' => [1 => 25, 2 => 18, 3 => 15],
            'pole_position_points' => 1,
            'participation_points' => 0,
            'dnf_points' => 5,
            'dns_points' => 5,
        ];
        $entries = [
            ['driver_id' => 1, 'status' => 'finished', 'finish_position' => 1, 'grid_position' => 2, 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed'],
            ['driver_id' => 2, 'status' => 'finished', 'finish_position' => 2, 'grid_position' => 1, 'qualifying_time_ms' => 41800, 'qualifying_status' => 'completed'],
            ['driver_id' => 3, 'status' => 'dnf', 'finish_position' => null, 'grid_position' => 3, 'qualifying_time_ms' => null, 'qualifying_status' => 'not_started'],
        ];

        $result = StandingsService::computeRacePoints($entries, $scoring);

        $this->assertSame(1, $result['pole_driver_id']);
        $byDriver = collect($result['entries'])->keyBy('driver_id');

        // Winner: 25 + 1 pole.
        $this->assertSame(26, $byDriver[1]['points']);
        // Runner-up: 18.
        $this->assertSame(18, $byDriver[2]['points']);
        // A DNF scores zero even when the season config still carries an old
        // dnf_points value, so a confirmed result is never quietly re-scored.
        $this->assertSame(0, $byDriver[3]['points']);
    }

    public function test_compute_standings_orders_by_points_then_wins_and_tracks_snapshots(): void
    {
        $race = $this->completedRace(['date' => '2026-03-14']);
        $d1 = $this->driver()->id;
        $d2 = $this->driver()->id;

        $this->entry($race, $d1, ['finish_position' => 1, 'grid_position' => 1, 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed']);
        $this->entry($race, $d2, ['finish_position' => 2, 'grid_position' => 2, 'qualifying_time_ms' => 41800, 'qualifying_status' => 'completed']);

        $result = StandingsService::computeStandings([$race], StandingsService::scoringFor($this->seasonWithScoring()));

        $this->assertCount(1, $result['snapshots']);
        $this->assertCount(2, $result['final']);

        $this->assertSame($d1, $result['final'][0]['driver_id']);
        $this->assertSame(26, $result['final'][0]['points']);
        $this->assertSame(1, $result['final'][0]['wins']);
        $this->assertSame($d2, $result['final'][1]['driver_id']);
        $this->assertSame(18, $result['final'][1]['points']);
    }

    public function test_compute_standings_accumulates_across_races_with_second_snapshot(): void
    {
        $r1 = $this->completedRace(['date' => '2026-03-14']);
        $r2 = $this->completedRace(['date' => '2026-05-09']);
        $d1 = $this->driver()->id;
        $d2 = $this->driver()->id;

        $this->entry($r1, $d1, ['finish_position' => 1, 'grid_position' => 1, 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed']);
        $this->entry($r1, $d2, ['finish_position' => 2, 'grid_position' => 2, 'qualifying_time_ms' => 41800, 'qualifying_status' => 'completed']);

        $this->entry($r2, $d2, ['finish_position' => 1, 'grid_position' => 1, 'qualifying_time_ms' => 41720, 'qualifying_status' => 'completed']);
        $this->entry($r2, $d1, ['finish_position' => 2, 'grid_position' => 2, 'qualifying_time_ms' => 41790, 'qualifying_status' => 'completed']);

        $result = StandingsService::computeStandings([$r1, $r2], StandingsService::scoringFor($this->seasonWithScoring()));

        $this->assertCount(2, $result['snapshots']);

        // Each driver wins one round with pole; points tie at 44, so the better
        // overall qualifying best (d2) takes the standings lead.
        $final = $result['final'];
        $this->assertSame($d2, $final[0]['driver_id']);
        $this->assertSame(44, $final[0]['points']);
        $this->assertSame($d1, $final[1]['driver_id']);
        $this->assertSame(44, $final[1]['points']);
        $this->assertSame(2, $final[0]['podiums']);
    }

    public function test_event_breakdown_columns_reconcile_with_standings_totals(): void
    {
        $season = $this->seasonWithScoring();

        $r1 = $this->completedRace(['date' => '2026-03-14']);
        $r2 = $this->completedRace(['date' => '2026-05-09']);
        $d1 = $this->driver()->id;
        $d2 = $this->driver()->id;

        $this->entry($r1, $d1, ['finish_position' => 1, 'grid_position' => 1, 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed']);
        $this->entry($r1, $d2, ['finish_position' => 2, 'grid_position' => 2, 'qualifying_time_ms' => 41800, 'qualifying_status' => 'completed']);
        $this->entry($r2, $d1, ['finish_position' => 2, 'grid_position' => 2, 'qualifying_time_ms' => 41790, 'qualifying_status' => 'completed']);
        $this->entry($r2, $d2, ['finish_position' => 1, 'grid_position' => 1, 'qualifying_time_ms' => 41720, 'qualifying_status' => 'completed']);

        $races = collect([$r1, $r2]);
        $races->each(fn ($race, $i) => $season->races()->attach($race->id, ['round_number' => $i + 1]));
        $races = $season->races()->get();

        $scoring = StandingsService::scoringFor($season);
        $events = StandingsService::eventBreakdown($races, $scoring);
        $final = StandingsService::computeStandings($races, $scoring)['final'];

        $this->assertCount(2, $events);
        $this->assertSame([1, 2], array_column($events, 'round'));

        // Column values sum to the standings total for every driver.
        foreach ($final as $row) {
            $sum = 0;
            foreach ($events as $event) {
                $sum += $event['points'][$row['driver_id']] ?? 0;
            }
            $this->assertSame($row['points'], $sum, "columns must reconcile for driver {$row['driver_id']}");
        }

        $this->assertSame(26, $events[0]['points'][$d1]);
        $this->assertSame(26, $events[1]['points'][$d2]);
    }

    public function test_event_breakdown_omits_races_that_have_not_happened(): void
    {
        $season = $this->seasonWithScoring();
        $completed = $this->completedRace(['date' => '2026-03-14']);
        $draft = Race::factory()->create(['date' => '2026-10-03', 'status' => 'draft']);

        $d1 = $this->driver()->id;
        $this->entry($completed, $d1, ['finish_position' => 1, 'grid_position' => 1, 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed']);
        $this->entry($draft, $d1, ['finish_position' => 1, 'grid_position' => 1]);

        $season->races()->attach($completed->id, ['round_number' => 1]);
        $season->races()->attach($draft->id, ['round_number' => 2]);

        $events = StandingsService::eventBreakdown($season->races()->get(), StandingsService::scoringFor($season));

        $this->assertCount(1, $events);
        $this->assertSame(1, $events[0]['round']);
    }

    public function test_scored_races_follow_round_number_then_date(): void
    {
        $season = $this->seasonWithScoring();
        $driver = $this->driver()->id;

        // Round 2 runs before round 1 by date; round order must win.
        $round1 = $this->completedRace(['date' => '2026-07-18']);
        $round2 = $this->completedRace(['date' => '2026-03-14']);

        $this->entry($round1, $driver, ['finish_position' => 1]);
        $this->entry($round2, $driver, ['finish_position' => 1]);

        $season->races()->attach($round1->id, ['round_number' => 1]);
        $season->races()->attach($round2->id, ['round_number' => 2]);

        $scored = StandingsService::scoredRaces($season->races()->get());

        $this->assertSame([$round1->id, $round2->id], $scored->pluck('id')->all());
    }

    public function test_progress_reports_remaining_races_and_final_date(): void
    {
        $completed = $this->completedRace(['date' => '2026-03-14']);
        $next = Race::factory()->create(['date' => '2026-10-03', 'status' => 'draft']);
        $final = Race::factory()->create(['date' => '2026-11-21', 'status' => 'draft']);

        $progress = StandingsService::progress([$completed, $final, $next]);

        $this->assertSame(2, $progress['remaining']);
        $this->assertSame($final->id, $progress['final_race']->id);
        $this->assertSame('2026-11-21', $progress['final_race_date']->format('Y-m-d'));
    }

    public function test_progress_ignores_cancelled_races(): void
    {
        $completed = $this->completedRace(['date' => '2026-03-14']);
        $cancelled = Race::factory()->create(['date' => '2026-12-01', 'status' => 'cancelled']);

        $progress = StandingsService::progress([$completed, $cancelled]);

        $this->assertSame(0, $progress['remaining']);
        $this->assertNull($progress['final_race']);
        $this->assertNull($progress['final_race_date']);
    }

    public function test_standings_ignore_cancelled_penalties_but_track_issued_ones(): void
    {
        $season = $this->seasonWithScoring();
        $race = $this->completedRace(['date' => '2026-03-14']);
        $season->races()->attach($race->id, ['round_number' => 1]);

        $d1 = $this->driver()->id;
        $this->entry($race, $d1, ['finish_position' => 1, 'grid_position' => 1, 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed']);

        RacePenalty::create([
            'race_id' => $race->id,
            'driver_id' => $d1,
            'seconds' => 5,
            'reason' => 'Contact under braking',
            'issued_by' => $this->driver()->id,
            'status' => 'issued',
        ]);

        $races = $season->races()->with(['entries', 'penalties'])->get();
        $final = StandingsService::computeStandings($races, StandingsService::scoringFor($season))['final'];

        $this->assertSame(1, $final[0]['penalties']);
        $this->assertSame(5, $final[0]['penalty_seconds']);
        $this->assertSame(0, $final[0]['clean_races']);

        $race->penalties()->update(['status' => 'cancelled']);
        $final = StandingsService::computeStandings(
            $season->races()->with(['entries', 'penalties'])->get(),
            StandingsService::scoringFor($season)
        )['final'];

        $this->assertSame(0, $final[0]['penalties']);
        $this->assertSame(1, $final[0]['clean_races']);
        // Points are unaffected by penalties under the default scoring config.
        $this->assertSame(26, $final[0]['points']);
    }

    public function test_compute_standings_skips_non_completed_races(): void
    {
        $draft = Race::factory()->create(['status' => 'draft']);
        $d1 = $this->driver()->id;
        $this->entry($draft, $d1, ['finish_position' => 1]);

        $result = StandingsService::computeStandings([$draft], StandingsService::scoringFor($this->seasonWithScoring()));

        $this->assertSame([], $result['final']);
        $this->assertSame([], $result['snapshots']);
    }
}
