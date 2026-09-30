# Phase 5h — Extended Settings: Audit

Date: 2026-09-18. Repository: `karting-laravel` at HEAD `fb8f8f7` (phase 5g).
Baseline: phase 5f audit/doc + 5g audit confirm the deferred-feature plan.

## 1. Next feature from the documented plan

`docs/phase-5f-chat-admin-notifications.md` §3 lists the deferred features in
order, and `docs/phase-5g-audit.md` §1 confirmed that ordering:

1. **Chat** — delivered in phase 5g (`docs/phase-5g-chat.md`).
2. **Global admin** — explicitly *out of scope by design* (no user-level admin
   concept exists; a global tier needs its own policy/table and was rejected).
3. **Extended settings** — deferred; proposal: `user_preferences (user_id pk, key,
   value)` or preference columns on `users`. **← next incomplete feature.**
4. **Invite-by-email** — deferred; needs an `invites` table.

Both reference and Laravel apps currently render `/settings` as a
`SectionPlaceholder`/`feature-placeholder`; the reference copy is *"Profile,
privacy, notifications, and application preferences will be configured here."*
`/account` already covers profile/driver editing, so phase 5h implements the
two preference areas that have **no** backing storage yet — notifications and
privacy — on a new `user_preferences` table.

Phase 5h is **extended settings**. Phase 5i (invite-by-email) is not started.

## 2. Existing implementation

- **`/settings`** is `Route::view('/settings', 'feature-placeholder')->name('settings')`
  (`routes/web.php:74`); `feature-placeholder` holds a `settings` copy block
  ("Settings beyond profile basics are deferred until preference storage exists").
  It is **not** linked from the sidebar/bottom-nav (removed in 5f); it is only
  reachable by URL. `AppShellTest::test_nav_routes_render_for_authenticated_user`
  asserts it returns 200, and `NotificationTest` asserts the placeholder copy.
- **No preference storage exists.** No `user_preferences` table, model, or
  relation. `git ls-files database/migrations` ends at
  `2026_09_18_000021_create_chat_last_reads_table.php`.
- **`/account`** (`ProfileController` + `profile.edit`) covers account/profile
  editing and is the current "settings" surface; it stays.
- **Notifications are unconditional today.** `RaceService` dispatches
  `RaceOpened` (all group members), `RaceCompleted` (entered drivers) and
  `PenaltyIssued` (target driver) with no user opt-out:
  - `openLobby()` → `notifyGroupMembers(...)`
  - `completeRace()` → `notifyEntryUsers(...)`
  - `issuePenalty()` → direct `$user->notify(...)` loop.
- **Driver visibility is unconditional today.** `DriverPolicy::view` returns
  `$user->driver !== null` and is **never invoked**; `DriverController::show`
  computes group/team filtering in-page, and `DriverController::index` lists
  every driver for any authenticated user. Any signed-in driver can view any
  other driver.
- Reusable conventions: `App\Enums\*` string-backed enums; composite-PK pivot
  models (`RaceEntry` has the `setKeysForSaveQuery` override pattern);
  `User` already has `profile()` / `driver()` (via `HasOneThrough`);
  services hold business logic; feature tests use `RefreshDatabase` + seeded
  demo identities; views use `x-app-layout`, `x-page-header`, `x-card`,
  `x-button`, design tokens (`var(--panel)`, `var(--line)`, `var(--red)`).

## 3. Missing functionality (scope of 5h)

1. **Preference storage** — `user_preferences` key/value table, per user.
2. **Settings page** (`GET /settings`) showing the signed-in user's current
   notification + privacy preferences, replacing the placeholder.
3. **Save settings** (`POST /settings`) with validation + CSRF, writing only
   whitelisted keys to the **authenticated** user.
4. **Notification preferences** — per-type opt-out (race opened, race completed,
   penalty issued) enforced at dispatch in `RaceService`.
5. **Privacy preference** — driver profile visibility (`public` vs `members`);
   enforced on `drivers.show` (403 for non-shared viewers) and `drivers.index`
   (members-only drivers hidden from viewers who share no group).
6. **Navigation** — Settings link restored in the sidebar footer.
7. **Tests** — settings access/persistence/isolation, notification opt-out,
   privacy enforcement.

## 4. Required migrations

| Migration | Purpose |
| --- | --- |
| `2026_09_18_000022_create_user_preferences_table.php` | `user_id` FK→`users` (cascade), `key` varchar(100), `value` varchar(255), timestamps, **composite PK `(user_id, key)`**. |

Composite PK (not a surrogate `id`) follows both the 5f proposal
(`user_id pk, key, value`) and the codebase's pivot convention
(`group_members`, `team_members`, `race_entries`). No other migration required;
theme has no storage need (see §7).

## 5. Authorization / security risks

- **Cross-user writes:** `POST /settings` must bind every write to
  `$request->user()`; it must not accept/trust a `user_id` (or any other model
  key). Only keys present in the form-request rules are persisted — arbitrary
  preference keys (e.g. `role`, `is_admin`) are dropped before storage.
- **Key injection:** a preference row is `(user_id, key, value)`; unchecked
  mass-assignment of `key`/`value` could otherwise let a user seed unexpected
  keys. Whitelist on write; render only known keys.
- **Privacy bypass:** `drivers.show` previously relied on in-page filtering only.
  Phase 5h adds a real `DriverPolicy::view` check; the directory query is scoped
  so a members-only driver is neither listed nor viewable by a non-shared user.
  Self-view always allowed; default visibility stays `public`, so no existing
  test/behaviour regresses.
- **Notification suppression is per-recipient:** muting affects only the
  muting user; other members still receive. A muted user simply gets no row.
- **CSRF** on the settings form; **validation** of every preference value
  (booleans + enum).
- No new privilege tier: settings are strictly self-service.

## 6. Affected routes, models, services, views, policies, tests

- **Routes (`routes/web.php`):** replace the `/settings` placeholder with
  `GET /settings` (`settings`, `SettingsController@index`) and
  `POST /settings` (`settings.update`, `SettingsController@update`).
- **Migration/model:** new `2026_09_18_000022_create_user_preferences_table.php`,
  `app/Models/UserPreference.php`; `User::preferences()` + preference helpers.
- **Enums:** new `App\Enums\NotificationType`, `App\Enums\DriverProfileVisibility`.
- **Controller/request:** new `app/Http/Controllers/SettingsController.php`,
  `app/Http/Requests/UpdateSettingsRequest.php`.
- **Service:** `RaceService` gains a per-recipient preference check at dispatch.
- **Policy/controller:** `DriverPolicy::view` becomes real;
  `DriverController::{index,show}` use the visibility rule.
- **Views:** new `resources/views/settings/index.blade.php`,
  `resources/views/components/toggle.blade.php`; edit
  `components/sidebar.blade.php` (footer Settings link),
  `feature-placeholder.blade.php` (drop `settings` key).
- **Tests:** new `tests/Feature/SettingsTest.php`; update
  `tests/Feature/NotificationTest.php` (its placeholder assertion is obsolete).

## 7. Deferred / out of 5h

- **Theme preference** — the app is dark-only by design (AGENTS visual
  language; migration audit item 18: dark-only). There is no light theme to
  toggle, so no fake control is added. The `user_preferences` store can hold a
  `theme` key later; not surfaced in the UI.
- **Global admin** — out of scope by design (unchanged from 5f).
- **Invite-by-email** — phase 5i candidate; not started.
- **Per-group notification mute / quiet hours / email digest** — no delivery
  channel beyond the in-app database notifications exists.
- **Editing another user's preferences or admin-forcing settings** — no such
  tier.

## 8. Decision record

- Key/value table (`user_preferences`) per the documented proposal, composite PK.
- Preference semantics are **opt-out** for notifications (default: receive).
- Privacy default is **public** (matches today's behaviour); `members` restricts
  to shared-group viewers and self.
- Theme is intentionally not implemented (dark-only design).
