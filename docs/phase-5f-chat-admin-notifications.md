# Phase 5f — Chat, Admin & Notifications

Date: 2026-09-18. Companion audit: `docs/phase-5f-audit.md`.

## 1. Overview

Phase 5f delivers the group-administrative surface the UI already promised and a
real database-backed notification system. Chat and extended settings are **not**
implemented because neither has schema support; the audit (linked above) documents
this per-feature, and this doc records the concrete schema proposals that would
unlock them later.

## 2. What shipped

### Notifications (fully implemented)

- **New migration** `2026_09_18_000019_create_notifications_table.php` — the
  canonical Laravel `notifications` table: `{id: uuid, type, notifiable morphs,
  data: text, read_at, timestamps}`. SQLite and MySQL compatible.
- **Notification classes** (`app/Notifications/`):
  - `RaceOpened` — sent to every group member when a race moves draft → lobby.
  - `PenaltyIssued` — sent to the affected driver when a penalty is issued.
  - `RaceCompleted` — sent to every driver entered in a race when it completes.
  All store `title`, `body`, `race_id`, and `url` in `data`.
- **`NotificationController`** (`app/Http/Controllers/NotificationController.php`):
  - `index` — paginated list of the authenticated user's notifications (+ unread count).
  - `markRead` — marks one notification read; ownership-scoped (404 for foreign ids).
  - `markAllRead` — bulk marks the user's unread set read.
- **Routes** (`routes/web.php`): `GET /notifications`,
  `POST /notifications/read-all`, `POST /notifications/{notification}/read`.
- **View** `resources/views/notifications/index.blade.php` — paginated list with
  unread highlight, diff-for-humans timestamps, a "View race" link back to the
  race, and per-item/bulk mark-read controls.
- **Header bell** (`layouts/app.blade.php`): the red dot is now driven by the real
  unread count instead of a hardcoded always-on dot.
- **Service hooks** (`app/Services/RaceService.php`): `openLobby()`,
  `completeRace()`, and `issuePenalty()` each dispatch their notification after the
  DB transaction succeeds. Targets resolve via driver → profile → user.

### Group administration (fully implemented)

All mutations are scoped to the route's `{group}` and authorised by policy.

- **`GroupPolicy::manageRoles`** (new) — role changes are **admin-only**.
  `manageMembers` (add/remove) stays open to admins and organizers.
- **`GroupController`**:
  - `addMember` — attach an existing driver with role `member`, availability
    `available`. Rejects drivers already in the group with 422.
  - `updateMemberRole` — change admin/organizer/member. Refuses 422 when the last
    active admin would be demoted. 404 for non-members of the group.
  - `removeMember` — detach. Refuses 422 when the last admin would be removed.
- **Routes**: `POST /groups/{group}/members` (`groups.members.store`),
  `PATCH /groups/{group}/members/{driver}/role` (`groups.members.role`),
  `DELETE /groups/{group}/members/{driver}` (`groups.members.destroy`).
- **View** `resources/views/groups/members.blade.php` — the decorative remove/block
  buttons are gone. In their place: a working "Add member" dialog (driver picker,
  empty when everyone is already in), a role selector (admins only), and a working
  remove action (admins + organizers, except the group's last admin). Organizers
  cannot modify admins.

### Navigation (cleaned up)

- **Sidebar / bottom nav / group tabs**: the `/chat` and `/settings` links were
  replaced with the real **Drivers** destination; the sidebar footer now points to
  **Account** (`/account`) instead of `/settings`; the fake Chat unread badge is
  gone.
- **`feature-placeholder.blade.php`**: `chat` and `settings` entries now state
  they are deferred with pointers to this document; `notifications` was removed.

## 3. Deferred features (no schema support) + proposals

### Chat — deferred

No messages/chats table exists (audit §1). Proposal:

```
chat_messages:
  id           uuid pk
  group_id     fk → groups.id (nullable for race-room threads)
  race_id      fk → races.id   (nullable)
  sender_id    fk → drivers.id
  body         text
  created_at   timestamp
```

Requirements before build: chat thread scoping (group/race), read receipts or
last-read watermark, and (if live updates are wanted) websocket/broadcast — the
app is currently pure server-rendered Blade. `/chat` remains a clear deferred
placeholder and is not in navigation.

### Global admin — out of scope by design

Roles are group-scoped (`group_members.role`). There is no user-level admin flag,
so a global admin panel (user listing, impersonation, platform settings) is not
implemented. The group admin surface is the supported elevation model. A future
global admin would require a `users.is_admin` boolean or an `admins` table plus a
separate policy tier.

### Extended settings — deferred

`/account` already covers profile/driver settings. Theme, notification, and
privacy preferences have no backing columns. Proposal: add a
`user_preferences` (user_id pk, key, value) or preference columns on `users`.
`/settings` remains a clearly-marked deferred placeholder.

### Invite-by-email — deferred

Joining a group currently requires either the creator adding the driver or the
driver being added manually. An email-invite flow would need an
`invites` table (token, group_id, email, status, expires_at) — no fake flow was
built.

## 4. Security

- Role changes and membership mutations are authorised per-group via
  `GroupPolicy` (`manageRoles` = admin only, `manageMembers` = admin|organizer).
- Organizers cannot change roles; admins are protected by the last-admin guard
  on both demote and remove.
- Notifications are ownership-scoped: `markRead` returns 404 for notification ids
  that do not belong to the caller; listing only returns the caller's rows.
- Every mutating form (add/remove/role/read/read-all) posts with CSRF tokens.
- All rendered content passes through Blade escaping; notification `data` is
  treated as opaque JSON from our own classes.

## 5. Tests

- New `tests/Feature/GroupMemberManagementTest.php` (12 tests): guest redirects,
  admin/organizer/member capabilities, duplicate-add rejection, last-admin
  guard on demote and remove, foreign-driver 404, promotion/demotion flows.
- New `tests/Feature/NotificationTest.php` (10 tests): guest redirects, list
  rendering, single/bulk mark-read, cross-user read protection, and
  service-driven dispatch for RaceOpened (all group members), RaceCompleted
  (entered drivers only), and PenaltyIssued (target driver only).
- Full suite: **153 passed / 480 assertions** (was 131 / 401).

## 6. Files

New: migration `2026_09_18_000019`, `app/Notifications/{RaceOpened,PenaltyIssued,RaceCompleted}.php`,
`app/Http/Controllers/NotificationController.php`, `resources/views/notifications/index.blade.php`,
`tests/Feature/{GroupMemberManagementTest,NotificationTest}.php`, `docs/phase-5f-audit.md`.

Modified: `GroupPolicy`, `GroupController`, `RaceService`, `routes/web.php`,
`groups/members` view, `layouts/app`, `sidebar`, `bottom-nav`, `group-tabs`,
`feature-placeholder`.