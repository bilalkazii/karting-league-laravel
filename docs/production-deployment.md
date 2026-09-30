# Production Deployment Guide

This guide lists the settings and steps required to run the Karting app safely in
production. It uses **environment variable names and placeholder values only** —
never commit real secrets, and never copy secrets into tickets, logs or docs.

The app currently runs on **SQLite** (see "Database" below). No database switch is
made by this document.

## 1. Application configuration

Set these in the production `.env` (created on the server, never committed —
`.env` is already git-ignored):

| Variable | Production value | Why |
| --- | --- | --- |
| `APP_ENV` | `production` | Enables production behaviour and the demo-seed guard. |
| `APP_DEBUG` | `false` | Never leak stack traces / config to users. |
| `APP_URL` | `https://your-domain.example` | Correct absolute URLs and mail links. |
| `APP_KEY` | generated once on the server (`php artisan key:generate`) | Encrypts sessions/cookies. Keep it secret and stable. |
| `LOG_CHANNEL` | `stack` (or `daily`) | Log target. |
| `LOG_LEVEL` | `warning` or `error` | Avoid storing verbose/request data in production logs. |
| `SESSION_DRIVER` | `database` | Server-side sessions. |
| `SESSION_SECURE_COOKIE` | `true` | Cookies only over HTTPS. |
| `SESSION_SAME_SITE` | `lax` | CSRF-safe default. |
| `SESSION_ENCRYPT` | `true` (recommended) | Encrypt session payloads at rest. |
| `MAIL_*` | real SMTP values when email invites are wanted | Otherwise leave `MAIL_MAILER=log`. |

`.env.example` keeps development-friendly defaults; production overrides them.
Do **not** edit `.env` as part of a code change.

## 2. HTTPS and cookies

- Terminate TLS at the host or reverse proxy and serve the app only over HTTPS.
- Set `APP_URL` to the `https://` origin and `SESSION_SECURE_COOKIE=true`; secure
  cookies are only sent over HTTPS.
- If the app runs behind a load balancer/proxy, configure trusted proxies so
  generated URLs and client IPs are correct (`bootstrap/app.php` currently has no
  `trustProxies` configuration — add it only if a proxy is in front).

## 3. Database

- Verify before deploying that the configured connection is the intended one.
- Run schema changes with `php artisan migrate --force` (never `migrate:fresh`
  against production data).
- **SQLite** is the current driver (`database/database.sqlite`, git-ignored).
  It is fine for a small private group but serialises writes and needs the backup
  procedure in `docs/production-backup-and-restore.md`.
- A **MySQL** connection block already exists in `config/database.php`. Moving to
  MySQL is an **open decision** (see §7) and requires its own migration rehearsal;
  it is not performed by this phase.

## 4. Seeding and demo data (safety guard)

- `DemoDataSeeder` and `ChampionshipContentSeeder` **refuse to run when
  `APP_ENV=production`**, even if invoked directly (e.g.
  `php artisan db:seed --class=DemoDataSeeder --force`). This prevents the known
  `demo@karting.app` credential and demo content from ever reaching production.
- On production, do **not** run `php artisan db:seed`. Create real accounts via
  the invitation flow (registration is invitation-only).
- Outside production, `php artisan db:seed` remains fully usable for local
  development.

## 5. Deploy steps

```sh
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
# serve via the host's PHP-FPM/HTTPS setup
```

After changing configuration, re-run `config:cache`. To clear during rollback:
`php artisan optimize:clear`.

## 6. Registration and access

- Public self-registration is **disabled**: registration requires a valid pending
  invitation token, and the invitee's email must match when the invite names one.
  Login, logout, password reset and invitation acceptance are unchanged.
- Driver profiles default to `public` visibility within the signed-in app; the
  documented privacy preference (`members` only) is available on `/settings`.

## 7. Open deployment decisions

1. **Database engine** — stay on SQLite or migrate to MySQL? (Affects backup and
   concurrency; see the backup doc.)
2. **SMTP** — provide real SMTP credentials for invitation emails, or rely on
   copyable shareable links only.
3. **Backup destination/retention** — where off-host backups are stored and how
   long they are kept.
4. **Trusted proxies** — only if the app sits behind a load balancer.

## 8. Never do this

- Do not commit `.env`, keys, tokens or SMTP passwords.
- Do not run `db:seed` in production.
- Do not enable `APP_DEBUG` in production.
- Do not edit the production `.env` as part of a code commit.
