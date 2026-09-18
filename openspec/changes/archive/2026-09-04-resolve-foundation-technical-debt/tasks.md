# Tasks: Resolve Foundation Technical Debt (DEBT-001, 002, 004, 005)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 220-320 (Pint style diff across 30 files is mechanical/low-line-impact per file; 1 seeder line; composer.json+lock; 2 new command files; .env; 1 doc) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR (4 independent units, but each is small enough to review together) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Pint auto-fix repo-wide (DEBT-001) | PR 1 | Independent; style-only, verified by Pest suite |
| 2 | `DatabaseSeeder.php` idempotent test user (DEBT-002) | PR 1 | Independent of Unit 1; can ship in same PR |
| 3 | `league/flysystem-aws-s3-v3` dependency + `local` default + bootstrap/probe commands (DEBT-004) | PR 1 | Independent of Units 1-2 |
| 4 | DEBT-005 root-cause doc + register update (DEBT-001/002/004/005) | PR 1 | Depends on Units 1-3 being verifiable first |

## Phase 1: DEBT-001 — Pint Compliance
- [x] 1.1 Run `vendor/bin/pint --test` to confirm baseline (expect 30 issues / 133 files per design evidence). — Confirmed: 30 issues / 133 files.
- [x] 1.2 Run `vendor/bin/pint` (auto-fix, default preset, no new `pint.json`) repo-wide. — `FIXED 133 files, 30 style issues fixed`.
- [x] 1.3 Review the diff to confirm all changes are style-only (whitespace, imports, brace placement) with no logic edits. — Spot-checked `config/auth.php` (fully_qualified_strict_types import), `Modules/Users/Domain/Entities/User.php` (single_line_empty_body) — style-only.
- [x] 1.4 Run `vendor/bin/pint --test` again; confirm exit 0, zero violations (spec: "Pint test passes clean"). — `PASS 133 files`.
- [x] 1.5 Run the full Pest suite; confirm all previously passing tests still pass, no new failures (spec: "Style fixes do not alter behavior"). — 42 passed (135 assertions).

## Phase 2: DEBT-002 — Idempotent Database Seeding
- [x] 2.1 Modify `database/seeders/DatabaseSeeder.php` L23-26: replace `factory()->create()` fixed-email test user with `User::firstOrCreate(['email' => 'test@example.com'], User::factory()->raw(['name' => 'Test User', 'email' => 'test@example.com']))` (`Modules\Users\Infrastructure\Models\User`). — Done.
- [x] 2.2 Run `sail artisan db:seed` once on the `testing` database; confirm success and exactly one `test@example.com` row. — Ran against Sail's `testing` Postgres database (`migrate:fresh` + `db:seed --force`); row id=2, exactly one match.
- [x] 2.3 Run `sail artisan db:seed` a second time on the same database; confirm no duplicate-key/unique-constraint error and the row count is still 1 (spec: "Repeated seeding succeeds"). — Second run completed with no error.
- [x] 2.4 Confirm the existing `test@example.com` record is left in place, not duplicated, across the second run (spec: "Seeded test user is updated, not duplicated"). — Same `id=2` row after second run; `SELECT count(*)` = 1.

## Phase 3: DEBT-004 — S3 Dependency, Local Default, Bootstrap & Probe
- [x] 3.1 Run `composer require league/flysystem-aws-s3-v3:"^3.0"` and confirm `composer.json`/`composer.lock` update with no Flysystem major-version conflict. — Resolved `league/flysystem-aws-s3-v3` `^3.25.1` (satisfies `^3.0`), no conflict with `league/flysystem ^3.25.1`.
- [x] 3.2 Confirm resolving the `s3` disk no longer throws a missing-class error, e.g. `Storage::disk('s3')` instantiates cleanly (spec: "S3 disk dependency is installed and resolvable"). — After `composer dump-autoload` + `config:clear`, `Storage::disk('s3')` resolves to `Illuminate\Filesystem\AwsS3V3Adapter`.
- [x] 3.3 Set `FILESYSTEM_DISK=local` in `.env` (dev), restoring `local` as the default disk; confirm the default disk resolution is `local` with no explicit override (spec: "Local disk remains the default"). — `.env` backed up to `.env.backup-debt-apply`; `config('filesystems.default')` = `local`.
- [x] 3.4 Create `app/Console/Commands/StorageBucketBootstrap.php` (`storage:bucket-bootstrap`): `headBucket` on the `s3` disk client, `createBucket` only on a not-found response. — Created; uses `Aws\S3\S3Client` built from `filesystems.disks.s3` config.
- [x] 3.5 Run `storage:bucket-bootstrap` against a missing bucket; confirm it creates the bucket (spec: "Bucket bootstrap creates a missing bucket"). — Output: `Bucket "educativo" created.`
- [x] 3.6 Run `storage:bucket-bootstrap` again against the now-existing bucket; confirm it detects the bucket, makes no destructive change, exits successfully (spec: "Bucket bootstrap is idempotent"). — Output: `Bucket "educativo" already exists — no action taken.`
- [x] 3.7 Create `app/Console/Commands/StorageProbe.php` (`storage:probe`): upload a random temp key to `Storage::disk('s3')`, read it back, assert content match, then delete it; print PASS/FAIL only. — Created.
- [x] 3.8 Run `storage:probe`; confirm PASS and inspect command output/logs to confirm no credential values (keys, secrets, tokens) are present (spec: "Storage probe verifies real read/write/delete"). — Output: `PASS: upload, read-back, and delete all succeeded on the s3 disk.`; grepped output for `sail`/`password`/`AWS_SECRET`/`AKIA` — no matches.

## Phase 4: DEBT-005 — Root-Cause Documentation Only
- [x] 4.1 Confirm `GET /up` still performs no database, cache-store, or queue connectivity checks beyond a basic process-alive response (spec: "`/up` stays lightweight") — no code change expected. — Confirmed: `bootstrap/app.php` still registers `health: '/up'`, Laravel's built-in lightweight health route, untouched.
- [x] 4.2 Draft the DEBT-005 evidence writeup: warm p50s (`/up` ~2.7s, `/` ~3.5s, 0.27s-7.2s spread over 10 warm requests), the config/route-cache experiment result (tested live, no improvement, reverted), and the identified root cause (Windows Docker Desktop bind-mount filesystem I/O contention) (spec: "Root cause is documented with live evidence"). — Written into `docs/technical-debt/README.md` DEBT-005 evidence column (orchestrator's live measurements from the design phase, carried forward — no code change in this apply batch).
- [x] 4.3 Confirm the writeup does not assert any numeric latency threshold as met by an application-code change, and sets status to reflect root-cause-documented-but-not-code-fixable, not "Resolved" (spec: "DEBT-005 is not marked Resolved by this change"). — Status set to `Accepted — root cause documented, infrastructure follow-up required`, not `Resolved`.

## Phase 5: Debt Register Update
- [x] 5.1 Update `docs/technical-debt/README.md` DEBT-001 row: status `Resolved`, evidence = `vendor/bin/pint --test` exits 0 repo-wide + full Pest suite green after auto-fix. — Done.
- [x] 5.2 Update `docs/technical-debt/README.md` DEBT-002 row: status `Resolved`, evidence = repeated `db:seed` verified deterministic with no duplicate-key error. — Done.
- [x] 5.3 Update `docs/technical-debt/README.md` DEBT-004 row: status `Resolved`, evidence = `league/flysystem-aws-s3-v3` present, `local` restored as default, idempotent bootstrap verified, probe PASS with no credential leak. — Done.
- [x] 5.4 Update `docs/technical-debt/README.md` DEBT-005 row: status `Accepted — root cause documented, infrastructure follow-up required` (not `Resolved`), evidence = live latency measurements, the reverted config/route-cache experiment, and the Windows bind-mount I/O contention root cause. — Done.
- [x] 5.5 Confirm the register is updated only after Phases 1-4 verification, per the no-Git per-file-backup rollback plan. — Confirmed: register updated only after the final full Pest suite re-run (42 passed) following all Phase 1-4 changes.
