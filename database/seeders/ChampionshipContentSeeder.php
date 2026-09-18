<?php

namespace Database\Seeders;

use App\Models\Award;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Race;
use App\Models\RaceAward;
use App\Models\RaceEntry;
use App\Models\RacePenalty;
use App\Models\ScoringPoint;
use App\Models\Season;
use App\Models\SeasonRecord;
use App\Models\SeasonScoring;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Mirrors supabase/migrations/202609170004_seed_championship_content.sql —
 * the 2026 season content for group "Karting Crew": 5 races, standings,
 * penalties, awards and records. Builds on DemoDataSeeder.
 */
class ChampionshipContentSeeder extends Seeder
{
    public function run(): void
    {
        $group = Group::where('name', 'Karting Crew')->firstOrFail();

        /** @var array<int, Driver> $driverByMockId */
        $driverByMockId = Driver::all()->keyBy(fn (Driver $d) => strtolower($d->nickname));

        $drv = function (string $token) use ($driverByMockId): int {
            $nickname = match ($token) {
                'drv_1' => 'BD',
                'drv_2' => 'AR',
                'drv_3' => 'UK',
                'drv_4' => 'AJ',
                'drv_5' => 'ZF',
                'drv_6' => 'HA',
                'drv_7' => 'DS',
                'drv_8' => 'OT',
                default => throw new \InvalidArgumentException("Unknown driver: {$token}"),
            };

            return $driverByMockId->get(strtolower($nickname))->id;
        };

        // -------------------------------------------------------------------
        // Season
        // -------------------------------------------------------------------
        $season = Season::firstOrCreate(
            ['name' => 'Crew Championship 2026'],
            [
                'group_id' => $group->id,
                'status' => 'active',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'created_at' => '2025-12-01',
                'updated_at' => '2025-12-01',
            ]
        );

        SeasonScoring::firstOrCreate(
            ['season_id' => $season->id],
            [
                'mode' => 'automatic',
                'pole_position_points' => 1,
                'fastest_lap_points' => 0,
                'participation_points' => 0,
                'dnf_points' => 0,
                'dns_points' => 0,
                'penalty_adjustment_enabled' => false,
            ]
        );

        $pointsByPosition = [1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 4];
        foreach ($pointsByPosition as $position => $points) {
            ScoringPoint::firstOrCreate(
                ['season_id' => $season->id, 'position' => $position],
                ['points' => $points]
            );
        }

        // -------------------------------------------------------------------
        // Races (rounds 1-3 completed, rounds 4-5 draft)
        // -------------------------------------------------------------------
        $races = [
            ['key' => 'rc_8', 'name' => 'Opening Sprint',    'venue_name' => 'Nashik Karting Arena', 'date' => '2026-03-14', 'start_time' => '16:00', 'format' => 'sprint',  'status' => 'completed', 'organizer' => 'drv_1', 'rules' => 'Season opener. Rolling start after one formation lap.', 'created_at' => '2026-02-25', 'updated_at' => '2026-03-14'],
            ['key' => 'rc_9', 'name' => 'Grand Prix Sprint', 'venue_name' => 'Pune Race Zone',      'date' => '2026-05-09', 'start_time' => '15:30', 'format' => 'feature', 'status' => 'completed', 'organizer' => 'drv_5', 'rules' => 'Feature-length run. One score-counting lap for the grid.', 'created_at' => '2026-04-20', 'updated_at' => '2026-05-09'],
            ['key' => 'rc_10','name' => 'Monsoon Special',   'venue_name' => 'Nashik Karting Arena', 'date' => '2026-07-18', 'start_time' => '16:30', 'format' => 'sprint',  'status' => 'completed', 'organizer' => 'drv_1', 'rules' => 'Run under full wet conditions if rain holds. No red-flag restarts for drizzle.', 'created_at' => '2026-07-01', 'updated_at' => '2026-07-18'],
            ['key' => 'rc_11','name' => 'Autumn Sprint',     'venue_name' => 'Pune Race Zone',      'date' => '2026-10-03', 'start_time' => '15:00', 'format' => 'sprint',  'status' => 'draft',     'organizer' => 'drv_1', 'rules' => '', 'created_at' => '2026-09-10', 'updated_at' => '2026-09-10'],
            ['key' => 'rc_12','name' => 'Champions Finale',  'venue_name' => 'Nashik Karting Arena', 'date' => '2026-11-21', 'start_time' => '16:00', 'format' => 'feature', 'status' => 'draft',     'organizer' => 'drv_5', 'rules' => '', 'created_at' => '2026-09-10', 'updated_at' => '2026-09-10'],
        ];

        $raceModel = [];
        foreach ($races as $r) {
            $raceModel[$r['key']] = Race::updateOrCreate(
                ['name' => $r['name'], 'group_id' => $group->id],
                [
                    'date' => $r['date'],
                    'venue_name' => $r['venue_name'],
                    'start_time' => $r['start_time'],
                    'format' => $r['format'],
                    'status' => $r['status'],
                    'organizer_id' => $drv($r['organizer']),
                    'qualifying_lap_count' => 1,
                    'rules' => $r['rules'],
                    'created_at' => $r['created_at'],
                    'updated_at' => $r['updated_at'],
                ]
            );
        }

        foreach ($races as $i => $r) {
            $season->races()->syncWithoutDetaching([$raceModel[$r['key']]->id => ['round_number' => $i + 1]]);
        }

        // -------------------------------------------------------------------
        // Race entries (rc_8: 8, rc_9: 8, rc_10: 7)
        // race_id, driver, kart, status, confirmed, ready, grid, penalty, quali_ms, quali_status, finish, penalty_total, notes
        // -------------------------------------------------------------------
        $gc = 'completed';
        $entries = [
            // Opening Sprint (rc_8)
            ['rc_8', 'drv_1', 7,  'finished', true, true, 1, 0, 41750, $gc, 1, 0, ''],
            ['rc_8', 'drv_5', 9,  'finished', true, true, 2, 0, 41800, $gc, 2, 0, ''],
            ['rc_8', 'drv_2', 14, 'finished', true, true, 3, 0, 41960, $gc, 3, 0, ''],
            ['rc_8', 'drv_8', 19, 'finished', true, true, 4, 0, 42290, $gc, 4, 0, ''],
            ['rc_8', 'drv_7', 5,  'finished', true, true, 5, 0, 42030, $gc, 5, 0, ''],
            ['rc_8', 'drv_3', 3,  'finished', true, true, 6, 0, 42410, $gc, 6, 0, ''],
            ['rc_8', 'drv_4', 22, 'finished', true, true, 7, 0, 42680, $gc, 7, 0, ''],
            ['rc_8', 'drv_6', 11, 'finished', true, true, 8, 0, 42910, $gc, 8, 0, ''],
            // Grand Prix Sprint (rc_9)
            ['rc_9', 'drv_5', 9,  'finished', true, true, 1, 0, 41720, $gc, 1, 0, ''],
            ['rc_9', 'drv_1', 7,  'finished', true, true, 2, 0, 41790, $gc, 2, 0, ''],
            ['rc_9', 'drv_7', 5,  'finished', true, true, 3, 0, 42060, $gc, 3, 0, ''],
            ['rc_9', 'drv_2', 14, 'finished', true, true, 4, 0, 41940, $gc, 4, 0, ''],
            ['rc_9', 'drv_3', 3,  'finished', true, true, 5, 0, 42380, $gc, 5, 0, ''],
            ['rc_9', 'drv_8', 19, 'finished', true, true, 6, 0, 42450, $gc, 6, 0, ''],
            ['rc_9', 'drv_4', 22, 'dnf',      true, true, 7, 0, 42650, $gc, null, 0, 'Spin at turn 1'],
            ['rc_9', 'drv_6', 11, 'dns',      true, false, 8, 0, 42880, $gc, null, 0, 'Starter motor failure'],
            // Monsoon Special (rc_10)
            ['rc_10','drv_1', 7,  'finished', true, true, 1, 0, 41770, $gc, 1, 0, ''],
            ['rc_10','drv_5', 9,  'finished', true, true, 2, 0, 41820, $gc, 2, 0, ''],
            ['rc_10','drv_2', 14, 'finished', true, true, 3, 0, 42040, $gc, 3, 0, ''],
            ['rc_10','drv_7', 5,  'finished', true, true, 4, 0, 41990, $gc, 4, 0, ''],
            ['rc_10','drv_8', 19, 'finished', true, true, 5, 0, 42330, $gc, 5, 0, ''],
            ['rc_10','drv_3', 3,  'finished', true, true, 6, 0, 42480, $gc, 6, 0, ''],
            ['rc_10','drv_6', 11, 'retired',  true, true, 7, 0, 42850, $gc, null, 0, 'Radiator stone strike'],
        ];

        foreach ($entries as $e) {
            RaceEntry::firstOrCreate(
                ['race_id' => $raceModel[$e[0]]->id, 'driver_id' => $drv($e[1])],
                [
                    'kart_number' => $e[2],
                    'status' => $e[3],
                    'confirmed' => $e[4],
                    'ready' => $e[5],
                    'grid_position' => $e[6],
                    'grid_penalty_seconds' => $e[7],
                    'qualifying_time_ms' => $e[8],
                    'qualifying_status' => $e[9],
                    'finish_position' => $e[10],
                    'penalty_total_seconds' => $e[11],
                    'notes' => $e[12],
                ]
            );
        }

        // -------------------------------------------------------------------
        // Penalties (informational — never reorder finishes)
        // -------------------------------------------------------------------
        RacePenalty::firstOrCreate(
            ['race_id' => $raceModel['rc_9']->id, 'driver_id' => $drv('drv_3')],
            [
                'seconds' => 5, 'reason' => 'Contact under braking',
                'issued_by' => $drv('drv_5'), 'status' => 'issued',
                'created_at' => '2026-05-09', 'updated_at' => '2026-05-09',
            ]
        );
        RacePenalty::firstOrCreate(
            ['race_id' => $raceModel['rc_10']->id, 'driver_id' => $drv('drv_3')],
            [
                'seconds' => 5, 'reason' => 'Track limits at turn 9',
                'issued_by' => $drv('drv_1'), 'status' => 'issued',
                'created_at' => '2026-07-18', 'updated_at' => '2026-07-18',
            ]
        );

        // -------------------------------------------------------------------
        // Race awards
        // -------------------------------------------------------------------
        $raceAwards = [
            ['rc_8',  'drv_1', null, 'drv_8', '2026-03-14'],
            ['rc_9',  'drv_5', null, 'drv_7', '2026-05-09'],
            ['rc_10', 'drv_1', null, 'drv_3', '2026-07-18'],
        ];
        foreach ($raceAwards as $a) {
            RaceAward::firstOrCreate(
                ['race_id' => $raceModel[$a[0]]->id],
                [
                    'driver_of_race_id' => $drv($a[1]),
                    'most_improved_id' => $a[2] ? $drv($a[2]) : null,
                    'cleanest_driver_id' => $drv($a[3]),
                    'updated_at' => $a[4],
                ]
            );
        }

        // -------------------------------------------------------------------
        // Season awards
        // -------------------------------------------------------------------
        $teamLeader = Team::where('name', 'Apex Racing')->firstOrFail();
        $seasonAwards = [
            ['aw_7',  'team_champion',     'Team Leader',              'Leading team after round 3',             null,         $teamLeader->id, null,     '118 pts',  '2026-07-19'],
            ['aw_8',  'championship_winner','Leader After Round 3',    'Current standings leader',              'drv_1',      null,            null,     '70 pts',   '2026-07-19'],
            ['aw_9',  'most_wins',         'Most Wins (3 rounds)',    'Most race wins so far',                  'drv_1',      null,            null,     '2 wins',   '2026-07-19'],
            ['aw_10', 'most_poles',        'Most Poles (3 rounds)',   'Most pole positions so far',             'drv_1',      null,            null,     '2 poles',  '2026-07-19'],
            ['aw_11', 'cleanest_season',   'Cleanest Season (3 rounds)','Most penalty-free races so far',       'drv_1',      null,            null,     '3 races',  '2026-07-19'],
        ];
        foreach ($seasonAwards as $a) {
            Award::firstOrCreate(
                ['season_id' => $season->id, 'type' => $a[1]],
                [
                    'label' => $a[2],
                    'description' => $a[3],
                    'driver_id' => $a[4] ? $drv($a[4]) : null,
                    'team_id' => $a[5],
                    'race_id' => $a[6],
                    'value' => $a[7],
                    'created_at' => $a[8],
                    'updated_at' => $a[8],
                ]
            );
        }

        // -------------------------------------------------------------------
        // Season records
        // -------------------------------------------------------------------
        $seasonRecords = [
            ['rec_4', 'Best Qualifying', '41.720 s', 'drv_5', '2026-07-19'],
            ['rec_5', 'Most Starts',     '3 / 3',    'drv_1', '2026-07-19'],
            ['rec_6', 'Most Wins',       '2',        'drv_1', '2026-07-19'],
        ];
        foreach ($seasonRecords as $r) {
            SeasonRecord::firstOrCreate(
                ['season_id' => $season->id, 'label' => $r[1]],
                [
                    'value' => $r[2],
                    'driver_id' => $drv($r[3]),
                    'created_at' => $r[4],
                    'updated_at' => $r[4],
                ]
            );
        }
    }
}