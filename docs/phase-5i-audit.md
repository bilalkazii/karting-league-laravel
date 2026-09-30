# Phase 5i — Invite-by-email: Audit

Date: 2026-09-18. Repository: `karting-laravel` at HEAD `34744b0` (phase 5h).
Baseline: phase 5h audit/doc confirm the deferred-feature plan; working tree clean.

This is a **planning audit only**. No application code, migration, or schema change
is made in this document.

## 1. Next feature from the documented plan

The deferred-feature ordering is first recorded in
`docs/phase-5f-chat-admin-notifications.md` §3 and re-confirmed by each later
audit:

1. **Chat** — delivered in phase 5g (`docs/phase-5g-chat.md`).
2. **Global admin** — explicitly *out of scope by design* (no user-level admin
   concept; a global tier would need its own policy/table and was rejected).
3. **Extended settings** — delivered in phase 5h (`docs/phase-5h-settings.md`).
4. **Invite-by-email** — deferred; **← next incomplete feature.**

Sources:

- `docs/phase-5f-chat-admin-notifications.md` §3 — *Invite-by-email — deferred*:
  "An email-invite flow would need an `invites` table (token, group_id, email,
  status, expires_at) — no fake flow was built."
- `docs/phase-5f-audit.md` §10.4 — "Invite-by-email flow for group onboarding —
  requires an invites table."
- `docs/phase-5h-audit.md` §1.4 and §7 — "Invite-by-email — deferred; needs an
  `invites` table." / "phase 5i candidate; not started."
- `docs/phase-5h-settings.md` §12 — "Invite-by-email — phase 5i candidate; not
  started."

The Next.js reference app corroborates the gap: its audit calls the invite system
**"UI mock only — clipboard copy of hardcoded link/code"**
(`karting-app/docs/laravel-migration-audit.md` §2), and the component is a static
`InvitePanel` with a hardcoded `https://karting.app/invite/x7Km2pQ9` and an empty
"Pending invitations" block (`karting-app/src/components/invite-panel.tsx`). No
backing store exists in either app.

**Phase 5i is invite-by-email for group onboarding. Phase 5j is not started.**

## 2. Existing implementation

- **Group membership is driver-based.** `group_members (group_id, driver_id,
  role, availability, joined_at)` with a composite primary key
  (`database/migrations/2026_09_18_000004_create_group_members_table.php`). Role is
  restricted to `admin|organizer|member` (`App\Enums\GroupRole`).
- **The only join path is manual attach.** `GroupController::addMember`
  (`app/Http/Controllers/GroupController.php:184`) authorises
  `GroupPolicy::manageMembers` (admin/organizer) and attaches an **already-
  registered** `driver_id` chosen from `availableDrivers` in
  `resources/views/groups/members.blade.php:136`. There is no email/email-token
  path, no invite table, model, service, controller, route, mail, or view.
- **Identity provisioning exists.** Registration (`RegisteredUserController`)
  fires `Illuminate\Auth\Events\Registered`, and
  `App\Listeners\CreateDriverIdentity` auto-creates `profiles` then `drivers`
  (1:1 chain). So any registered email already resolves to a driver. This is the
  hook an invite acceptance relies on.
- **Registration does not honour an intended URL.**
  `RegisteredUserController::store` hard-redirects to `route('dashboard')`
  (`app/Http/Controllers/Auth/RegisteredUserController.php:49`), unlike Breeze's
  login which uses `redirect()->intended(...)`.
- **Auth is Breeze-based and email/password only.** `MustVerifyEmail` is commented
  out in `app/Models/User.php`, so no verification gate. `routes/auth.php` has
  guest `register`/`login`/password-reset and auth `verify-email`/`confirm-
  password`/`logout`.
- **Mail infrastructure exists but is unused by the app.** `config/mail.php`
  default is `env('MAIL_MAILER', 'log')`; `.env.example` sets `MAIL_MAILER=log`.
  There is **no `app/Mail/` directory, no Mailable, and no mail Blade view**. The
  three `app/Notifications/*` classes are `database`-channel only. `QUEUE_CONNECTION=
  database` and the `jobs` table exist.
- **Reusable conventions:** `App\Enums\*` string-backed enums; FormRequest
  validation; policies for authorization; services for business logic;
  `AuthorizesRequests` in controllers; `x-app-layout`, `x-page-header`,
  `x-card`, `x-button`, `x-modal`, design tokens (`var(--panel)`, `var(--line)`,
  `var(--red)`); feature tests use `RefreshDatabase` + seeded demo identities.

## 3. Missing functionality (proposed scope of 5i)

1. **Invite storage** — an `invites` table keyed by a hashed token, scoped to a
   group and a normalised email, with status and expiry.
2. **Create invite** — admin/organizer invites an email from the group members
   page; one pending invite per `(group, email)`; rejects an email that is
   already a member or already has a pending invite.
3. **Invitation email** — a transactional Mailable containing a tokenised
   acceptance link, sent to the invitee.
4. **Pending-invite management** — the group members page lists pending invites
   with a revoke action (admin/organizer; scoped to the group).
5. **Acceptance** — an authenticated user whose email matches the invite can
   join the group as a `member`; expired / revoked / used / mismatched invites
   are rejected.
6. **Invalid/expired states** — a clear page for not-found/expired/revoked
   tokens, and a 403 for an email mismatch.
7. **Tests** — authorization, creation/validation/dedupe, mail dispatch, revoke,
   expiry, acceptance (registered user), email mismatch, already-a-member
   idempotency, and cross-group isolation.

## 4. Required migrations

| Migration | Purpose |
| --- | --- |
| `2026_09_18_000023_create_invites_table.php` | `id`, `group_id` FK→`groups` (cascade), `invited_by` FK→`drivers` (nullOnDelete), `email` varchar(255), `token_hash` varchar(64) **unique**, `role` varchar(20) default `member`, `status` varchar(20) default `pending`, `accepted_at` nullable, `expires_at`, timestamps, indexes on `email` and `(group_id, email, status)`. |

Notes:

- **Hashed token**, not plaintext — mirrors Laravel's `password_reset_tokens`
  (stores a hash) and the codebase's "never persist a usable secret" stance. The
  proposal's column name `token` is realised as `token_hash`; the plaintext token
  exists only in the emailed URL. This is the one intentional deviation from the
  5f sketch, and it is a hardening, not a scope change.
- **Bigint PK** (codebase convention), not uuid.
- `role` is fixed to `member` for 5i; the column exists so a later phase can
  allow admin/organizer invites without a migration. Inviting at a higher role is
  **not** implemented (see §9).
- `nullOnDelete` on `invited_by` preserves invite history if the inviter's driver
  is removed; `cascadeOnDelete` on `group_id` removes invites with the group.
- **No unique `(group_id, email)`** because that would block re-invites after
  accept/revoke on engines without partial indexes (MySQL). The "one pending
  invite" rule is enforced in `InviteService` (application level), portable
  across SQLite/MySQL.
- This becomes migration batch **[5]** (current total 25 → 26).

A new `App\Enums\InviteStatus` enum (`Pending`, `Accepted`, `Revoked`) is added;
`expired` is **derived** from `expires_at` rather than stored, so no background
job is required to keep state consistent.

## 5. Proposed design

### 5.1 HTTP surface

| Method | URI | Name | Authz | Purpose |
| --- | --- | --- | --- | --- |
| `POST` | `/groups/{group}/invites` | `groups.invites.store` | `GroupPolicy::manageMembers` | Create + email an invite |
| `DELETE` | `/groups/{group}/invites/{invite}` | `groups.invites.destroy` | `GroupPolicy::manageMembers` + invite belongs to group | Revoke a pending invite |
| `GET` | `/invites/{token}` | `invites.show` | `auth` (+ email match for the accept control) | Acceptance page |
| `POST` | `/invites/{token}/accept` | `invites.accept` | `auth` + email match | Join the group |

All mutating routes carry CSRF and sit behind `auth`; `GET /invites/{token}` is
behind `auth` so guests are sent to login with the invite as the intended URL,
then returned by Breeze's existing `redirect()->intended(...)`.

### 5.2 Acceptance flow

1. Organizer opens **Invite driver** on the group members page, enters an email.
2. `InviteService::invite()` normalises the email (lowercase/trim), refuses if the
   email already belongs to a member of the group or has a pending invite, issues
   a 32-byte random token (store `hash('sha256', $token)`), sets `expires_at`
   (proposed 14 days), and sends `GroupInvitation`.
3. Invitee opens `GET /invites/{token}`:
   - guest → `auth` middleware redirects to login (intended URL preserved);
   - authenticated with matching email → group summary + "Join group" form;
   - authenticated with a different email → **403**;
   - unknown / expired / revoked / accepted → dedicated invalid states (no 500).
4. `POST /invites/{token}/accept` re-validates, then inside a transaction attaches
   the caller's driver to `group_members` as `member` (if not already attached)
   and marks the invite `accepted` / `accepted_at = now()`. Redirect to
   `groups.show`.
5. A one-line, backward-compatible change to `RegisteredUserController` swaps the
   hard `route('dashboard')` redirect for `redirect()->intended(route('dashboard'))`
   so a brand-new invitee who registers mid-flow lands back on the invite page.
   Default behaviour is unchanged when there is no intended URL.

### 5.3 Components

- **Model** `App\Models\Invite` — casts (`status`→`InviteStatus`, `expires_at`/`accepted_at`→datetime), relations `group()`, `inviter()` (Driver), helpers/scopes `isPending()`, `isExpired()`, `scopePending()`, `scopeForEmail()`.
- **Service** `App\Services\InviteService` — the single home for invite rules
  (create/dedupe, revoke, token lookup by hash, transactional accept, pending
  listing), keeping the controller thin per codebase convention.
- **Controller** `App\Http\Controllers\InviteController` — `store`, `destroy`,
  `show`, `accept`.
- **Request** `App\Http\Requests\StoreInviteRequest` — `email` required, valid,
  `max:255`; normalised before rules run.
- **Mailable** `App\Mail\GroupInvitation` + `resources/views/emails/group-invitation.blade.php`.

## 6. Affected routes, models, services, policies, controllers, views, tests

- **Routes (`routes/web.php`):** the four invite routes above; no existing route
  changes.
- **Migration/model:** new `2026_09_18_000023_create_invites_table.php`,
  `app/Models/Invite.php`; optional `Group::invites()` and `Driver::sentInvites()`
  relations.
- **Enum:** new `app/Enums/InviteStatus.php`.
- **Service:** new `app/Services/InviteService.php`.
- **Controller/request:** new `app/Http/Controllers/InviteController.php`,
  `app/Http/Requests/StoreInviteRequest.php`.
- **Mail:** new `app/Mail/GroupInvitation.php`,
  `resources/views/emails/group-invitation.blade.php`.
- **Policy:** reuse `GroupPolicy::manageMembers`; no new policy tier. `destroy`
  also asserts `$invite->group_id === $group->id` (404 otherwise).
- **Views:** new `resources/views/invites/show.blade.php` (acceptance +
  invalid/expired/revoked/mismatch states); edit
  `resources/views/groups/members.blade.php` (invite dialog + pending-invites
  list with revoke); optional small email partial.
- **Auth:** one-line edit to `app/Http/Controllers/Auth/RegisteredUserController.php`
  (`intended` redirect). `CreateDriverIdentity` is unchanged and already covers
  provisioning.
- **Tests:** new `tests/Feature/InviteTest.php`. `GroupMemberManagementTest` and
  `AppShellTest` must stay green unchanged.

## 7. Security, authorization, data-integrity, backward compatibility

- **Authorization:** create/revoke require `GroupPolicy::manageMembers`
  (admin/organizer); revoke is scoped to the route's group (foreign invite →
  404). Acceptance requires authentication **and** `auth email === invite email`
  (case-insensitive), so a forwarded token cannot be redeemed under another
  account.
- **Token hygiene:** 32 random bytes; only the SHA-256 hash is stored; lookup by
  hash; single-use (status flips to `accepted`); short expiry; token never logged
  or rendered in list views.
- **No privilege escalation:** invites always grant `member`; admin/organizer
  promotion remains an explicit `GroupPolicy::manageRoles` action. The `role`
  column is reserved but not user-selectable in 5i.
- **Abuse / enumeration:** a valid token is required to see the acceptance page;
  the page reveals only the invited group's name. Invalid tokens return a generic
  invalid state. Rate-limit invite creation and acceptance (throttle) to bound
  email-bombing and token probing.
- **Input integrity:** emails normalised to lowercase/trim on write and compared
  case-insensitively; `Rule::email`; membership attach guarded by the existing
  composite pivot PK (no duplicate rows); acceptance is transactional and
  idempotent (already-member → mark accepted, no duplicate attach).
- **CSRF** on both mutating forms; all output through Blade escaping.
- **Data integrity:** cascade `group_id`; `nullOnDelete` `invited_by`;
  unique `token_hash`; emails are not a foreign key (an invite may precede the
  account).
- **Backward compatibility:** purely additive (new table/routes/enum/service/
  views/mail). The only touch to existing behaviour is the `RegisteredUserController`
  intended-URL redirect, which defaults to today's dashboard redirect. Existing
  membership, policies, and the 181-test suite are unaffected.
- **Mail delivery:** `MAIL_MAILER=log` by default; real SMTP is a deployment/env
  concern, not a code change. Tests use `Mail::fake()`.

## 8. Acceptance criteria

1. `php artisan migrate` creates `invites` and `migrate:status` is green (26
   migrations).
2. An admin or organizer can create an invite for an arbitrary valid email from
   the group members page; a Mailable is dispatched to that address.
3. A duplicate pending invite for the same `(group, email)` is rejected with a
   validation error; an email that is already a group member is rejected.
4. A plain member and a guest cannot create or revoke invites (403 / redirect).
5. Pending invites are listed with the correct group scope; revoking removes the
   invite and a revoked token can no longer be accepted.
6. An expired token cannot be accepted and shows the invalid state.
7. An authenticated user whose email matches a valid invite can accept and becomes
   a `member` of the group (one pivot row); accepting twice is idempotent.
8. An authenticated user whose email does not match the invite receives 403 and no
   membership is created.
9. A guest opening a valid invite is redirected to login and returned to the
   invite after authenticating; a new registrant is returned via the intended URL.
10. Existing tests remain green; new `InviteTest` covers the cases above.
11. `vendor/bin/pint --test` passes on all touched files; `view:cache` compiles
    the new views and email template; `db:seed` remains idempotent.

## 9. Explicitly deferred (out of 5i)

- **Shareable link/code invites** (the reference app's static `InvitePanel`
  pattern) without an email address — 5i is email-bound by design.
- **Inviting at admin/organizer roles** — members only; the reserved `role`
  column enables a later phase.
- **Resend / reminders / expiry-notification emails** and a scheduled
  `invites:expire` command — expiry is derived, no scheduler needed in 5i.
- **Bulk/CSV invites, invite inbox `/invites`, decline flow, per-invite message.**
- **Email-verification enforcement** — still disabled app-wide.
- **Global admin** and **phase 5j+** — untouched.

## 10. Decision record

- Email invites with a hashed token; the plaintext token lives only in the link.
- Acceptance is email-bound and single-use; expiry is derived from `expires_at`.
- Invites grant `member` only; no new policy tier (reuse `GroupPolicy::manageMembers`).
- One-line `intended` redirect change in registration to complete the
  register-then-join path.

## 11. Current verification snapshot

- `git status`: clean; branch `master`; HEAD `34744b0`.
- `php artisan test`: **181 passed / 578 assertions**.
- `php artisan migrate:status`: **25 migrations, all Ran** (latest batch `[4]`).
- Mail default `log`; `QUEUE_CONNECTION=database`; no `app/Mail` yet.

## 12. Approved decisions (2026-09-18) — deltas from the proposal above

The user approved implementation with these adjustments:

1. **Shareable link/code is the primary mechanism** (not strict email binding).
   Possession of a valid token is the invitation credential; **authentication is
   still required** and the raw link is shown once at creation (hash-only storage).
   Acceptance is therefore *not* restricted to a matching email — the first
   authenticated account to accept consumes the invite. An optional recipient
   email may also be supplied to deliver the link.
2. **Member role only** — invitations can never grant organizer/admin.
3. **14-day expiry** (already proposed).
4. **SMTP configuration and testing are in scope.** No SMTP credentials are
   present in `.env` (`MAIL_MAILER=log`), so live delivery cannot be verified in
   this environment; the Mailable is exercised with Laravel's mail testing
   facilities and the log/array mailers. Required SMTP env names are documented
   in `docs/phase-5i-invitations.md`.
5. Phase 5i only; Phase 5j not started.

Because the link is shareable, §5.2's email-binding step is replaced by
possession-based acceptance with the same transactional, single-use, expiry and
revocation guarantees.

Approved for implementation; see `docs/phase-5i-invitations.md` for the as-built
record.
