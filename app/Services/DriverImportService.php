<?php

namespace App\Services;

use App\Enums\DriverAvailability;
use App\Enums\GroupRole;
use App\Models\Driver;
use App\Models\DriverImport;
use App\Models\DriverImportRow;
use App\Models\Group;
use App\Models\Profile;
use App\Models\User;
use App\Support\DriverCsvParser;
use App\Support\DriverCsvRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Two-step driver import: build a reviewable preview, then apply exactly the
 * rows an administrator confirmed.
 *
 * The safety rules this enforces:
 *
 *  - uploading a file writes no driver rows at all
 *  - a row that matches an existing driver links to it and never edits it
 *  - ambiguous or uncertain matches are held back until a person resolves them
 *  - race results and historical entries are never written
 *  - confirming twice does not create a second set of drivers
 */
class DriverImportService
{
    /**
     * Parse an uploaded file and store it as a pending preview. No driver or
     * race data is created or modified.
     */
    public function preview(Group $group, User $user, string $contents, string $filename): DriverImport
    {
        $parsed = DriverCsvParser::parse($contents);

        if ($parsed['errors'] !== []) {
            throw ValidationException::withMessages(['file' => $parsed['errors']]);
        }

        if ($parsed['rows'] === []) {
            throw ValidationException::withMessages(['file' => ['The file has a header but no data rows.']]);
        }

        // Only drivers already in this group are candidates for matching, so an
        // import can never silently attach a row to someone in another group.
        $existing = Driver::with('profile')
            ->whereHas('groups', fn ($q) => $q->where('groups.id', $group->id))
            ->get()
            ->map(fn (Driver $driver): array => [
                'id' => $driver->id,
                'name' => $driver->profile?->full_name ?? $driver->nickname ?? "Driver #{$driver->id}",
            ])
            ->all();

        $rows = array_map(
            static fn (array $row): DriverCsvRow => DriverCsvRow::fromArray($row, $existing),
            $parsed['rows'],
        );
        $rows = DriverCsvRow::flagDuplicates($rows);

        return DB::transaction(function () use ($group, $user, $filename, $rows): DriverImport {
            $import = DriverImport::create([
                'group_id' => $group->id,
                'created_by' => $user->id,
                'original_filename' => $filename,
                'status' => DriverImport::PREVIEW,
            ]);

            foreach ($rows as $row) {
                DriverImportRow::create([
                    'driver_import_id' => $import->id,
                    'row_number' => $row->line,
                    'raw_name' => $row->name,
                    'raw_email' => $row->email,
                    'raw_phone' => $row->phone,
                    'raw_event_label' => $row->eventLabel,
                    'raw_finish_position' => $row->finishPosition,
                    'raw_points_displayed' => $row->pointsDisplayed,
                    'match_status' => $row->matchStatus,
                    'matched_driver_id' => $row->matchedDriverId,
                    'match_score' => $row->matchScore,
                    'issues' => $row->issues,
                ]);
            }

            return $import;
        });
    }

    /**
     * Apply a confirmed import.
     *
     * @param  array<int, string>  $rowActions  row_number => skip|link:<driver_id>|create
     */
    public function confirm(DriverImport $import, array $rowActions): DriverImport
    {
        abort_unless($import->isPending(), 422, 'This import has already been handled.');

        return DB::transaction(function () use ($import, $rowActions): DriverImport {
            $import->load('rows');

            foreach ($import->rows as $row) {
                $action = $this->normaliseAction($rowActions[(int) $row->row_number] ?? 'skip');

                if ($action === 'skip' || $row->isApplied()) {
                    continue;
                }

                // A row the parser rejected cannot be forced through by
                // hand-editing the form.
                abort_if(
                    $row->match_status === DriverCsvRow::INVALID,
                    422,
                    "Line {$row->row_number} is not valid and cannot be imported: ".implode(' ', $row->issues ?? []),
                );

                if ($action[0] === 'link') {
                    $this->linkToExistingDriver($row, $action[1]);

                    continue;
                }

                if ($action[0] === 'create') {
                    $this->createDriver($import, $row);
                }
            }

            $import->update([
                'status' => DriverImport::APPLIED,
                'confirmed_at' => now(),
            ]);

            return $import;
        });
    }

    /**
     * Turn a submitted form value into a [verb, driverId] pair.
     *
     * "link" without an id means "use the driver the preview matched", and only
     * "link:<id>" names a driver the reviewer picked by hand. Anything
     * unrecognised is a skip rather than a silent write.
     *
     * @return array{0: string, 1: int|null}
     */
    private function normaliseAction(mixed $value): array
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === 'create') {
            return ['create', null];
        }

        if (str_starts_with($value, 'link:')) {
            return ['link', (int) substr($value, 5)];
        }

        if ($value === 'link') {
            return ['link', null];
        }

        return ['skip', null];
    }

    /**
     * Point a row at a driver the administrator picked. The existing driver is
     * only linked from the import row; nothing about the driver changes.
     */
    private function linkToExistingDriver(DriverImportRow $row, ?int $driverId): void
    {
        $driverId ??= $row->matched_driver_id;

        abort_if(! $driverId, 422, "Choose which existing driver line {$row->row_number} refers to.");

        $driver = Driver::with('profile')
            ->whereHas('groups', fn ($q) => $q->where('groups.id', $row->import->group_id))
            ->find($driverId);

        abort_unless($driver !== null, 422, 'That driver is not a member of this group.');

        $row->update([
            'matched_driver_id' => $driver->id,
            'match_status' => DriverCsvRow::EXACT,
            'applied_at' => now(),
        ]);
    }

    /**
     * Add a brand new driver for a row that matched nothing.
     *
     * A driver is only ever created here, on explicit confirmation, and never
     * over an existing profile with the same name: if one turns up between the
     * preview and the confirmation, the row is held back instead.
     *
     * The schema is users 1:1 profiles 1:1 drivers, so a racing identity cannot
     * exist without a login behind it. The account is provisioned with a random
     * unusable password: the imported driver can race and be scored immediately,
     * but cannot sign in until somebody claims the identity through the normal
     * registration path. No email is ever mailed here.
     */
    private function createDriver(DriverImport $import, DriverImportRow $row): void
    {
        $name = trim($row->raw_name);

        $existingId = Profile::query()
            ->whereRaw('lower(full_name) = ?', [mb_strtolower($name)])
            ->value('id');

        if ($existingId !== null) {
            $row->update([
                'match_status' => DriverCsvRow::DUPLICATE,
                'issues' => array_merge($row->issues ?? [], [
                    'A profile with this name now exists, so no new driver was created.',
                ]),
            ]);

            return;
        }

        $user = User::create([
            'name' => $name,
            'email' => $this->placeholderEmailFor($name),
            'password' => Hash::make(Str::random(64)),
        ]);

        $profile = Profile::create([
            'user_id' => $user->id,
            'full_name' => $name,
        ]);

        $driver = Driver::create([
            'profile_id' => $profile->id,
            'nickname' => $this->nicknameFrom($name),
            'racing_number' => null,
            'avatar_color' => '#27272a',
            'avatar_text_color' => '#ffffff',
            'rating' => 1200,
        ]);

        $import->group->members()->attach($driver->id, [
            'role' => GroupRole::Member->value,
            'availability' => DriverAvailability::Available->value,
            'joined_at' => now(),
        ]);

        $row->update([
            'created_driver_id' => $driver->id,
            'match_status' => DriverCsvRow::UNMATCHED,
            'applied_at' => now(),
        ]);
    }

    /**
     * A non-routable address for the placeholder account.
     *
     * The real address from the CSV is deliberately not used: putting a real
     * person's address on an account nobody can sign in to would be worse than
     * a placeholder, and a claim flow can fill it in later.
     */
    private function placeholderEmailFor(string $name): string
    {
        $slug = Str::slug($name) ?: 'driver';

        do {
            $email = $slug.'-'.Str::lower(Str::random(8)).'@imported.invalid';
        } while (User::where('email', $email)->exists());

        return $email;
    }

    /** Two-letter display code, mirroring registration. */
    private function nicknameFrom(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [$name];

        return Str::upper(Str::substr($parts[0], 0, 2));
    }

    /** Counts for the preview summary line. */
    public function summary(DriverImport $import): array
    {
        $counts = $import->rows()
            ->reorder()
            ->selectRaw('match_status, count(*) as total')
            ->groupBy('match_status')
            ->pluck('total', 'match_status');

        return [
            'total' => (int) $counts->sum(),
            'exact' => (int) $counts->get('exact', 0),
            'unmatched' => (int) $counts->get('unmatched', 0),
            'uncertain' => (int) $counts->get('uncertain', 0),
            'ambiguous' => (int) $counts->get('ambiguous', 0),
            'duplicate' => (int) $counts->get('duplicate', 0),
            'invalid' => (int) $counts->get('invalid', 0),
        ];
    }
}
