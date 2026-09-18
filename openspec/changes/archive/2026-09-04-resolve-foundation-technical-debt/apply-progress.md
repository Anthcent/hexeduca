# Apply Progress: Resolve Foundation Technical Debt (DEBT-001, 002, 004, 005)

**Batch**: 1 (single batch — all 5 phases, 25 tasks)
**Mode**: Standard (no strict TDD config found; no `openspec/config.yaml` present)
**Status**: 25/25 tasks complete. All phases verified with real command output.

## Completed Tasks (all phases)

### Phase 1: DEBT-001 — Pint Compliance
- [x] 1.1 Baseline confirmed: `vendor/bin/pint --test` → `FAIL 133 files, 30 style issues`.
- [x] 1.2 Auto-fix run: `vendor/bin/pint` → `FIXED 133 files, 30 style issues fixed`.
- [x] 1.3 Diff reviewed (config/auth.php, Modules/Users/Domain/Entities/User.php) — style-only (import qualification, empty-body formatting, ordered imports).
- [x] 1.4 Re-run `vendor/bin/pint --test` → `PASS 133 files`, exit 0.
- [x] 1.5 Full Pest suite → 42 passed (135 assertions), no regressions.

### Phase 2: DEBT-002 — Idempotent Database Seeding
- [x] 2.1 `database/seeders/DatabaseSeeder.php` changed to `User::firstOrCreate(['email' => 'test@example.com'], User::factory()->raw([...]))`.
- [x] 2.2 First `db:seed --force` run against Sail's `testing` Postgres database (after `migrate:fresh --force`): succeeded, row `id=2` for `test@example.com`.
- [x] 2.3 Second `db:seed --force` run on same database: succeeded, no duplicate-key/unique-constraint error.
- [x] 2.4 Confirmed same `id=2` row after second run; `SELECT count(*)` = 1.

### Phase 3: DEBT-004 — S3 Dependency, Local Default, Bootstrap & Probe
- [x] 3.1 `composer require league/flysystem-aws-s3-v3:"^3.0"` → resolved `^3.25.1`, no conflict with `league/flysystem ^3.25.1`.
- [x] 3.2 After `composer dump-autoload` + `config:clear`, `Storage::disk('s3')` resolves to `Illuminate\Filesystem\AwsS3V3Adapter` (no missing-class error).
- [x] 3.3 `.env` backed up to `.env.backup-debt-apply`; `FILESYSTEM_DISK` changed `s3` → `local`; `config('filesystems.default')` confirmed `local`.
- [x] 3.4 Created `app/Console/Commands/StorageBucketBootstrap.php` (`storage:bucket-bootstrap`) — `headBucket`/`createBucket` via `Aws\S3\S3Client` built from `filesystems.disks.s3` config.
- [x] 3.5 First run against MinIO: `Bucket "educativo" created.`
- [x] 3.6 Second run: `Bucket "educativo" already exists — no action taken.` (idempotent).
- [x] 3.7 Created `app/Console/Commands/StorageProbe.php` (`storage:probe`) — upload/read-back/assert/delete on `Storage::disk('s3')` explicitly, PASS/FAIL output only.
- [x] 3.8 Ran `storage:probe` → `PASS: upload, read-back, and delete all succeeded on the s3 disk.`; grepped output for `sail`/`password`/`AWS_SECRET`/`AKIA` — no matches (no credential leak).

### Phase 4: DEBT-005 — Root-Cause Documentation Only
- [x] 4.1 Confirmed `bootstrap/app.php` still registers `health: '/up'` (Laravel's built-in lightweight health route), untouched — no DB/cache/queue checks.
- [x] 4.2 Evidence writeup drafted into `docs/technical-debt/README.md` (warm p50s, config/route-cache experiment result, Windows bind-mount root cause) — no code change.
- [x] 4.3 Status set to `Accepted — root cause documented, infrastructure follow-up required`, explicitly not `Resolved`; no numeric threshold claimed as met by code.

### Phase 5: Debt Register Update (run LAST, after Phases 1-4 verified)
- [x] 5.1 `docs/technical-debt/README.md` DEBT-001 → `Resolved`, evidence = clean `pint --test` + green Pest suite.
- [x] 5.2 DEBT-002 → `Resolved`, evidence = repeated `db:seed` verified deterministic.
- [x] 5.3 DEBT-004 → `Resolved`, evidence = dependency present, `local` default restored, bootstrap idempotent, probe PASS with no leak.
- [x] 5.4 DEBT-005 → `Accepted — root cause documented, infrastructure follow-up required` (NOT `Resolved`), evidence = live latency measurements, reverted cache experiment, bind-mount root cause.
- [x] 5.5 Register updated only after final full Pest suite re-run (42 passed) confirmed no regressions.

## Files Changed

| File | Action | What Was Done |
|------|--------|---------------|
| `database/seeders/DatabaseSeeder.php` | Modified | `factory()->create()` → `firstOrCreate` idempotent test user (DEBT-002) |
| `composer.json` / `composer.lock` | Modified | Added `league/flysystem-aws-s3-v3:^3.0` (DEBT-004) |
| `.env` | Modified | `FILESYSTEM_DISK` `s3` → `local` (DEBT-004); backup at `.env.backup-debt-apply` |
| `app/Console/Commands/StorageBucketBootstrap.php` | Created | Idempotent `storage:bucket-bootstrap` command (DEBT-004) |
| `app/Console/Commands/StorageProbe.php` | Created | `storage:probe` credential-safe upload/read/delete probe on `s3` disk (DEBT-004) |
| 133 files repo-wide (`Modules/**`, `config/**`, `database/**`, `bootstrap/providers.php`) | Modified | `vendor/bin/pint` auto-fix, style-only, 30 issues fixed (DEBT-001) |
| `docs/technical-debt/README.md` | Modified | DEBT-001/002/004 → `Resolved`; DEBT-005 → `Accepted — root cause documented, infrastructure follow-up required` (Phase 5) |
| `openspec/changes/resolve-foundation-technical-debt/tasks.md` | Modified | All 25 tasks marked `[x]` with evidence notes |

## Deviations from Design

None — implementation matches design. One clarification: the earlier Postgres row observed for `test@example.com` had `id=2` both before and after the second seed run (id=1 belongs to the `super-admin` seeded user), confirming no new row was inserted.

## Issues Found

- The `s3` disk initially still threw `Class "Aws\S3\S3Client" not found` and `PortableVisibilityConverter not found` immediately after `composer require` completed, even though the package existed under `vendor/`. Root cause: stale Composer autoloader / cached Laravel config from before the install finished. Resolved by running `composer dump-autoload` followed by `php artisan config:clear` and `cache:clear`. No design deviation — this is an operational sequencing detail, not a code change.
- Docker exec commands were frequently slow enough to auto-background (consistent with the DEBT-005 finding of Windows bind-mount I/O contention); this only affected local execution timing, not the outcome of any task.
- The `SuperAdminUserSeeder` (unrelated to DEBT-002 but part of the same `DatabaseSeeder::run()` chain) requires `SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD`/`SUPER_ADMIN_NAME` env vars to avoid failing closed. These were supplied only as ephemeral `docker compose exec -e` overrides for the two `db:seed` verification runs against the `testing` database — not written to `.env`, no lasting change.

## Remaining Tasks

None — all 25 tasks across 5 phases complete.

## Workload / PR Boundary

- Mode: single PR (per tasks.md forecast: Low risk, 220-320 lines, no chaining)
- Current work unit: all 4 units (DEBT-001, 002, 004, 005 doc) — delivered together as designed
- Boundary: full change from Pint baseline confirmation through final debt-register update
- Estimated review budget impact: within the 400-line budget; largest single contributor is the repo-wide Pint diff, which is mechanical/low-line-impact per file

## Final Verification

Full Pest suite re-run after ALL changes (Phases 1-4 + register update): **42 passed (135 assertions)**, `Duration: 144.24s`. No failures, no regressions.

## Status

25/25 tasks complete. Ready for verify.
