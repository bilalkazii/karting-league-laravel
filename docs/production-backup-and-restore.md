# Production Backup and Restore

Operating procedure for backing up and restoring the Karting database. Follow it
before storing real championship data, and rehearse restores on a **copy** — never
against the live database.

## 1. Database type and implications

- The app uses **SQLite**: a single file at `database/database.sqlite`
  (git-ignored via `database/.gitignore`, so it is never committed).
- The whole dataset is one file, which makes backup simple but also means a lost
  or corrupted file is a total loss. There is no binlog/point-in-time recovery.
- SQLite allows one writer at a time; keep backups short and avoid them during
  heavy race-control activity if copying the file directly.

## 2. Consistent backup procedure

**Preferred — online backup via the SQLite CLI (safe while the app runs):**

```sh
sqlite3 /path/to/database/database.sqlite ".backup '/backups/karting-$(date +%Y%m%d-%H%M%S).sqlite'"
```

`.backup` produces a consistent snapshot even with concurrent readers/writers.

**Fallback — coordinated file copy (app must be quiesced):**

1. Put the app in maintenance mode or briefly stop PHP-FPM so no writes occur.
2. Copy the file:
   - Linux/macOS: `cp database/database.sqlite /backups/karting-<timestamp>.sqlite`
   - Windows PowerShell: `Copy-Item -LiteralPath database\database.sqlite -Destination <backup>\karting-<timestamp>.sqlite`
3. Resume the app.
4. Record the app commit hash alongside the backup so code and data can be
   restored together.

If SQLite is later replaced by MySQL, replace this with `mysqldump` (logical) or
a host snapshot, and update this document.

## 3. Storage, retention and location

- Keep backups **outside the application directory and the public web root** so a
  compromise or accidental deploy cannot overwrite or expose them.
- Follow a 3-2-1 habit (3 copies, 2 media, 1 off-site), encrypted at rest.
- Suggested retention: daily for 14 days, weekly for 8 weeks, monthly for 12
  months. Adjust to taste.
- Store the **application key** (`APP_KEY`) separately and securely: it is needed
  to decrypt sessions and other encrypted data, but is not part of the DB file.
- Restrict filesystem/permissions on backup files to the minimum needed.

## 4. Restore rehearsal (performed on a COPY only)

Never overwrite the live database to test a restore. The rehearsal below was
**actually performed during Phase 5k on an isolated copy** and the live DB was not
touched:

1. Copy the live file to a scratch location
   (`%TEMP%\opencode\karting-restore-test.sqlite`).
2. Confirm the copy is complete (byte size identical: `286720` = `286720`).
3. Point a read-only check at the copy only, without editing `.env`:
   `DB_DATABASE=<copy-path> php artisan migrate:status` → **26 migrations, all
   "Ran"**, confirming the schema is intact.
4. Read domain counts from the copy:
   `DB_DATABASE=<copy-path> php artisan tinker --execute="..."` →
   **users=8, groups=2, races=5, race entries=23, seasons=1, season_races=5**.
5. Remove the temporary environment override; the live database is unchanged.

This validates that a backup file opens cleanly, carries the full schema, and
contains the expected race/championship records. It is a **copy-level** rehearsal,
not a full "point the running app at the restored file" exercise.

### Full restore (for a real incident)

1. Take the app offline / maintenance mode.
2. Preserve the current (possibly damaged) file for forensics.
3. Replace `database/database.sqlite` with the chosen backup file.
4. Verify: `php artisan migrate:status` (all migrations "Ran") and the counts
   above.
5. Spot-check the UI: a completed race's results and a season's standings.
6. Resume the app and re-run `config:cache`/`route:cache`/`view:cache` if needed.

## 5. Verifying a restored database

- `php artisan migrate:status` → every migration `Ran`, none `Pending`.
- Row counts for `users`, `groups`, `races`, `race_entries`, `seasons`,
  `season_races` match expectations.
- Open a completed race and confirm its classification; open its season and
  confirm standings totals.
- Confirm the demo guard means **no** `demo@karting.app` user exists in
  production.

## 6. Recovery limitations / downtime

- Restoring a file copy may require brief downtime to stop writers; the online
  `.backup` method avoids that for taking the backup, but restore still replaces
  the file and should be done with the app offline.
- No point-in-time recovery: the most recent backup loses everything written
  since it was taken.
- SQLite is not suited to high-concurrency multi-writer deployments; if that
  becomes a real need, migrate to MySQL (open decision, see the deployment guide).

## 7. Open decisions

1. Off-host backup destination and retention policy.
2. Scheduled automated backups (cron/agent) vs manual.
3. SQLite vs MySQL for production.
