# Phase 5k — Launch Readiness

Date: 2026-09-18. Starting commit: `866ddf5` (Phase 5j). No new migrations.

Phase 5k addresses the five P1 findings from the launch-readiness audit: missing
championship standings, missing server-side race/season integrity guards, public
self-registration, production/demo-seed safety, and backup documentation.

## 1. Championship standings (P1)

`StandingsService` already implemented the calculation and tie-breaking; it was
only unit-tested and never rendered. `SeasonController::show` now computes
`StandingsService::computeStandings($season->races, StandingsService::scoringFor($season))`
and passes the final standings plus a driver lookup to the view. Races are loaded
with `entries` and `penalties` eager-loaded (no N+1). `championship/show.blade.php`
renders an accessible `<table>` (caption, `<th scope="col">`) with position,
driver, points, gap, wins, podiums and poles, an empty state when no completed
races exist, and driver links. Scoring rules and tie-breaking are reused
unchanged — no second scoring implementation was introduced.

## 2. Server-side race and season integrity (P1)

Every race mutation now enforces the race's status **on the server** in
`RaceService` (not only by hiding UI). Status transitions are wrapped in
`DB::transaction` with a `lockForUpdate()` re-fetch and a status re-check so
concurrent/duplicate calls cannot double-complete or corrupt results.

### Race state rules implemented

| Action | Allowed race statuses |
| --- | --- |
| Open lobby | `draft` |
| Start qualifying | `lobby` |
| Record / correct / invalidate / restore / clear qualifying | `qualifying` |
| Lock grid | `qualifying` |
| Set kart number / grid penalty | `draft`, `lobby`, `qualifying`, `grid` |
| Set field (add/remove participants) | `draft`, `lobby` |
| Confirm / ready | `draft`, `lobby` |
| Classify driver (finished/DNF/DNS/retired/withdrawn) | `grid`, `racing` |
| Start race | `grid` |
| Complete race | `racing`, `grid` |
| Cancel race | anything except `completed` / `cancelled` |
| Issue / cancel penalty | anything except `cancelled` (post-race penalties remain possible) |
| Edit race details | `draft`, `lobby` |
| Delete race | `draft`, `cancelled` |
| Delete season | only when it has no races, records or awards |

Invalid mutations return **422**. Completed races can no longer be edited or
deleted, and their entries/results cannot be mutated. Season deletion no longer
silently cascades away championship history: a season with attached races,
records or awards is protected, and its delete button is hidden in that case.
Authorization policies and normal organizer workflows are preserved. The
overlapping UI controls (race Edit/Cancel, qualifying actions, grid kart/penalty,
lobby check-in, season delete) are also gated by status so the interface matches
the server rules.

## 3. Private-group registration and driver privacy (P1)

Public self-registration is disabled. Registration is now **invitation-based**:
`RegisteredUserController` requires a valid pending invitation token, and when the
invitation names an email, the submitted email must match. The invite landing page
passes the token to the register link, and the register form carries it as a
hidden field (with an "invitation only" notice when absent). Login, logout,
password reset, invitation acceptance and provisioning are unchanged. Driver
visibility keeps its documented behaviour (default public within the signed-in
app, `members`-only available per user); since only invited users can register,
the directory is no longer reachable by anonymous self-signups.

## 4. Production safety and demo seeding (P1)

- **Demo guard:** `DemoDataSeeder` and `ChampionshipContentSeeder` return early
  when `APP_ENV=production`, regardless of how they are invoked (including
  `db:seed --class=... --force`). Local development seeding is unaffected.
- **Deployment guide:** `docs/production-deployment.md` documents production
  settings by variable name (APP_ENV/APP_DEBUG/APP_URL/APP_KEY/LOG_*,
  SESSION_SECURE_COOKIE/SAME_SITE/ENCRYPT, MAIL_*), HTTPS, caching, the no-seed
  rule and open decisions. No secret values are recorded and `.env` is untouched.
- **Database:** SQLite remains the driver; the MySQL option is documented but not
  adopted in this phase (open decision).

## 5. Backup and restore (P1)

`docs/production-backup-and-restore.md` documents the SQLite-specific backup
procedure (`sqlite3 .backup` or a coordinated file copy), storage/retention,
keeping backups out of the repo/web root, a copy-only restore rehearsal, and
recovery limitations. A **copy-based rehearsal was actually performed** (see §7).

## 6. Tests

New/updated coverage:
- `tests/Feature/ChampionshipStandingsTest.php` — standings points/order rendered;
  empty state without completed races.
- `tests/Feature/RacesTest.php` — completed race cannot be deleted/edited, its
  entries/results cannot be mutated, qualifying cannot be recorded after grid
  lock, cancelled races can be deleted, plus the existing full flow.
- `tests/Feature/SeasonsTest.php` — season with history cannot be destroyed;
  empty season can be.
- `tests/Feature/Auth/RegistrationTest.php` — registration requires a valid
  invitation; matching email enforced.
- `tests/Feature/InviteTest.php` — registration via invite token returns to the
  invite.
- `tests/Feature/SeederGuardTest.php` — demo/database seeders blocked in
  production, still usable outside it.
- `NotificationTest`/`SettingsTest` updated to set up fields while the race is a
  draft before moving to racing.

## 7. Verification

- `php artisan test` — **226 passed / 737 assertions** (baseline 213 / 696).
- `php artisan migrate:status` — **26 migrations, none pending**.
- `php artisan route:list` — 93 routes (unchanged; no new endpoints).
- `php artisan view:cache` — compiles.
- Pint on every changed PHP file — clean (repo-wide pre-existing drift unchanged).
- **Restore rehearsal (copy only):** copied `database/database.sqlite` to a
  scratch path; byte-identical; `migrate:status` on the copy reported all 26
  migrations "Ran"; read-only counts on the copy = users 8, groups 2, races 5,
  entries 23, seasons 1, season_races 5. The live database was never modified.

## 8. Remaining manual steps / limitations

- Set a real production `.env` and HTTPS before go-live (deployment guide).
- Configure SMTP if invitation emails are wanted (shareable links work without it).
- Decide SQLite vs MySQL and set up scheduled off-host backups.
- The concurrency guarantee relies on transactions; SQLite serialises writes and
  `lockForUpdate()` is a no-op there, so protections are strongest on MySQL.
