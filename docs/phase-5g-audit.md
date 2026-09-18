# Phase 5g — Group & Race Chat: Audit

Date: 2026-09-18. Repository: `karting-laravel` at HEAD `90f66e6` (phase 5f).
Baseline: phase 5f audit + doc confirm `/chat` is the first-listed deferred feature
with a documented schema proposal and required preconditions.

## 1. Next feature from the documented plan

`docs/phase-5f-chat-admin-notifications.md` §3 lists deferred features in order:
**Chat** (proposal + requirements), global admin (out of scope by design),
extended settings (deferred), invite-by-email (deferred). Chat is therefore the
next incomplete feature to migrate.

Documented proposal (5f doc §3):

```
chat_messages:
  id           uuid pk
  group_id     fk → groups.id (nullable for race-room threads)
  race_id      fk → races.id   (nullable)
  sender_id    fk → drivers.id
  body         text
  created_at   timestamp
```

Documented requirements before build: thread scoping (group/race), a read state
("read receipts or last-read watermark"), and live updates only if wanted — the
app is pure server-rendered Blade, so **no broadcast/websocket** infrastructure.

## 2. Existing implementation

- **No messages/chat tables** exist (`git ls-files database/migrations` → none
  past `2026_09_18_000019_create_notifications_table`).
- **No models/services/controllers** for chat. Nothing can store or serve a chat
  message today.
- Currently `/chat` is `Route::view('/chat', 'feature-placeholder')` → placeholder
  view (deferred message). The route name `chat` exists.
- Navigation no longer links chat (removed in 5f): sidebar/bottom-nav have no Chat
  item; group-tabs has no Chat tab. `layouts/app` bell references
  `unreadNotifications` only.
- Authorization primitives that a chat feature can reuse:
  - `GroupPolicy::view` — membership in `group_members`.
  - `RacePolicy::view` — membership in the race's group.
  - Role/pivot helpers from `group_members.role` (admin/organizer/member).
- Conventions: bigint `$table->id()` PKs (no UUID columns anywhere), `timestamps()`,
  `foreignId(...)->constrained()`, eager-loading relations, `AuthorizesRequests`,
  services for business logic (`RaceService`, `StandingsService`), feature tests
  with `RefreshDatabase` + seeded demo identities.

## 3. Missing functionality (scope of 5g)

1. Chat storage schema — messages table + last-read watermark table.
2. Thread scoping: a **group room** (all members) and a **race room** (members of
   the race's group).
3. Read state: per-driver last-read watermark per thread → unread counts.
4. Message creation (post), listing (room view), and sender-ownership deletion.
5. Room index (`/chat`) listing the user's group rooms with latest-message preview
   and unread count.
6. Navigation: Chat restored to sidebar/bottom-nav/group-tabs; Race chat reachable
   from the race page; unread badge on the sidebar Chat item (real count, replacing
   the fake badge removed in 5f).
7. Tests: access control, posting, validation, deletion ownership, unread counts,
   cross-group isolation.

## 4. Required migrations

| Migration | Purpose |
| --- | --- |
| `2026_09_18_000020_create_chat_messages_table.php` | `id`, nullable `group_id` + `race_id` FKs (exactly one set at app level), `sender_id` FK (nullOnDelete), `body` text, timestamps, indexes on `group_id`/`race_id`. |
| `2026_09_18_000021_create_chat_last_reads_table.php` | `driver_id` FK, nullable `group_id` + `race_id` FKs, `last_read_at`, timestamps, unique `(driver_id, group_id)` and `(driver_id, race_id)`. |

Deviation from the proposal: **bigint PK, not uuid** — the codebase uses
`$table->id()` everywhere and has no UUID/ULID usage. Noted in the phase doc.

## 5. Authorization / security risks

- **Cross-group access:** race rooms must gate with `RacePolicy::view` (member of
  the race's group), group rooms with `GroupPolicy::view` (member). Any child of a
  race reveals only that group's content.
- **Scope validation:** a message must target exactly one thread (group xor race);
  a watermark must too. Prevent the "both/null" smuggling of rows into broad
  queries.
- **Message injection / spam:** `body` trimmed, 1–500 chars, rendered through Blade
  `{{ }}` (auto-escaped); no raw HTML.
- **Deletion ownership:** only the sender may delete a message (403 otherwise);
  deletion route must not allow cross-thread guessing.
- **Unread-count isolation:** counts scoped to threads the driver may view.
- **Self-referential:** deleting a driver nulls `sender_id` (message persists,
  sender shown as "Former member") — no orphan leaks.
- **CSRF:** all POST/DELETE forms include `@csrf`.
- No new privilege tiers: chat is member-level (same view authority as races);
  moderating others' messages is **deferred** (noted).

## 6. Affected routes, views, services, tests

- **Routes (`routes/web.php`):** replace `Route::view('/chat', ...)`; add
  `GET /chat`, `GET|POST /chat/groups/{group}`, `GET|POST /chat/races/{race}`,
  `DELETE /chat/messages/{message}`.
- **Views:** new `chat/index.blade.php` + `chat/show.blade.php`; update
  `components/sidebar.blade.php` (Chat + unread badge), `components/bottom-nav.blade.php`,
  `components/group-tabs.blade.php`, `races/show.blade.php` (Race chat link),
  `feature-placeholder.blade.php` (drop chat table key).
- **Models:** new `ChatMessage`, `ChatLastRead`; relations on `Group`, `Race`,
  `Driver`.
- **Service:** new `app/Services/ChatService.php` (store, mark read, unread counts,
  room previews).
- **Request:** new `StoreChatMessageRequest`.
- **Controller:** new `app/Http/Controllers/ChatController.php`.
- **Tests:** new `tests/Feature/ChatTest.php`; `AppShellTest`/nav tests still
  expect `/chat` 200 (kept green).

## 7. Deferred (explicitly out of 5g)

- Live/broadcast updates (websockets, SSE) — requires broadcast infra; manual +
  gentle interval refresh instead.
- Message moderation/editing (delete other's messages, pin, report) — ownership
  deletion only.
- Race-room listing on `/chat` index (race chat stays linked from each race page).
- Emoji/attachments/mentions — out of scope; plain text only.
- Chat testing/demo data in seeder — not added; test-created only.

## 8. Decision record

- Bigint PKs (codebase convention) instead of the proposal's uuid.
- `nullOnDelete` sender (message permanence) matching `race_events.driver_id`.
- `cascadeOnDelete` on group/race threads: deleting a group/race removes its room.
- Nav unread badge uses a real per-driver count via `ChatService`.