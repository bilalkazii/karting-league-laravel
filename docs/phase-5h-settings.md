# Phase 5h — Extended Settings

Date: 2026-09-18. Companion audit: `docs/phase-5h-audit.md`.

## 1. Overview

Phase 5h delivers the third deferred feature from the 5f plan (after chat in 5g):
real, schema-backed **extended settings**. `/settings` stops being a placeholder
and becomes a self-service page for the two preference areas that had no backing
storage — **notification opt-out** and **driver-profile privacy** — on a new
key/value `user_preferences` table. Profile/account editing stays on `/account`.

This phase resumed from an interrupted working tree: the migration, model, enums,
and `User` imports existed at HEAD `fb8f8f7`, but no controller, routes, views,
enforcement, or tests did.

## 2. Data model

One migration (SQLite- and MySQL-compatible, batch [4]):

```
user_preferences:
  user_id     bigint → users.id (cascade delete)
  key         varchar(100)
  value       varchar(255)
  created_at / updated_at
  primary key (user_id, key)
```

Per the audit decision record:

- **Composite PK `(user_id, key)`**, not a surrogate id — matches the 5f proposal
  and the codebase pivot convention (`group_members`, `race_entries`).
- `UserPreference` sets `$incrementing = false`, `$primaryKey = 'user_id'`, and
  overrides `setKeysForSaveQuery()` so updates target both key parts. This mirrors
  `RaceEntry`.
- No `theme` storage is surfaced; the app is dark-only by design (audit §7).

Known preference keys are produced by the enums, never hard-coded in the
controller:

- `notifications.race_opened` / `notifications.race_completed` /
  `notifications.penalty_issued` — `NotificationType::preferenceKey()`
- `privacy.driver_profile_visibility` — `DriverProfileVisibility::preferenceKey()`

## 3. Domain logic — `app/Models/User.php`

- `preferences()` — `HasMany` to `UserPreference`.
- `preferenceValue($key, $default)` — reads one row for the user.
- `setPreference($key, $value)` — `updateOrCreate` scoped to the user + key.
- `notificationEnabled(NotificationType)` — **opt-out**: missing row or value
  `'1'` means receive; `'0'` means muted.
- `driverProfileVisibility()` — **default public**; falls back to `Public` for a
  missing/unknown value.

All preference semantics live on the model so the controller, policy, and service
share one definition.

## 4. HTTP surface

| Route | Name | Purpose |
|---|---|---|
| `GET /settings` | `settings` | `SettingsController@index` — render current preferences |
| `POST /settings` | `settings.update` | `SettingsController@update` — persist whitelisted keys |

- `UpdateSettingsRequest` requires `driver_profile_visibility` (`Rule::enum`) and
  a `notifications` array whose keys are built from `NotificationType::cases()`;
  each is `required|boolean`. `validated()` therefore drops any key not declared
  in the rules.
- `update()` writes every known notification key as `'1'`/`'0'` and the privacy
  key, always to `$request->user()`. It never reads a model key from the request.

## 5. Notification enforcement — `app/Services/RaceService.php`

- `notifyRaceUsers()` maps the notification class to a `NotificationType` via
  `NotificationType::fromNotificationClass()` and skips a recipient whose
  preference is off.
- `issuePenalty()` applies the same per-recipient check before `notify()`.
- Suppression is **per-recipient**: muting affects only the muting user; every
  other member still receives. `RaceOpened`, `RaceCompleted`, and `PenaltyIssued`
  are all covered.

## 6. Privacy enforcement

- `DriverPolicy::view` is now real (it was previously never invoked and always
  returned true for any driver):
  - self-view always allowed;
  - `public` drivers visible to any signed-in driver;
  - `members` drivers visible only to drivers sharing at least one group.
- `DriverController::show` calls `authorize('view', $driver)` (403 otherwise).
- `DriverController::index` excludes members-only drivers whose owner shares no
  group with the viewer, so they are neither listed nor viewable. Default
  `public` means existing directory behaviour is unchanged.

## 7. Views & navigation

- `resources/views/settings/index.blade.php` — `x-page-header` + two `x-card`
  sections (Notifications toggles, Privacy radio options), a save button, success
  and validation banners.
- `resources/views/components/toggle.blade.php` — reusable checkbox switch
  (hidden `0` + checkbox `1`, `peer`-driven track/thumb, focus-visible ring).
- Sidebar footer regains a **Settings** link (`sliders-horizontal`), alongside
  Account.
- `feature-placeholder.blade.php` drops the `settings` entry (route is real now).

## 8. Security

- Every write is bound to `$request->user()`; a smuggled `user_id` (or any other
  model key) is ignored by validation and cannot target another user.
- Only whitelisted keys are persisted; arbitrary keys such as `role` or
  `is_admin` are dropped before storage (covered by tests).
- Privacy bypass closed: the directory query and the `view` policy both consult
  the same visibility rule, so a members-only driver cannot be reached by a
  non-shared viewer through either surface.
- Values validated (booleans + enum); CSRF on the form; all output escaped by
  Blade.
- No new privilege tier — settings are strictly self-service.

## 9. Tests — `tests/Feature/SettingsTest.php` (13 tests)

Guest redirects (GET + POST); defaults render; persistence of notification and
privacy preferences; writes scoped to the authenticated user (foreign user
untouched); unknown keys dropped; invalid visibility rejected; required
notification keys enforced; per-recipient suppression for `RaceOpened`,
`RaceCompleted`, and `PenaltyIssued`; members-only driver hidden from directory
and 403 on profile; members-only driver visible to a shared-group viewer;
self-view always allowed.

`tests/Feature/NotificationTest.php` loses its obsolete
`/settings` placeholder assertion (the route no longer renders the placeholder).

## 10. Verification

- `php artisan test` → **181 passed / 578 assertions** (baseline 169 / 536; +12).
- `php -l` clean on every touched PHP file.
- `vendor/bin/pint --test` passes on the touched set. (Whole-repo `pint --test`
  still reports pre-existing baseline drift on untouched files — enums, older
  migrations, `Profile`, etc. — none modified here.)
- `php artisan view:cache` compiles every Blade template including the new
  settings view + toggle; cache cleared afterwards.
- `php artisan migrate --force` applied `user_preferences` (batch [4]);
  `migrate:status` green across all 25 migrations.
- `php artisan db:seed` ran twice — idempotent.

## 11. Files

New: `app/Http/Controllers/SettingsController.php`,
`app/Http/Requests/UpdateSettingsRequest.php`,
`resources/views/settings/index.blade.php`,
`resources/views/components/toggle.blade.php`, `tests/Feature/SettingsTest.php`,
`docs/phase-5h-settings.md` (plus the pre-outage migration, `UserPreference`
model, and enums carried in the working tree).

Modified: `app/Models/User.php` (preferences relation + helpers),
`app/Policies/DriverPolicy.php`, `app/Http/Controllers/DriverController.php`,
`app/Services/RaceService.php`, `routes/web.php`, `components/sidebar.blade.php`,
`feature-placeholder.blade.php`, `tests/Feature/NotificationTest.php`.

## 12. Limitations / deferred

- **Theme preference** not surfaced — dark-only design.
- **Global admin** — out of scope by design (unchanged from 5f).
- **Invite-by-email** — phase 5i candidate; not started.
- **Per-group notification mute / quiet hours / email digest** — no delivery
  channel beyond in-app database notifications exists.
- **Editing another user's preferences / admin-forcing settings** — no such tier.
