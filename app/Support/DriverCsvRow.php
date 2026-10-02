<?php

namespace App\Support;

/**
 * A single reviewable line from an uploaded driver CSV.
 *
 * `matchStatus` is deliberately explicit so the preview can tell a person what
 * will happen rather than what might happen:
 *
 *  - exact      the name matches an existing driver exactly
 *  - ambiguous  several drivers are equally plausible, needs a human
 *  - uncertain  one driver is similar but not an exact match, needs a human
 *  - duplicate  the same name appears more than once in the file
 *  - unmatched  no existing driver looks like this name
 *  - invalid    the row cannot be imported at all
 */
final class DriverCsvRow
{
    public const EXACT = 'exact';

    public const AMBIGUOUS = 'ambiguous';

    public const UNCERTAIN = 'uncertain';

    public const DUPLICATE = 'duplicate';

    public const UNMATCHED = 'unmatched';

    public const INVALID = 'invalid';

    /** Below this the name is treated as "no match at all". */
    public const MATCH_FLOOR = 0.6;

    /**
     * @param  list<array{id: int, name: string, score: float}>  $candidates
     * @param  list<string>  $issues
     */
    public function __construct(
        public readonly int $line,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $eventLabel,
        public readonly ?string $finishPosition,
        public readonly ?string $pointsDisplayed,
        public readonly string $matchStatus,
        public readonly ?int $matchedDriverId,
        public readonly ?string $matchedDriverName,
        public readonly ?float $matchScore,
        public readonly array $candidates,
        public readonly array $issues,
    ) {}

    /** True when the row needs a person to decide before it can be applied. */
    public function needsReview(): bool
    {
        return in_array($this->matchStatus, [self::AMBIGUOUS, self::UNCERTAIN], true);
    }

    public function isImportable(): bool
    {
        return ! in_array($this->matchStatus, [self::INVALID, self::AMBIGUOUS, self::UNCERTAIN, self::DUPLICATE], true);
    }

    /** A matched existing driver is a link, not a write. */
    public function createsDriver(): bool
    {
        return $this->isImportable() && $this->matchedDriverId === null;
    }

    /**
     * @param  list<array{id: int, name: string}>  $existing
     */
    public static function fromArray(array $row, array $existing): self
    {
        $name = trim((string) ($row['name'] ?? ''));
        $email = self::nullable($row['email'] ?? null);
        $phone = self::nullable($row['phone'] ?? null);
        $eventLabel = self::nullable($row['event_label'] ?? null);
        $finishPosition = self::nullable($row['finish_position'] ?? null);
        $points = self::nullable($row['points_displayed'] ?? null);
        $line = (int) ($row['_line'] ?? 0);

        $issues = [];

        // Data problems that make a row unusable regardless of how well the
        // name matches. These are collected first because a malformed email or
        // a finish position of "0" cannot be fixed by choosing a different
        // match, so the row must never reach the "create" action.
        $fatal = [];

        if ($name === '') {
            $fatal[] = 'No driver name in this row.';
        }
        if ($finishPosition !== null && (! ctype_digit($finishPosition) || (int) $finishPosition < 1)) {
            $fatal[] = 'Finish position must be a whole number of 1 or more.';
        }
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fatal[] = 'Email address is not a valid address.';
        }

        $issues = $fatal;

        $candidates = self::rankCandidates($name, $existing);

        if ($fatal !== []) {
            return new self($line, $name, $email, $phone, $eventLabel, $finishPosition, $points, self::INVALID, null, null, null, $candidates, $issues);
        }

        $top = $candidates[0] ?? null;
        $runnerUp = $candidates[1] ?? null;

        // Two candidates within 0.1 of each other are too close to separate
        // automatically, even if the top one is a perfect name match.
        $ambiguous = $top !== null
            && $runnerUp !== null
            && ($top['score'] - $runnerUp['score']) < 0.1;

        if ($top !== null && $top['score'] >= 1.0 && ! $ambiguous) {
            return new self($line, $name, $email, $phone, $eventLabel, $finishPosition, $points, self::EXACT, $top['id'], $top['name'], $top['score'], $candidates, $issues);
        }

        if ($ambiguous) {
            $names = collect($candidates)
                ->filter(fn (array $c): bool => $c['score'] >= $top['score'] - 0.1)
                ->map(fn (array $c): string => (string) $c['name'])
                ->implode(', ');

            $issues[] = "This name could be any of: {$names}.";

            return new self($line, $name, $email, $phone, $eventLabel, $finishPosition, $points, self::AMBIGUOUS, null, null, $top['score'], $candidates, $issues);
        }

        if ($top !== null && $top['score'] >= self::MATCH_FLOOR) {
            $issues[] = "Closest existing driver is {$top['name']} (".(int) round($top['score'] * 100).'% match). Confirm before importing.';

            return new self($line, $name, $email, $phone, $eventLabel, $finishPosition, $points, self::UNCERTAIN, $top['id'], $top['name'], $top['score'], $candidates, $issues);
        }

        return new self($line, $name, $email, $phone, $eventLabel, $finishPosition, $points, self::UNMATCHED, null, null, null, $candidates, $issues);
    }

    /**
     * Flag rows that repeat inside the file, so one person is not turned into
     * two drivers by a duplicated export.
     *
     * The key includes the event label when the file has one: the same driver
     * legitimately appears once per event in a per-round export, and that is not
     * a duplicate. When the file carries no event column, a repeated name is
     * treated as a duplicate because there is nothing to tell the rows apart.
     *
     * @param  list<self>  $rows
     * @return list<self>
     */
    public static function flagDuplicates(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            if ($row->matchStatus === self::INVALID) {
                continue;
            }
            $key = self::duplicateKey($row);
            if ($key === '') {
                continue;
            }
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return array_map(static function (self $row) use ($counts): self {
            $key = self::duplicateKey($row);

            if ($key === '' || $row->matchStatus === self::INVALID || ($counts[$key] ?? 0) < 2) {
                return $row;
            }

            $issues = array_merge($row->issues, ["This name appears {$counts[$key]} times in the file."]);

            return new self(
                $row->line, $row->name, $row->email, $row->phone,
                $row->eventLabel, $row->finishPosition, $row->pointsDisplayed,
                self::DUPLICATE, $row->matchedDriverId, $row->matchedDriverName,
                $row->matchScore, $row->candidates, $issues,
            );
        }, $rows);
    }

    /** Name plus event, so per-event exports are not mistaken for duplicates. */
    private static function duplicateKey(self $row): string
    {
        $name = DriverCsvParser::normaliseName($row->name);

        if ($name === '') {
            return '';
        }

        $event = $row->eventLabel !== null
            ? DriverCsvParser::normaliseName($row->eventLabel)
            : '';

        return $event === '' ? $name : $name.'@'.$event;
    }

    /**
     * @param  list<array{id: int, name: string}>  $existing
     * @return list<array{id: int, name: string, score: float}>
     */
    private static function rankCandidates(string $name, array $existing): array
    {
        if ($name === '') {
            return [];
        }

        $candidates = [];
        foreach ($existing as $driver) {
            $score = DriverCsvParser::nameSimilarity($name, (string) $driver['name']);
            if ($score >= self::MATCH_FLOOR) {
                $candidates[] = ['id' => (int) $driver['id'], 'name' => (string) $driver['name'], 'score' => $score];
            }
        }

        usort($candidates, static fn (array $a, array $b): int => ($b['score'] <=> $a['score']) ?: ($a['id'] <=> $b['id']));

        return array_values($candidates);
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
