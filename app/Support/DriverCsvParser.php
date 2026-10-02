<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Read-only parsing and matching for driver CSV imports.
 *
 * This class never writes to the drivers or race_entries tables. It turns an
 * uploaded file into a reviewable list of rows, each tagged with how confident
 * we are that a row refers to a driver who already exists. Confirmation is a
 * separate, explicit step handled by DriverImportService.
 */
final class DriverCsvParser
{
    /** Header aliases, so a real-world export usually just works. */
    private const HEADER_ALIASES = [
        'name' => ['name', 'full_name', 'driver', 'driver name', 'fullname'],
        'email' => ['email', 'email address', 'e-mail', 'mail'],
        'phone' => ['phone', 'mobile', 'contact', 'phone number', 'whatsapp'],
        'event_label' => ['event', 'event_label', 'event label', 'race', 'round', 'event name'],
        'finish_position' => ['position', 'finish_position', 'finishing position', 'place', 'pos', 'result'],
        'points_displayed' => ['points', 'points_displayed', 'displayed points', 'total points', 'pts'],
    ];

    /**
     * Normalise a header cell so "Full Name", "full_name" and "fullname" all
     * resolve to the same field.
     */
    public static function normaliseHeader(string $header): string
    {
        $key = Str::of($header)->lower()->trim()->replace(['-', '_'], ' ')->squish()->toString();

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if ($key === $alias || $key === Str::of($alias)->replace('_', ' ')->toString()) {
                    return $field;
                }
            }
        }

        return (string) Str::of($key)->replace(' ', '_');
    }

    /**
     * Split raw CSV text into a header map plus data rows.
     *
     * @return array{headers: array<string, int>, rows: list<array<string, string|null>>, errors: list<string>}
     */
    public static function parse(string $contents): array
    {
        $contents = self::stripBom($contents);
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $headerIndex = 0;
        $headers = [];
        $rows = [];
        $errors = [];

        while (($cells = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $headerIndex++;

            // fgetcsv reports a blank line as [null].
            if ($cells === [null] || $cells === false) {
                continue;
            }

            if ($headers === []) {
                foreach ($cells as $position => $cell) {
                    $field = self::normaliseHeader((string) $cell);
                    // The first occurrence of a field wins, so a stray
                    // "Name (confirmed)" column cannot shadow the real name.
                    $headers[$field] ??= $position;
                }

                continue;
            }

            if (! isset($headers['name'])) {
                fclose($handle);

                return [
                    'headers' => [],
                    'rows' => [],
                    'errors' => ['The file needs a column for the driver name.'],
                ];
            }

            $row = [];
            foreach ($headers as $field => $position) {
                $row[$field] = isset($cells[$position]) ? trim((string) $cells[$position]) : null;
            }
            $row['_line'] = (string) $headerIndex;
            $rows[] = $row;
        }

        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows, 'errors' => $errors];
    }

    private static function stripBom(string $contents): string
    {
        $bom = "\xEF\xBB\xBF";

        return str_starts_with($contents, $bom) ? substr($contents, strlen($bom)) : $contents;
    }

    /**
     * Fold a name down to a comparable form so "Shoaib  Khan" and
     * "Shoaib Khan" compare equal, and so "Shoaibkhan" and "Khan, Shoaib" can
     * be scored against each other.
     */
    public static function normaliseName(string $name): string
    {
        $name = preg_replace('/\s*,\s*/', ' ', $name) ?? $name;

        return (string) Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->toString();
    }

    /**
     * Similarity between two names, 0.0 to 1.0.
     *
     * An exact folded match scores 1.0. A "Last, First" ordering of the same
     * tokens also scores 1.0, because that is the same person written
     * differently rather than a near miss. Anything else falls back to token
     * overlap, which keeps similar-but-different names below the exact-match
     * band so they reach a human.
     */
    public static function nameSimilarity(string $a, string $b): float
    {
        $left = self::normaliseName($a);
        $right = self::normaliseName($b);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        if ($left === $right) {
            return 1.0;
        }

        if (self::reorderedTokens($left) === self::reorderedTokens($right)) {
            return 1.0;
        }

        return self::tokenOverlap($left, $right);
    }

    /**
     * The same name with its tokens in a fixed order, so "shoaib khan" and
     * "khan shoaib" collapse to one value.
     */
    private static function reorderedTokens(string $normalised): string
    {
        $tokens = explode(' ', $normalised);
        sort($tokens);

        return implode(' ', $tokens);
    }

    /**
     * How much of the shorter name's token set appears in the longer one.
     */
    private static function tokenOverlap(string $left, string $right): float
    {
        $leftTokens = array_filter(explode(' ', $left));
        $rightTokens = array_filter(explode(' ', $right));

        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $shared = count(array_intersect($leftTokens, $rightTokens));

        return $shared / max(count($leftTokens), count($rightTokens));
    }
}
