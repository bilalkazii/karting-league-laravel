# Phase 5g — Group & Race Chat

Date: 2026-09-18. Companion audit: `docs/phase-5g-audit.md`.

## 1. Overview

Phase 5g delivers the first deferred feature carried over from the 5f doc: real,
schema-backed **chat** for groups and races. Two new tables, a service, a
controller, dedicated views, navigation entry points, and a test suite. Mixed
between the two revisions: the threads are **server-rendered with a gentle
auto-refresh interval**; live broadcast (websockets/Pusher) is deliberately
out of scope and documented as a limitation.

## 2. Data model

Two migrations (both SQLite- and MySQL-compatible, batch [3]):

```
chat_messages:
  id           bigint pk
  group_id     bigint nullable → groups.id (cascade delete)
  race_id      bigint nullable → races.id   (cascade delete)
  sender_id    bigint nullable → drivers.id (null on delete)
  body         text
  created_at / updated_at
  (index_group_thread: group_id, id) (index_race_thread: race_id, id)
  (index sender_id)

chat_last_reads:
  id           bigint pk
  driver_id    bigint → drivers.id        (cascade delete)
  group_id     bigint nullable → groups.id (cascade delete)
  race_id      bigint nullable → races.id   (cascade delete)
  last_read_at timestamp
  created_at / updated_at
  (unique driver_id + nullable group/race)
```

Notes, per the audit decision record:

- **bigint PKs**, matching the codebase convention (the 5f proposal's `uuid`
  PKs are dropped — the app already uses bigint PKs everywhere).
- `sender_id` is `nullOnDelete`: a deleted driver's messages persist, labelled
  **Former member** by `ChatService::senderLabel()`. The alternative (cascade)
  would silently rewrite history.
- Group and race threads delete with their owning group/race (`cascadeOnDelete`):
  a room is meaningless once its group/race is gone.
- **Exactly-one-scope** is enforced at the application level (see §4): a message
  row must reference a group **or** a race, never both, never neither. The
  nullable FKs alone cannot express this, so `ChatService::storeMessage()`
  rejects both violations with 422.
- `chat_last_reads.last_read_at` is the **last-read watermark** driving unread
  counts (see §3).

## 3. Domain logic — `app/Services/ChatService.php`

- `storeMessage()` — trims the body, rejects empty (422) and >500 chars (422),
  enforces exactly-one-scope (422), associates the active driver as `sender_id`,
  persists. Returns the message.
- `markThreadRead()` — `updateOrCreate` watermark for the driver in the thread
  (one row per driver+group and per driver+race).
- `unreadInGroupThread()` / `unreadInRaceThread()` — count of thread messages
  with `created_at > watermark`; no watermark row means everything is unread.
- `unreadForDriver()` — sums group-room unread across the driver's groups
  (room-level total shown on the index + sidebar badge). Race rooms are not
  aggregated here because they are not listed on `/chat`.
- `groupRoomsFor()` — map keyed by group id: `{group, unread, latest}` used by
  the index and sidebar, sorted by most recent activity first.
- `threadMessages()` — newest-first capped at 100, reversed to chronological for
  display; eager-loads `sender.profile`.
- `senderLabel()` — static; driver display name, or **Former member** for deleted
  senders.

This is the only place where chat queries/counts live, so race/group rules stay
out of the controller and tests can assert counts deterministically.

## 4. HTTP surface — `app/Http/Controllers/ChatController.php`

| Route | Name | Authz | Purpose |
|---|---|---|---|
| `GET /chat` | `chat` | any logged-in user | Room index |
| `GET /chat/groups/{group}` | `chat.group` | `GroupPolicy::view` | Group thread + marks read |
| `POST /chat/groups/{group}` | `chat.group.send` | `GroupPolicy::view` + driver | Store group message |
| `GET /chat/races/{race}` | `chat.race` | `RacePolicy::view` | Race thread + marks read |
| `POST /chat/races/{race}` | `chat.race.send` | `RacePolicy::view` + driver | Store race message |
| `DELETE /chat/messages/{message}` | `chat.messages.destroy` | sender only | Delete own message |

- FormRequest `StoreChatMessageRequest` validates `body` (required, `max:500`);
  a user without a driver profile cannot post.
- Deleting someone else's message is **403** — no moderation in this phase.
- Thread **viewing marks the thread read** for the caller (side effect, but
  intended: opening a room is the read signal).

## 5. Views

- `resources/views/chat/index.blade.php` — grid of group rooms (logo chip, name,
  "Group room", latest-message preview via `senderLabel`, unread badge, "You're
  all caught up" chip when nothing is unread, empty state pointing at My groups).
- `resources/views/chat/show.blade.php` — **shared** group/race thread:
  header (title, subtitle, back link to the group/race page), bubble history
  (red bubble + right-aligned for own messages, bordered left-aligned for
  others, diff-for-humans timestamp, "Delete" on own bubbles only), composer
  textarea with CSRF, and an Alpine auto-refresh every 20 s that **pauses while
  the tab is hidden or the composer is focused/being typed in** (so a refresh
  never eats an in-progress message). A caption documents that the app is
  server-rendered — no live broadcast.

## 6. Navigation

- **Sidebar** `/chat` item returns with a **real unread badge** (count via
  `ChatService::unreadForDriver`).
- **Bottom nav** gets a Chat tab.
- **Group tabs** get a group-scoped Chat tab → `chat.group`.
- **Race show** gets a "Race chat" button visible to all members who can view
  the race (not just managers).
- `feature-placeholder.blade.php` drops the `chat` entry (route is now real).

## 7. Security

- Group threads behind `GroupPolicy::view` (group membership); race threads
  behind `RacePolicy::view` (member of the race's group). Outsiders get 403 on
  both read and post.
- Cross-group isolation: a message can only ever be created/read under the
  route's own `{group}`/`{race}`; the exactly-one-scope guard prevents a message
  from silently attaching to a second scope.
- Ownership: `destroy` is sender-only (403 otherwise); there is no way to edit
  another driver's message.
- No concept of global moderation or message-level admin in this phase; if 5f's
  "admin" surface grows into global moderation later it will need its own policy
  tier (see 5f doc §3).
- CSRF on every mutating form; all rendered content (including user bodies)
  passes through Blade escaping so HTML/scripts render as text.

## 8. Tests — `tests/Feature/ChatTest.php` (16 tests)

Guest redirects on all six routes; member vs. non-member 403 on group and race
threads (GET and POST); storing a group message; trimming; whitespace-only /
empty / over-500 rejection (redirect + session error, per the app's
FormRequest convention); race thread read+post; deleting own vs. another
driver's message (403); unread watermark behaviour (1 → read → 0, affects
`unreadForDriver`); index room listing with preview; service-level 422 for
no-scope and two-scope messages.

`tests/Feature/AppShellTest` still covers `GET /chat` rendering 200 for a
logged-in user (16 chat tests + 3 app-shell routes = suite delta +16).

## 9. Verification

- `php artisan test` → **169 passed / 536 assertions** (was 153 / 480).
- `php -l` clean on every touched PHP file.
- `php vendor/bin/pint` applied to all touched files; `pint --test` passes on
  the touched set. (Note: `pint --test` on the **whole** repo still reports
  pre-existing baseline drift on untouched files — enums, `DashboardController`,
  older migrations, `Profile` model — all fixed independently of this phase,
  none modified here.)
- `php artisan view:cache` compiles every Blade template including the new chat
  views; cache cleared afterwards.
- `php artisan db:seed` ran twice — idempotent.
- `php artisan migrate --force` on the local dev DB applied both chat tables
  (batch [3]); `migrate:status` green across all 24 migrations.

## 10. Files

New: migrations `2026_09_18_000020` + `2026_09_18_000021`,
`app/Models/{ChatMessage,ChatLastRead}.php`, `app/Services/ChatService.php`,
`app/Http/Controllers/ChatController.php`,
`app/Http/Requests/StoreChatMessageRequest.php`,
`resources/views/chat/{index,show}.blade.php`, `tests/Feature/ChatTest.php`,
`docs/phase-5g-audit.md`.

Modified: `app/Models/{Driver,Group,Race}.php` (+chat relations, Pint import
cleanup), `routes/web.php`, `sidebar`, `bottom-nav`, `group-tabs`, `races/show`,
`feature-placeholder`.

## 11. Limitations / deferred

- **No live broadcast** — threads refresh on page load and every 20 s (paused
  while typing / tab hidden). Real-time delivery needs broadcast channels +
  Echo on the client and is out of phase scope.
- **Last 100 messages per thread**, no pagination; rooms hold modest history in
  practice. Older messages are not fetched.
- **No moderation** — members may only delete their own messages; group admins
  cannot remove others' messages (a future global/group moderation tier would
  extend the policy).
- **Race rooms are not listed on `/chat`** — they are entered from each race
  page, so the global unread badge is group-rooms only.
- **No message search, attachments, or formatting** beyond plain text.

Phase 5h follows from the plan when requested.