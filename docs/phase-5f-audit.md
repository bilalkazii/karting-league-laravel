# Phase 5f — Chat, Admin & Notifications: Audit

Date: 2026-09-18. Repository: `karting-laravel` at HEAD `de6fedd`.

## 1. Existing chat/message schema

**None.** No migration creates a messages, chats, or conversation table. Search of
`database/migrations/**` for `messages|chats|conversations` returns nothing. The
Next.js reference application also contains no chat code or types — chat exists
only as a placeholder in its navigation.

Because the existing schema cannot store chat messages, group/race chat is **not
implementable this phase without a new table**. A schema proposal is documented in
`docs/phase-5f-chat-admin-notifications.md`; the `/chat` placeholder stays as a
clearly-labelled deferred view and is removed from application navigation.

## 2. Existing notification schema

**None.** The `users` migration (`0001_01_01_000000_create_users_table.php`)
creates only `users`, `password_reset_tokens`, `sessions`. There is no
`notifications` table. `User` uses the `Illuminate\Notifications\Notifiable`
trait but the database column backing it does not exist.

Laravel's canonical notification storage is the standard `notifications` table
(uuid primary key, `type`, notifiable morphs, `data`, `read_at`, timestamps). This
is the framework-standard, minimal, and MySQL/SQLite-compatible schema for the
feature this phase explicitly requests. **Migration planned:**
`2026_09_18_000019_create_notifications_table.php`.

Realtime (websockets/broadcast) is not part of this application; the page is
server-rendered Blade. Marking a notification read is a full-page form POST.

## 3. Existing roles

- `group_members.role` (varchar) restricted by the `GroupRole` enum to:
  `admin`, `organizer`, `member`. Group-scoped only.
- Pivot `(group_id, driver_id)` composite primary key. `joined_at`,
  `availability` also stored per member.
- There is **no global/application admin** — the `users` table has no
  `is_admin`/`role` column, and `User` has no admin flag.

## 4. Existing admin capabilities

- `GroupPolicy::update` / `manageMembers`: pivot role in `{admin, organizer}`.
- `GroupPolicy::view`: group membership.
- `RacePolicy`, `SeasonPolicy`, `DriverPolicy`: ownership/group-scoped rules from
  phases 5c/5d/5e.
- The group members page (`groups/members.blade.php`) already renders remove/block
  buttons for admins/organizers but they are decorative —
  "These updates affect local state only until backend integration."
- `GroupController` currently supports list/create/show/members/availability (self).

**Conclusion:** the application is group-administered, not globally administered.
Phase 5f implements the supported group-level administrative surface that the UI
already promises: member role management, member removal, and member addition —
all backed by the existing `group_members` table. A global admin panel (users
listing, impersonation, system settings) is **out of scope** because the data
model has no global admin concept; this distinction is documented.

## 5. Existing settings capabilities

- `/account` (`profile.edit`) already covers the supported settings surface:
  profile `full_name`, driver nickname, racing number, avatar colours.
- `/settings` is a placeholder view.
- No schema columns exist for notification preferences, theme preference, or
  privacy options. **Not implementable without schema changes** → the `/settings`
  placeholder is retained with a clear "deferred" message and removed from
  navigation.

## 6. Existing placeholders

`routes/web.php` maps to `feature-placeholder` view:

| Route | Decision |
| --- | --- |
| `/chat` | Deferred (no schema). Removed from nav, view kept with clear message. |
| `/notifications` | Replaced by a real notification listing page. |
| `/settings` | Deferred (no schema beyond `/account`). Removed from nav, view kept with clear message. |
| `/race-setup` | Genuinely unfinished dedicated feature; kept as placeholder, not in nav. |

Navigation currently links `/chat` from the sidebar (with a hardcoded unread badge
"3"), bottom nav, and group tabs, and links `/settings` from the sidebar and group
tabs. These must be cleaned up, and the header notification bell's hardcoded dot
must be driven by real state.

## 7. Required migrations, if any

1. `2026_09_18_000019_create_notifications_table.php` — standard Laravel
   notifications table (the canonical implementation of the requested feature).

No other migration is required. Chat, settings prefs, and global admin would each
need schema work that is explicitly out of scope for this phase.

## 8. Realtime requirements

- None. No websocket/broadcast infrastructure in the codebase. Notifications are
  read via HTTP GET pagination; read-state changes are form POSTs.

## 9. Security risks considered

- **Privilege escalation:** role changes restricted to group admins (organizers
  cannot grant admin). Organizers cannot demote/remove admins.
- **Self-lockout:** the last active admin of a group cannot be demoted or removed.
- **Cross-group isolation:** all member mutations are scoped to the `{group}`
  route parameter and authorised against that group's membership/pivot.
- **Notification leak:** listing and read-state mutations filter strictly to the
  authenticated user's own notifications (`notifiable_type = App\Models\User`,
  `notifiable_id = user id`).
- **CSRF:** every state-changing form includes `@csrf`.
- **Injection:** all dynamic output rendered through Blade `{{ }}`; no raw HTML
  rendering of user content.

## 10. Features that cannot be implemented without schema support

- **Chat** (group/race messaging) — requires `chat_messages` table.
- **Global admin panel** — requires a user-level admin flag/table.
- **Extended settings** (notification prefs, theme, privacy) — requires preference
  columns or a settings table.
- **Invite-by-email flow** for group onboarding — requires an invites table.

All of the above are deferred and documented (proposals in the phase doc).