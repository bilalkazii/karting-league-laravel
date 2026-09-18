<?php

namespace App\Support;

use App\Enums\RaceStatus;
use App\Enums\ScoringMode;
use App\Models\Race;
use App\Models\RaceEntry;
use App\Models\RacePenalty;
use App\Models\Season;
use App\Models\SeasonScoring;
use Illuminate\Support\Collection;

/**
 * Deterministic points + standings computation, ported from
 * src/lib/championship-utils.ts. Standings are computed on demand from
 * persisted race entries/penalties — never stored.
 */
final class StandingsService
{
    public const CLASSIFICATION_RANK = [
        'finished' => 0,
        'dnf' => 1,
        'retired' => 2,
        'withdrawn' => 3,
        'dns' => 4,
        'racing' => 9,
        'ready' => 9,
        'confirmed' => 9,
        'invited' => 9,
        'declined' => 9,
    ];

    public const DEFAULT_POINTS_BY_POSITION = [1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 4];

    /**
     * @param  iterable<RaceEntry>  $entries
     * @return list<array<string, mixed>>
     */
    public static function raceEntries(iterable $entries): array
    {
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                'driver_id' => $entry->driver_id,
                'status' => $entry->status?->value ?? $entry->getRawOriginal('status'),
                'finish_position' => $entry->finish_position,
                'grid_position' => $entry->grid_position,
                'qualifying_time_ms' => $entry->qualifying_time_ms,
                'qualifying_status' => $entry->qualifying_status?->value ?? $entry->getRawOriginal('qualifying_status'),
                'penalty_total_seconds' => $entry->penalty_total_seconds,
            ];
        }

        return $rows;
    }

    public static function isClassified(string $status): bool
    {
        return (self::CLASSIFICATION_RANK[$status] ?? 9) <= 4;
    }

    public static function validQualifyingTime(array $entry): bool
    {
        return $entry['qualifying_time_ms'] !== null
            && in_array($entry['qualifying_status'], ['completed', 'manually_corrected'], true);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    public static function racePoleDriverId(array $entries): ?int
    {
        $valid = array_values(array_filter($entries, static fn (array $e): bool => self::validQualifyingTime($e)));
        if ($valid === []) {
            return null;
        }
        $best = $valid[0];
        foreach ($valid as $e) {
            if (($e['qualifying_time_ms'] ?? 0) < ($best['qualifying_time_ms'] ?? 0)) {
                $best = $e;
            }
        }

        return $best['driver_id'];
    }

    /**
     * Points for one race, mirroring computeRacePoints.
     *
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, mixed>  $scoring  ['points_by_position', 'mode', 'pole_position_points', 'participation_points', 'dnf_points', 'dns_points', ...]
     * @return array{entries: list<array<string, mixed>>, pole_driver_id: int|null}
     */
    public static function computeRacePoints(array $entries, array $scoring): array
    {
        $classified = array_values(array_filter($entries, static fn (array $e): bool => self::isClassified($e['status'])));
        usort($classified, static fn (array $a, array $b): int => self::classificationCompare($a, $b));
        $poleId = self::racePoleDriverId($entries);
        $pointsByPosition = $scoring['points_by_position'] ?? self::DEFAULT_POINTS_BY_POSITION;

        $result = [];
        foreach ($classified as $d) {
            $finished = $d['status'] === 'finished';
            $position = $finished ? $d['finish_position'] : null;
            $basePoints = $finished
                ? ($position !== null ? ($pointsByPosition[$position] ?? 0) : 0)
                : ($d['status'] === 'dns' ? ($scoring['dns_points'] ?? 0) : ($scoring['dnf_points'] ?? 0));
            $polePoints = $poleId === $d['driver_id'] ? ($scoring['pole_position_points'] ?? 0) : 0;
            $result[] = [
                'driver_id' => $d['driver_id'],
                'finish_position' => $position,
                'status' => $d['status'],
                'base_points' => $basePoints,
                'pole_points' => $polePoints,
                'points' => $basePoints + $polePoints + ($scoring['participation_points'] ?? 0),
                'clean' => $finished,
                'qualifying_time_ms' => $d['qualifying_time_ms'],
                'qualifying_status' => $d['qualifying_status'],
            ];
        }

        return ['entries' => $result, 'pole_driver_id' => $poleId];
    }

    /**
     * Standings for a season over its completed races (date order), computing a
     * snapshot after each completed race. Mirrors computeStandings.
     *
     * @param  iterable<Race>  $races
     */
    public static function computeStandings(iterable $races, array $scoring): array
    {
        $completed = collect($races)
            ->filter(fn (Race $r) => $r->status === RaceStatus::Completed)
            ->sortBy(static fn (Race $r) => $r->date?->format('Y-m-d').' '.$r->start_time)
            ->values();

        $snapshots = [];
        $prev = null;

        foreach ($completed as $index => $race) {
            $slice = $completed->slice(0, $index + 1);
            $acc = self::accumulate($slice, $scoring);
            $standings = self::toStandings($acc, $prev);
            $snapshots[] = ['race' => $race, 'standings' => $standings];
            $prev = collect($standings)->mapWithKeys(fn (array $s) => [$s['driver_id'] => $s['position']]);
        }

        $final = $snapshots !== [] ? end($snapshots)['standings'] : [];

        return ['final' => $final, 'snapshots' => $snapshots];
    }

    /**
     * @param  Collection<int, Race>  $races
     * @param  array<string, mixed>  $scoring
     * @return array<int, array<string, mixed>>
     */
    private static function accumulate(Collection $races, array $scoring): array
    {
        $acc = [];

        foreach ($races as $race) {
            $entries = self::raceEntries($race->entries);
            $activePenalties = $race->penalties->filter(
                fn (RacePenalty $p) => (string) ($p->status?->value ?? $p->getRawOriginal('status')) !== 'cancelled'
            );

            $byTime = [];
            foreach ($activePenalties as $p) {
                $byTime[$p->driver_id] = ($byTime[$p->driver_id] ?? 0) + $p->seconds;
            }

            $points = self::computeRacePoints($entries, $scoring);

            foreach ($points['entries'] as $e) {
                $driverId = $e['driver_id'];
                $a = $acc[$driverId] ??= [
                    'races_entered' => 0,
                    'races_completed' => 0,
                    'wins' => 0,
                    'podiums' => 0,
                    'poles' => 0,
                    'dnfs' => 0,
                    'dns' => 0,
                    'retirements' => 0,
                    'withdrawals' => 0,
                    'penalties' => 0,
                    'penalty_seconds' => 0,
                    'points' => 0,
                    'best_quali' => null,
                    'clean_races' => 0,
                    'races' => 0,
                ];

                $a['races_entered'] += 1;
                $a['races'] += 1;
                $a['points'] += $e['points'];
                if ($e['pole_points'] > 0) {
                    $a['poles'] += 1;
                }
                if ($e['status'] === 'finished') {
                    $a['races_completed'] += 1;
                    if ($e['finish_position'] === 1) {
                        $a['wins'] += 1;
                    }
                    if ($e['finish_position'] !== null && $e['finish_position'] <= 3) {
                        $a['podiums'] += 1;
                    }
                }
                if ($e['status'] === 'dnf') {
                    $a['dnfs'] += 1;
                }
                if ($e['status'] === 'dns') {
                    $a['dns'] += 1;
                }
                if ($e['status'] === 'retired') {
                    $a['retirements'] += 1;
                }
                if ($e['status'] === 'withdrawn') {
                    $a['withdrawals'] += 1;
                }
                $penSeconds = $byTime[$driverId] ?? 0;
                if ($penSeconds > 0) {
                    $a['penalties'] += 1;
                }
                $a['penalty_seconds'] += $penSeconds;
                if ($e['clean'] && $penSeconds === 0) {
                    $a['clean_races'] += 1;
                }
                if (self::validQualifyingTime($e)) {
                    $t = $e['qualifying_time_ms'];
                    $a['best_quali'] = $a['best_quali'] === null ? $t : min($a['best_quali'], $t);
                }
                $acc[$driverId] = $a;
            }
        }

        return $acc;
    }

    /**
     * @param  array<int, array<string, mixed>>  $acc
     * @param  Collection|null  $prev  map driverId => position
     * @return list<array<string, mixed>>
     */
    private static function toStandings(array $acc, ?Collection $prev): array
    {
        $rows = [];
        foreach ($acc as $driverId => $a) {
            $rows[] = [
                'position' => 0,
                'driver_id' => (int) $driverId,
                'points' => $a['points'],
                'wins' => $a['wins'],
                'podiums' => $a['podiums'],
                'poles' => $a['poles'],
                'races_entered' => $a['races_entered'],
                'races' => $a['races_entered'],
                'races_completed' => $a['races_completed'],
                'dnfs' => $a['dnfs'],
                'dns' => $a['dns'],
                'retirements' => $a['retirements'],
                'withdrawals' => $a['withdrawals'],
                'penalties' => $a['penalties'],
                'penalty_seconds' => $a['penalty_seconds'],
                'clean_races' => $a['clean_races'],
                'qualifying_best' => $a['best_quali'],
                'points_gap' => 0,
                'previous_position' => null,
                'trend' => 'same',
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $bestA = $a['qualifying_best'] ?? null;
            $bestB = $b['qualifying_best'] ?? null;

            return ($b['points'] <=> $a['points'])
                ?: ($b['wins'] <=> $a['wins'])
                ?: ($b['podiums'] <=> $a['podiums'])
                ?: ($b['poles'] <=> $a['poles'])
                ?: (($bestA ?? PHP_INT_MAX) <=> ($bestB ?? PHP_INT_MAX))
                ?: ($a['driver_id'] <=> $b['driver_id']);
        });

        $leaderPoints = $rows[0]['points'] ?? 0;
        foreach ($rows as $i => &$row) {
            $row['position'] = $i + 1;
            $row['points_gap'] = $leaderPoints - $row['points'];
            $prevPos = $prev?->get($row['driver_id']);
            $row['previous_position'] = $prevPos ?? null;
            $row['trend'] = $prevPos === null
                ? 'same'
                : ($prevPos > $row['position'] ? 'up' : ($prevPos < $row['position'] ? 'down' : 'same'));
        }
        unset($row);

        return $rows;
    }

    /**
     * Sort comparator used by computeRacePoints (rank, finish, grid).
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function classificationCompare(array $a, array $b): int
    {
        $rankA = self::CLASSIFICATION_RANK[$a['status']] ?? 9;
        $rankB = self::CLASSIFICATION_RANK[$b['status']] ?? 9;
        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }
        $fa = $a['finish_position'] ?? 999;
        $fb = $b['finish_position'] ?? 999;
        if ($fa !== $fb) {
            return $fa <=> $fb;
        }
        $ga = $a['grid_position'] ?? 999;
        $gb = $b['grid_position'] ?? 999;

        return $ga <=> $gb;
    }

    /**
     * Scalar scoring config derived from a season's season_scoring + scoring_points.
     *
     * @return array<string, mixed>
     */
    public static function scoringFor(Season $season): array
    {
        $scoring = $season->scoring;
        $points = collect($season->scoringPoints)->pluck('points', 'position');

        $mode = ScoringMode::Automatic->value;
        if ($scoring instanceof SeasonScoring) {
            $mode = $scoring->mode?->value ?? ScoringMode::Automatic->value;
        }

        return [
            'mode' => $mode,
            'points_by_position' => $points->all() ?: self::DEFAULT_POINTS_BY_POSITION,
            'pole_position_points' => $scoring?->pole_position_points ?? 1,
            'fastest_lap_points' => $scoring?->fastest_lap_points ?? 0,
            'participation_points' => $scoring?->participation_points ?? 0,
            'dnf_points' => $scoring?->dnf_points ?? 0,
            'dns_points' => $scoring?->dns_points ?? 0,
            'penalty_adjustment_enabled' => (bool) ($scoring?->penalty_adjustment_enabled ?? false),
        ];
    }
}
