# Phase 5i — Shareable Group Invitations

Date: 2026-09-18. Companion audit: `docs/phase-5i-audit.md`.

## 1. Overview

Phase 5i delivers the last of the documented deferred features: **email/SMTP-
capable, shareable link-based group invitations**. A group admin or organizer
creates an invite; the system stores only a hash of a high-entropy token and
shows the raw link once (and emails it if a recipient is supplied). The invitee
opens the link, signs in, and explicitly accepts to join the group as a
**member**. Expiry is 14 days; acceptance is transactional and single-use.

Approved decisions (audit §12): shareable link/code is primary (possession of the
token is the credential, authentication still required); member role only; 14-day
expiry; SMTP configuration/testing in scope; Phase 5i only.

## 2. Architecture

| Layer | Component |
| --- | --- |
| Schema | `database/migrations/2026_09_18_000023_create_invites_table.php` |
| Enum | `App\Enums\InviteStatus` (`pending`, `accepted`, `revoked`) |
| Model | `App\Models\Invite` (+ `Group::invites()`) |
| Service | `App\Services\InviteService` (token issue/hash, create, rotate, revoke, resolve, state, transactional accept) |
| Controller | `App\Http\Controllers\InviteController` (`store`, `destroy`, `regenerate`, `show`, `accept`) |
| Request | `App\Http\Requests\StoreInviteRequest` |
| Mail | `App\Mail\GroupInvitation` + `resources/views/emails/group-invitation.blade.php` |
| Views | `resources/views/invites/show.blade.php`, `resources/views/components/invite-layout.blade.php`, edits to `resources/views/groups/members.blade.php` |
| Routing | `routes/web.php` (5 routes, 3 throttles) |
| Tests | `tests/Feature/InviteTest.php` |

All invitation rules live in `InviteService`; the controller is a thin HTTP layer,
and the model holds lifecycle helpers/scopes — matching the repository's
service/policy conventions.

## 3. Schema and token storage

```
invites:
  id          bigint pk
  group_id    bigint → groups.id   (cascade delete)
  invited_by  bigint nullable → drivers.id (null on delete)
  email       varchar(255) nullable          -- optional recipient
  token_hash  varchar(64) unique             -- sha256 hex of the raw token
  role        varchar(20) default 'member'   -- always 'member' in 5i
  status      varchar(20) default 'pending'  -- pending|accepted|revoked
  accepted_at timestamp nullable
  expires_at  timestamp
  created_at / updated_at
  index (email), index (group_id, status)
```

Token design:

- Raw token = `bin2hex(random_bytes(32))` → 64 hex chars (256 bits of entropy).
- Only `hash('sha256', $token)` is persisted (unique). **The raw token is never
  stored in the `invites` table and never logged.** Lookup is by hash with a
  defensive `hash_equals()` check.
- **`expired` is derived** from `expires_at`; no scheduler is required.
- `role` is reserved for a future phase; 5i always writes `member`.
- One-time display: after creation the raw link is placed in an **encrypted**
  session flash (`Crypt::encryptString`), so even with `SESSION_DRIVER=database`
  no plaintext token is written to the database. The members page decrypts it for
  one render; `Regenerate link` issues a fresh token.

## 4. Invitation lifecycle and expiry

1. **Create** — admin/organizer submits the invite dialog (email optional).
   `InviteService::create()` issues the token, stores the hash, sets
   `expires_at = now + 14 days`, `role = member`, `status = pending`. If an email
   was supplied, `GroupInvitation` is sent; the raw link is always flashed for
   copying.
2. **Share** — the link `…/invites/{token}` (or the raw code) is copied/shared.
3. **Landing** — `GET /invites/{token}` is public. Unknown tokens → 404. Known
   tokens render `pending` / `accepted` / `used` / `expired` / `revoked` states.
   For guests the pending state shows Sign in / Create account and records the
   invite as the session's intended URL, so login **and** registration return to
   it.
4. **Accept** — `POST /invites/{token}/accept` (auth). `InviteService::accept()`
   runs in a transaction with `lockForUpdate()`, re-checks status/expiry, attaches
   the caller's driver to `group_members` as `member` (skipped if already a
   member), and marks the invite `accepted` with `accepted_at`.
5. **Revoke / Regenerate** — admins/organizers may revoke a pending invite, or
   rotate it (old → revoked, new pending invite + fresh link).
6. **Expiry** — after `expires_at`, the invite is not acceptable and the page
   shows the expired state.

Idempotency: the same account re-accepting is a no-op success (no duplicate
pivot row). A different account on an accepted invite is rejected as `used`.

## 5. Authorization and security

- **Create/revoke/regenerate**: `GroupPolicy::manageMembers` (admin or organizer),
  exactly as member management. `destroy`/`regenerate` additionally assert
  `invite.group_id === group.id` (404 otherwise) — cross-group management is
  impossible; organizers still cannot touch admin roles (unchanged).
- **Accept**: authentication required; the invite grants **member only**; client
  `role`, `group_id`, `driver_id` inputs are ignored (never trusted).
- **Single-use / concurrency**: transactional accept with a row lock; the first
  authenticated account consumes the invite; replays are safe and never duplicate
  membership.
- **Token handling**: hash-only persistence, unique hash, 32-byte CSPRNG token,
  constant-time compare, expiry, no logging of token material, 404 for unknown
  tokens (no foreign-group leakage).
- **Rate limiting**: `invite-create` (20/min per user), `invite-show` (30/min per
  IP), `invite-accept` (5/min per user).
- **CSRF** on every state-changing form; all output Blade-escaped.
- **Data integrity**: `group_id` cascades on group delete; `invited_by`
  `nullOnDelete`; membership written through the existing pivot; the composite
  pivot PK prevents duplicate rows.

## 6. Routes and UI

| Method | URI | Name | Auth | Throttle |
| --- | --- | --- | --- | --- |
| POST | `/groups/{group}/invites` | `groups.invites.store` | auth + `manageMembers` | `invite-create` |
| DELETE | `/groups/{group}/invites/{invite}` | `groups.invites.destroy` | auth + `manageMembers` | — |
| POST | `/groups/{group}/invites/{invite}/regenerate` | `groups.invites.regenerate` | auth + `manageMembers` | — |
| GET | `/invites/{token}` | `invites.show` | public | `invite-show` |
| POST | `/invites/{token}/accept` | `invites.accept` | auth | `invite-accept` |

UI:

- Group members page (`groups/members.blade.php`): **Invite driver** dialog
  (optional email), a one-time copyable **link + code** panel after creation, a
  **Pending invitations** list with expiry, inviter, **Regenerate link** and
  **Revoke** controls (organizers/admins only).
- Public invitation landing (`invites/show.blade.php`) using the dark
  `invite-layout`: group identity, inviter/expiry, Accept button when signed in,
  Sign in / Create account when not, and clear expired/revoked/used states.
- Registration now honours the intended URL (`redirect()->intended(...)`),
  defaulting to the dashboard exactly as before.

## 7. SMTP configuration (environment-variable names only)

Mail is configured through `.env` — **never commit credentials**:

```
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_SCHEME=tls            # or null for plain/auto
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="${APP_NAME}"
# optional: MAIL_URL, MAIL_EHLO_DOMAIN
```

`config/mail.php` reads these; the default remains `MAIL_MAILER=log` for local
development and tests.

How to configure and safely test SMTP:

1. Set the `MAIL_*` values above in the local `.env` (not committed). Use an app
   password / SMTP token, not a primary credential.
2. Confirm the mailer resolves: `php artisan tinker` →
   `config('mail.default')`.
3. Send a controlled message **only to an address you control**, e.g.
   `Mail::to('you@example.test')->send(new App\Mail\GroupInvitation($group, $url, now()->addDays(14)));`
   or invite yourself from a group members page.
4. For local inspection, run a catcher (Mailpit/MailHog) and point
   `MAIL_HOST`/`MAIL_PORT` at it — no external delivery.

## 8. Tests and verification

`tests/Feature/InviteTest.php` — **26 tests / 100 assertions** covering: guest
denial, admin/organizer creation, plain-member denial, cross-group denial, token
randomness + hash-only persistence, 14-day expiry, invalid/expired/revoked
rejection, auth requirement, member-only membership, elevated-role rejection,
cross-account reuse rejection, idempotent repeat acceptance, concurrent-accept
non-duplication, invalid input, rate limiting, mailable link/expiry/recipient,
link-only (no email) creation, guest landing page, expired/revoked states,
registration intended-redirect, pending list rendering, and token rotation.

Verification results (actual):

- Targeted: `php artisan test tests/Feature/InviteTest.php` → **26 passed / 100
  assertions**.
- Full suite: `php artisan test` → **207 passed / 678 assertions** (was
  181 / 578).
- `php -l` clean on all touched PHP files.
- `vendor/bin/pint --test` on touched files → **passed**.
- `php artisan view:cache` → compiled; cache cleared afterwards.
- `php artisan db:seed --force` twice → idempotent.
- `php artisan migrate --force` → `2026_09_18_000023_create_invites_table`
  (batch **[5]**); `migrate:status` green across **26** migrations.
- `php artisan route:list` → 94 routes; the 5 invite routes are registered.

## 9. Limitations and follow-up

- **Live SMTP delivery was NOT verified in this environment.** `.env` has
  `MAIL_MAILER=log` and empty `MAIL_USERNAME`/`MAIL_PASSWORD`; no SMTP
  credentials were available. Delivery is covered by Laravel mail-testing and
  Mailable-render assertions only. Provide `MAIL_*` credentials to complete a real
  delivery test.
- **Raw link is shown once** (hash-only storage); re-sharing requires
  **Regenerate link**. This is the deliberate security trade-off.
- Shareable links are possession-based, not email-bound; anyone with the link and
  an account can accept until the invite is consumed/revoked. Recipient email is
  delivery-only.
- No bulk/CSV invites, no invite inbox/decline, no resend/reminder emails, no
  scheduled expiry command (expiry derived), no non-member roles via invite —
  all deferred.
- Global admin and Phase 5j remain out of scope.
