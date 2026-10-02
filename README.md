# Karting League

A private web app for running a karting group's racing league â€” groups, drivers,
teams, race sessions with manual qualifying, and a full championship with
configurable points scoring.

Laravel 13 rewrite of the original Next.js prototype. It removes the
Supabase/PostgREST/Row-Level-Security surface entirely in favour of Eloquent,
first-class policies, and a relational database you can actually administer.
See [docs/](docs/) for the phase-by-phase build record and
[docs/production-deployment.md](docs/production-deployment.md) for deployment.

V1 is deliberately **software-only**: qualifying and race timing are entered
**manually** by a human with a stopwatch. There is deliberately no hardware
timing, lap counting, RFID, camera timing, radio, AI commentary, or safety-car
system.

---

## Features

**Groups & membership**
- Private karting groups with `admin` / `organizer` / `member` roles
- **Shareable invitations** â€” create, rotate, revoke. Tokens are generated with
  `random_bytes(32)`, stored **SHA-256 hashed**, and compared with
  `hash_equals()`. A transaction flips the invite to `accepted` so it is
  single-use, and an unknown token returns 404 so it cannot be used to probe
  other groups.
- Member role changes, availability, driver profile visibility preference
- **CSV driver import** with a two-step preview â†’ confirm flow. Names are
  matched by token-overlap and reordered-token similarity into three confidence
  bands, so an organizer reviews the ambiguous rows instead of guessing.

**Drivers & teams**
- Racing identity with rating, badges and aggregate stats
- Teams with full CRUD and member reassignment

**Race lifecycle** â€” a server-side state machine in `app/Services/RaceService`:

```
draft â†’ lobby â†’ qualifying â†’ grid â†’ racing â†’ completed
                                       â†˜ cancelled
```

- Lobby: check-in, ready toggles, participant set
- Qualifying: manual time entry with invalid / restore / corrected / replace
- Grid: computed from qualifying, with grid penalties and manual override
- Race control: finish, DNF, DNS, retire, withdraw, per-driver status
- Penalties: **informational only** â€” recorded and reported, never silently
  reordering a finish
- Results: classification, podium, custom round `event_label`
- Audit timeline: every state change written to an append-only `race_events` table

**Championships**
- Seasons with a configurable points table and scheduled rounds
  (`season_races` round numbers)
- Automatic or custom scoring: points by finishing position, pole points,
  fastest-lap points, participation / DNF / DNS points
- Penalty point adjustment, **off by default**
- Driver standings, team standings, position trend, 13 award types, season records

**Social & account**
- Group and race chat threads with unread watermarks and badges
- Database notifications (`RaceOpened`, `RaceCompleted`, `PenaltyIssued`) with
  per-user preferences
- Settings and profile screens

---

## Tech stack

| Layer | Choice |
| --- | --- |
| Framework | Laravel 13.32 |
| Language | PHP 8.3+ (uses PHP 8 attributes) |
| Rendering | Blade + Alpine.js 3.4 (no SPA) |
| CSS | Tailwind CSS v4 via `@tailwindcss/vite`, Vite 8 |
| Auth | Laravel Breeze (session, email/password), **invitation-only registration** |
| Authorization | 5 policies: `Group`, `Driver`, `Race`, `Season`, `Team` |
| Database | SQLite by default; MySQL / MariaDB configured for production |
| Testing | PHPUnit 12.5 â€” 31 test files, `Unit` + `Feature` suites |
| Formatting | Laravel Pint 1.32 |
| Icons | `fuzzyfox/lucide-for-laravel` |

---

## Architecture

```
app/
  Enums/            16 backed enums â€” the domain vocabulary
                    (RaceStatus, RaceFormat, GroupRole, AwardType, ...)
  Http/Controllers/ 13 domain controllers + 9 Breeze auth controllers
  Http/Requests/    18 FormRequest classes â€” all validation lives here
  Models/           22 Eloquent models
  Policies/         5 authorization policies
  Services/         RaceService (session state machine)
                    StandingsService (scoring + standings engine)
                    ChatService, DriverImportService, InviteService
  Support/          RaceUtils, DriverCsvParser, DriverCsvRow
  Listeners/        CreateDriverIdentity
  Mail/, Notifications/
routes/
  web.php           ~75 named routes
  auth.php          Breeze
resources/views/    100 Blade templates
database/
  migrations/       25 migrations
  seeders/          DemoDataSeeder, ChampionshipContentSeeder (both
                    hard-refuse to run when APP_ENV=production)
tests/              31 files â€” Unit, Feature, Auth
docs/               phase plans, audits, production runbooks
```

### Design decisions

**The state machine and the scoring engine are plain services, not controllers.**
`RaceService` and `StandingsService` are the two largest classes in the app
(~19 KB and ~21 KB) and they hold the only non-trivial logic. Controllers stay
thin, which is what makes `tests/Feature/` readable.

**No API layer.** This is a server-rendered Blade application with form POSTs.
There is no JSON API and no separate frontend to keep in sync.

**Derived data is not stored.** Stats, badges, previews, results, standings and
membership summaries are all computed at read time by `StandingsService`.

**Invitations are hashed, not stored.** Only the SHA-256 digest of an invite
token is persisted, so a database leak does not yield usable invite links.

---

## Installation

Requires **PHP 8.3+** with `ctype`, `filter`, `hash`, `mbstring`, `openssl` and
`tokenizer`, plus **Composer** and **Node.js 20+**.

```bash
git clone https://github.com/bilalkazii/karting-league-laravel.git
cd karting-league-laravel
composer setup
```

`composer setup` runs `composer install`, copies `.env.example` to `.env`,
generates an `APP_KEY`, runs migrations, then `npm install && npm run build`.

## Configuration

```bash
cp .env.example .env
php artisan key:generate
```

The defaults in `.env.example` are a working local development setup:

| Variable | Default | Notes |
| --- | --- | --- |
| `APP_KEY` | *(empty â€” must generate)* | Encrypts cookies and sessions |
| `APP_ENV` | `local` | |
| `APP_DEBUG` | `true` | **Must be `false` in production** |
| `DB_CONNECTION` | `sqlite` | Switch to `mysql` for production |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | commented out | Fill in for MySQL |
| `SESSION_SECURE_COOKIE` | *(unset)* | **Set `true` in production** or cookies go out over plain HTTP |
| `SESSION_ENCRYPT` | `false` | Consider `true` in production |
| `CACHE_STORE` / `QUEUE_CONNECTION` / `SESSION_DRIVER` | `database` | Shared-hosting friendly â€” no Redis required |
| `MAIL_MAILER` | `log` | Mail is written to the log, not sent |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_BUCKET` | *(empty)* | Only needed for S3 file storage |

Never commit `.env`. Never reuse the local `APP_KEY` in staging or production â€”
`docs/production-deployment.md` explains why it must be stable per environment
and different across environments.

## Running locally

```bash
composer dev
```

`artisan dev` runs the PHP server, the queue worker, the log tailer, and Vite
together. Then open <http://localhost:8000>.

Or run the pieces separately:

```bash
php artisan serve
npm run dev
```

### Demo account

`DemoDataSeeder` seeds a full championship dataset and creates
`demo@karting.app`. **The seeder refuses to run when `APP_ENV=production`** â€”
this is enforced by `tests/Feature/SeederGuardTest.php`. Demo credentials are
local-only; never reuse the password anywhere real.

## Testing

```bash
composer test
# or
php artisan test
```

The suite runs against an **in-memory SQLite** database with the cache, session
and queue drivers swapped to `array`, so it never touches your development
database.

```bash
./vendor/bin/pint              # format
./vendor/bin/pint --test       # check formatting without writing
```

## Building for production

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
npm ci && npm run build
```

Point the web server document root at `public/`. The full checklist, including
the `SESSION_SECURE_COOKIE` and `APP_DEBUG` requirements, the backup and restore
procedure, and the PostgreSQLâ†’MySQL migration path, is in
[docs/production-deployment.md](docs/production-deployment.md) and
[docs/production-backup-and-restore.md](docs/production-backup-and-restore.md).

---

## Project structure

| Path | Contents |
| --- | --- |
| `app/Enums/` | 16 backed enums defining the domain vocabulary |
| `app/Http/Controllers/` | Domain + Breeze auth controllers |
| `app/Http/Requests/` | 18 FormRequest validation classes |
| `app/Models/` | 22 Eloquent models |
| `app/Policies/` | Group, Driver, Race, Season, Team authorization |
| `app/Services/` | Race state machine, standings engine, chat, import, invites |
| `app/Support/` | Race timing/grid utilities, CSV name matching |
| `resources/views/` | 100 Blade templates |
| `database/` | 25 migrations, 3 seeders, 9 factories, SQLite dev DB (gitignored) |
| `tests/` | 31 PHPUnit test files |
| `docs/` | Phase plans and audits, production runbooks |

---

## Security notes

- `.env`, `vendor/`, `node_modules/`, `database/database.sqlite`,
  `storage/framework/`, `public/build/` and `bootstrap/cache/` are all
  gitignored.
- Invite tokens are stored only as SHA-256 digests.
- Passwords are bcrypt-hashed; the `User` model carries a `hashed` cast and
  `#[Hidden]` on `password` / `remember_token`.
- CSRF meta tag on both layouts, session token regenerated on login and on
  profile update.
- Three named rate limiters guard the invite routes (20/min/user to create,
  30/min/IP to view, 5/min/user to accept).
- The seeders no-op in production, enforced by a test.

## License

MIT — see [LICENSE](LICENSE).

