# Phase 5e — Races Pipeline

Races CRUD plus the full manual session pipeline: lobby, qualifying, grid, live
control, results, and penalties. Everything stays software-only; no hardware
timing, lap counters, or live telemetry.

## Scope

- Race record (per group) with venue, date, format, qualifying lap count.
- Session lifecycle: `draft` -> `lobby` -> `qualifying` -> `grid` -> `racing` -> `completed`, plus `cancelled`.
- Manual qualifying: record lap from split fields (min/sec/ms), invalidate, restore, manual correct, clear.
- Grid lock with qualifying order, grid penalties, and locked grid edit.
- Live control: classify each driver as finished/DNF/DNS/retired/withdrawn; server-assigned finish positions.
- Penalties: issue with category/severity effect (time or grid), cancel, totals feed results and standings.
- Deterministic results ordering and championship standings (ported helpers), fully unit-tested.

## Details

- Race state is interpreted rather than stored per-entry; race `status` and entry
  `status`/`finish_position` columns are the source of truth. No new migration was
  required; every column already existed from the Phase 3 schema.
- Standings are computed on demand from completed races for a season; never stored.
- `RaceEntry` uses a composite primary key `(race_id, driver_id)`. Eloquent does not
  natively key model saves by composite keys, so `RaceEntry::setKeysForSaveQuery()`
  is overridden to scope updates/deletes on both columns. Without this, `$entry->update(...)`
  silently matched zero rows.
- `RaceUtils` and `StandingsService` mirror `src/lib/race-utils.ts` from the Next.js
  app. One divergence is deliberate: the TypeScript numeric-or sort chain
  (`a - b || c - d`) does not survive the PHP port because `||` returns a boolean;
  `buildFinalResults()` uses explicit rank by rank -> finish position -> grid position.

## Routes

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/races` | `races` | List races for the user's groups |
| GET | `/races/new` | `races.new` | Create form |
| POST | `/races` | `races.store` | Store a race |
| GET | `/races/{race}` | `races.show` | Race detail with tabs |
| GET | `/races/{race}/edit` | `races.edit` | Edit form |
| PATCH | `/races/{race}` | `races.update` | Update a race |
| DELETE | `/races/{race}` | `races.destroy` | Delete a draft race |
| POST | `/races/{race}/lobby` | `races.lobby.open` | Draft -> lobby |
| POST | `/races/{race}/qualifying/start` | `races.qualifying.start` | Lobby -> qualifying |
| POST | `/races/{race}/qualifying` | `races.qualifying.record` | Record/invalidate/restore/correct/clear |
| POST | `/races/{race}/lock-grid` | `races.lock-grid` | Qualifying -> grid |
| POST | `/races/{race}/entries` | `races.entries.set` | Set the field |
| POST | `/races/{race}/entries/{driver}` | `races.entries.update` | Kart number / confirmed / grid penalty |
| POST | `/races/{race}/entries/{driver}/ready` | `races.entries.ready` | Toggle ready |
| DELETE | `/races/{race}/entries/{driver}` | `races.entries.remove` | Remove an entry |
| POST | `/races/{race}/drivers/{driver}/status` | `races.driver-status` | Classify a driver |
| POST | `/races/{race}/start` | `races.start` | Grid -> racing |
| POST | `/races/{race}/complete` | `races.complete` | Racing/grid -> completed |
| POST | `/races/{race}/cancel` | `races.cancel` | Cancel a session |
| POST | `/races/{race}/penalties` | `races.penalties.store` | Issue a penalty |
| POST | `/races/{race}/penalties/{penalty}/cancel` | `races.penalties.cancel` | Cancel a penalty |

All routes sit behind `auth`. Record-level authorization is the custom
`RacePolicy` (view/manage/destroy) which checks membership in the race's group
and role (manage = organizer or admin/moderator member).

## Key files

- `app/Models/RaceEntry.php` — composite-key support (save/update/delete).
- `app/Services/RaceService.php` — lifecycle transitions, field management, qualifying ops, penalties, event journal.
- `app/Support/RaceUtils.php` — lap-time formatting/parsing, qualifying rows, pole/delta, grid rows, final results.
- `app/Support/StandingsService.php` — classification, race points, standings/snapshots, season scoring lookup.
- `app/Http/Controllers/RaceController.php` / `RaceSessionController.php` — thin HTTP layer; all logic delegated to services.
- `app/Http/Requests/StoreRaceRequest.php` / `UpdateRaceRequest.php` — creation/update validation, group-non-admin 403.
- `app/Policies/RacePolicy.php` — group-member authorization.
- `resources/views/races/…` — `index`, `create`, `edit`, `show` (tab shell) and `partials/` for each tab.
- `resources/views/components/race-*.blade.php`, `result-status-badge.blade.php` — shared race UI.
- `tests/Feature/RacesTest.php`, `tests/Unit/RaceUtilsTest.php`, `tests/Unit/StandingsServiceTest.php` — tests.

## Verification

- `php artisan test` — 131 passed / 131, 401 assertions.
- `php artisan view:cache` — Blade templates compile.
- `php vendor/bin/pint` — clean on touched files.
- `php -l` — no syntax errors on touched files.
- `db:seed` twice — DemoDataSeeder + ChampionshipContentSeeder idempotent.

Commit: `1626548`