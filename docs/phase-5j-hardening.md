# Phase 5j — Organizer Setup Completion & Hardening

Date: 2026-09-18. Starting commit: `c62519f` (Phase 5i). No migrations.

Phase 5j is a small, corrective phase: it fixes a broken race-creation form,
replaces fabricated dashboard data with real data, and removes a redundant
placeholder plus dead views. No new features, no schema changes.

## 1. Race creation fix (HIGH)

**Defect:** `StoreRaceRequest` requires `qualifying_lap_count`
(`app/Http/Requests/StoreRaceRequest.php:29`), but
`resources/views/races/create.blade.php` omitted the field, so submitting the
create form always failed validation. The edit form already had the field; the
create form was the odd one out.

**Fix:** `races/create.blade.php` now renders a labelled, required
`qualifying_lap_count` number input (min 1, max 10, default 1), matching the edit
form's markup and the existing validation. Format and lap count sit in a
two-column grid, with a single helper line below. No validation was weakened and
race formats are unchanged.

**Tests** (`tests/Feature/RacesTest.php`):
- `test_create_form_exposes_required_qualifying_lap_count_field` — asserts the
  rendered create page contains the field name and label.
- `test_store_creates_draft_race_with_organizer` — updated so the submitted
  payload mirrors exactly the fields the create form renders (no `rules`, since
  the form does not render it); asserts the race persists with the submitted
  `qualifying_lap_count` and empty default `rules`.
- `test_store_requires_qualifying_lap_count` — asserts omitting the field fails
  validation and no race is created.

## 2. Dashboard activity (real data)

**Before:** `resources/views/dashboard.blade.php` hardcoded a static
"Recent activity" list (`Umar joined the group`, `Saturday GP was created`,
`Ahmad set a new personal best`) presented as real.

**Decision:** a suitable real source exists — the `race_events` table (written by
`RaceService` throughout the session pipeline). The feed was rebuilt on it rather
than removed, and **no new event subsystem or migration was introduced**.

**Implementation** (`app/Http/Controllers/DashboardController.php`):
- `recentActivity(Collection $groupIds)` queries `race_events` scoped to the
  viewer's groups via `whereHas('race', … whereIn('group_id', …))`, ordered by
  `occurred_at` desc, `limit(5)` (bounded), eager-loading `race.group` and
  `driver.profile` (no N+1).
- Each event is mapped to `{name, time, initials, color, textColor}` using
  `RaceEventType::label()` (new method on the enum) plus the race name; avatar
  initials/colour come from the driver when present, otherwise the group identity.
- Users without a driver or groups get an empty feed, and the view renders a
  "No race activity yet" empty state.

**View** (`resources/views/dashboard.blade.php`): the hardcoded block was removed;
the card iterates the controller-provided `$activity`, uses inline
`color`/`textColor` for contrast, shows the empty state, and its "View all" link
now points to `races` (the feed is race-based).

**Tests** (`tests/Feature/DashboardTest.php`): real event is displayed; the
fabricated strings are absent and the empty state shows; activity from a foreign
group is hidden; the dashboard renders for a user with no driver.

## 3. `/race-setup` placeholder removal

`/race-setup` was the last placeholder (`routes/web.php`), described in
`docs/phase-5f-audit.md` §6 as a "genuinely unfinished dedicated feature". Its
stated purpose (build a race weekend: drivers, kart assignments, qualifying
rules, lobby) is already fully covered by the per-race `races/create` and the
race `setup`/`lobby` tabs. It had no navigation entry, callers, or redirects.

Removed:
- the `/race-setup` route (`routes/web.php`);
- `resources/views/feature-placeholder.blade.php` (only remaining consumer of the
  route; its other keys — `groups.index`, `races.index`, `championship.index` —
  were already stale);
- `resources/views/components/placeholder.blade.php` (used only by
  `feature-placeholder`);
- `resources/views/welcome.blade.php` (default Laravel view, unused: `/`
  redirects to dashboard/login).

Updated:
- `tests/Feature/AppShellTest.php` no longer probes `/race-setup`;
- `docs/phase-5f-audit.md` §6 annotated with the removal.

Root `/` behaviour is unchanged and covered by `tests/Feature/ExampleTest.php`
(guest → login, authenticated → dashboard).

## 4. Verification

- `php artisan test` — see the phase report for the exact totals.
- `php artisan migrate:status` — **26 migrations, no pending** (unchanged).
- `php artisan route:list` — `/race-setup` absent; all other routes intact.
- `php artisan view:cache` — compiles the changed dashboard view.
- `vendor/bin/pint --test` — clean on every file touched by Phase 5j. The
  repository-wide Pint drift (59 pre-existing files, all unrelated to this
  phase) is unchanged and out of scope.

## 5. Out of scope / deferred

- Repo-wide Pint cleanup (pre-existing, separate task).
- `/race-setup` is **not** replaced by an organizer hub (explicitly out of scope).
- No chat, invitation, admin, or later-phase work.
- No `.env`, credential, migration, or environment changes.
