<?php

namespace App\Support;

use App\Models\RaceEntry;

/**
 * Deterministic race helpers ported from src/lib/race-utils.ts.
 * Operates on plain arrays so the logic stays testable and model-free.
 */
final class RaceUtils
{
    public static function formatLapTime(?int $ms): string
    {
        if ($ms === null || $ms < 0) {
            return '—';
        }
        $totalSeconds = intdiv($ms, 1000);
        $minutes = intdiv($totalSeconds, 60);
        $seconds = $totalSeconds % 60;
        $millis = round($ms % 1000);
        $pad = static fn (int $n, int $len = 2): string => str_pad((string) $n, $len, '0', STR_PAD_LEFT);

        return $minutes > 0
            ? "{$minutes}:{$pad($seconds)}.{$pad((int) $millis, 3)}"
            : "{$seconds}.{$pad((int) $millis, 3)}";
    }

    public static function formatStopwatchMs(int $ms): string
    {
        $totalSeconds = intdiv($ms, 1000);
        $minutes = intdiv($totalSeconds, 60);
        $seconds = $totalSeconds % 60;
        $millis = round($ms % 1000);
        $pad = static fn (int $n, int $len = 2): string => str_pad((string) $n, $len, '0', STR_PAD_LEFT);

        return ($minutes > 0 ? "{$minutes}:" : '')."{$pad($seconds)}.{$pad((int) $millis, 3)}";
    }

    public static function formatPenaltySeconds(int $seconds): string
    {
        return "+{$seconds}s";
    }

    public static function formatDateTime(string $date, string $time): string
    {
        return "{$date} at {$time}";
    }

    public static function parseLapTime(string $input): ?int
    {
        $s = trim($input);
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ':')) {
            $parts = explode(':', $s, 2);
            $minutes = (float) $parts[0];
            if (! is_finite($minutes)) {
                return null;
            }
            $rest = explode('.', $parts[1]);
            if (count($rest) < 2) {
                return null;
            }
            [$secRaw, $msRaw] = $rest;
            if ($secRaw === '') {
                return null;
            }
            $secs = (float) $secRaw;
            $ms = $msRaw !== '' ? (float) str_pad(substr($msRaw, 0, 3), 3, '0', STR_PAD_RIGHT) : 0.0;
            if (! is_finite($secs) || ! is_finite($ms)) {
                return null;
            }
            $total = (int) ($minutes * 60000 + $secs * 1000 + $ms);

            return $total > 0 ? $total : null;
        }
        $parts = explode('.', $s);
        if (count($parts) > 2) {
            return null;
        }
        if ($parts[0] === '') {
            return null;
        }
        $secs = (float) $parts[0];
        if (! is_finite($secs)) {
            return null;
        }
        $ms = isset($parts[1]) && $parts[1] !== '' ? (float) str_pad(substr($parts[1], 0, 3), 3, '0', STR_PAD_RIGHT) : 0.0;
        $total = (int) ($secs * 1000 + $ms);

        return $total > 0 ? $total : null;
    }

    public static function isValidQualifyingTime(array $entry): bool
    {
        return $entry['qualifying_time_ms'] !== null
            && in_array($entry['qualifying_status'], ['completed', 'manually_corrected'], true);
    }

    /**
     * Race entries sorted as the source qualifying table lists them:
     * official times (asc), then invalid times (asc), then no-time (confirmed first).
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    public static function qualifyingRows(array $entries): array
    {
        $withTime = array_values(array_filter($entries, static fn (array $e): bool => self::isValidQualifyingTime($e)));
        usort($withTime, static fn (array $a, array $b): int => ($a['qualifying_time_ms'] ?? 0) <=> ($b['qualifying_time_ms'] ?? 0));

        $invalid = array_values(array_filter(
            $entries,
            static fn (array $e): bool => $e['qualifying_status'] === 'invalid' && $e['qualifying_time_ms'] !== null
        ));
        usort($invalid, static fn (array $a, array $b): int => ($a['qualifying_time_ms'] ?? 0) <=> ($b['qualifying_time_ms'] ?? 0));

        $noTime = array_values(array_filter($entries, static fn (array $e): bool => $e['qualifying_time_ms'] === null));
        usort($noTime, static fn (array $a, array $b): int => ($b['confirmed'] ? 1 : 0) <=> ($a['confirmed'] ? 1 : 0));

        return array_merge($withTime, $invalid, $noTime);
    }

    public static function poleTime(array $entries): ?int
    {
        $valid = array_values(array_filter($entries, static fn (array $e): bool => self::isValidQualifyingTime($e)));
        if ($valid === []) {
            return null;
        }
        $best = null;
        foreach ($valid as $e) {
            $best = $best === null ? $e['qualifying_time_ms'] : min($best, $e['qualifying_time_ms']);
        }

        return $best;
    }

    public static function poleDiffMs(?int $timeMs, ?int $pole): ?int
    {
        return $timeMs === null || $pole === null ? null : $timeMs - $pole;
    }

    /**
     * Grid rows following computeGridRows: adjusted (time + grid penalty) ordering,
     * manual grid_position overrides the displayed position.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    public static function computeGridRows(array $entries): array
    {
        $base = self::qualifyingRows($entries);
        $adjusted = $base;
        usort($adjusted, static function (array $a, array $b): int {
            $ka = self::isValidQualifyingTime($a)
                ? ($a['qualifying_time_ms'] ?? 0) + ($a['grid_penalty_seconds'] ?? 0) * 1000
                : PHP_INT_MAX;
            $kb = self::isValidQualifyingTime($b)
                ? ($b['qualifying_time_ms'] ?? 0) + ($b['grid_penalty_seconds'] ?? 0) * 1000
                : PHP_INT_MAX;
            if ($ka === $kb) {
                return 0;
            }

            return $ka < $kb ? -1 : 1;
        });

        return array_map(static function (array $d) use ($base, $adjusted): array {
            $manual = $d['grid_position'];
            $indexAdjusted = self::indexOf($adjusted, $d['driver_id']);
            $display = $manual ?? ($indexAdjusted === null ? null : $indexAdjusted + 1);
            $indexBase = self::indexOf($base, $d['driver_id']);

            return [
                'driver_id' => $d['driver_id'],
                'kart_number' => $d['kart_number'],
                'qualifying_time_ms' => $d['qualifying_time_ms'],
                'qualifying_status' => $d['qualifying_status'],
                'ready' => $d['ready'],
                'original_position' => $indexBase === null ? null : $indexBase + 1,
                'grid_position' => $display,
                'grid_penalty_seconds' => $d['grid_penalty_seconds'],
            ];
        }, $base);
    }

    /**
     * Official results ordering (buildFinalResults).
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    public static function buildFinalResults(array $entries): array
    {
        $rank = [
            'finished' => 0, 'dnf' => 1, 'retired' => 2, 'withdrawn' => 3, 'dns' => 4,
            'racing' => 5, 'ready' => 5, 'confirmed' => 5, 'invited' => 5, 'declined' => 5,
        ];
        $rows = array_map(static fn (array $d): array => ['d' => $d, 'r' => $rank[$d['status']] ?? 9], $entries);
        usort($rows, static function (array $a, array $b): int {
            $rankCmp = $a['r'] <=> $b['r'];
            if ($rankCmp !== 0) {
                return $rankCmp;
            }
            $fa = $a['d']['finish_position'] ?? 999;
            $fb = $b['d']['finish_position'] ?? 999;
            $finishCmp = $fa <=> $fb;
            if ($finishCmp !== 0) {
                return $finishCmp;
            }

            return ($a['d']['grid_position'] ?? 999) <=> ($b['d']['grid_position'] ?? 999);
        });

        $finishCounter = 1;
        $mapped = [];
        foreach ($rows as $row) {
            $d = $row['d'];
            $isFinished = $d['status'] === 'finished';
            $pos = $isFinished ? ($d['finish_position'] ?? $finishCounter) : null;
            if ($isFinished && $d['finish_position'] === null) {
                $finishCounter += 1;
            }
            $mapped[] = [
                'driver_id' => $d['driver_id'],
                'finish_position' => $pos,
                'status' => $d['status'],
                'qualifying_time_ms' => $d['qualifying_time_ms'],
                'penalty_total_seconds' => $d['penalty_total_seconds'],
                'notes' => $d['notes'] ?? '',
            ];
        }

        return $mapped;
    }

    /**
     * @param  list<array<string, mixed>>  $haystack
     */
    private static function indexOf(array $haystack, int $driverId): ?int
    {
        foreach ($haystack as $i => $item) {
            if (($item['driver_id'] ?? $item['id'] ?? null) === $driverId) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Normalize a RaceEntry model into the plain array shape the helpers expect.
     *
     * @return array<string, mixed>
     */
    public static function entryToArray(RaceEntry $entry): array
    {
        return [
            'driver_id' => $entry->driver_id,
            'kart_number' => $entry->kart_number,
            'status' => $entry->status?->value ?? $entry->getRawOriginal('status'),
            'confirmed' => (bool) $entry->confirmed,
            'ready' => (bool) $entry->ready,
            'grid_position' => $entry->grid_position,
            'grid_penalty_seconds' => $entry->grid_penalty_seconds,
            'qualifying_time_ms' => $entry->qualifying_time_ms,
            'qualifying_status' => $entry->qualifying_status?->value ?? $entry->getRawOriginal('qualifying_status'),
            'finish_position' => $entry->finish_position,
            'penalty_total_seconds' => $entry->penalty_total_seconds,
            'notes' => $entry->notes ?? '',
        ];
    }

    /**
     * @param  iterable<RaceEntry>  $entries
     * @return list<array<string, mixed>>
     */
    public static function entriesToArrays(iterable $entries): array
    {
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = self::entryToArray($entry);
        }

        return $rows;
    }
}
