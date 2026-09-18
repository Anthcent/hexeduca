# Design: Resolve Foundation Technical Debt (DEBT-001, 002, 004, 005)

## Evidence Basis (read first)
This design was rewritten against **live evidence gathered by the orchestrator** directly
from the running Docker containers (`docker compose exec app ...`, `curl`). This design phase
itself had no shell/Docker tool; the numbers below are the orchestrator's ground-truth
measurements, not hypotheses. Two debts changed materially from the earlier static-only pass:
DEBT-004 (a hard missing Composer dependency, default disk is `s3` not `local`) and DEBT-005
(config/route caching proven NOT to help; reclassified to document-only).

## Technical Approach
Four independent fixes sharing only a per-file-backup rollback (no Git repo). Each debt owns a
probe that proves its corrected completion condition. DEBT-004 now includes a real dependency
addition (`composer require`); DEBT-005 becomes an evidence-backed root-cause writeup with an
infrastructure follow-up, not a code change.

## Live-Verified Findings
| Debt | Live evidence (ground truth) | Verdict |
|------|------------------------------|---------|
| 001 | `pint --test` = **30 issues / 133 files** (register said 29/119 — stale; file count grew from archived tenant-isolation change). No root `pint.json`; default preset. | CONFIRMED, count corrected |
| 002 | Not re-run live; static finding stands — `DatabaseSeeder.php` L23-26 raw `factory()->create()` with fixed `test@example.com`; other two seeders idempotent. | CONFIRMED (static) |
| 004 | `filesystems.default` = **`s3`** (not `local`). `s3` disk throws `Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found` — `league/flysystem-aws-s3-v3` is **absent from composer.json**. `AWS_BUCKET=educativo` set. Disk is non-functional today. | CONFIRMED, scope worse |
| 005 | Warm p50 ~2.7s `/up`, ~3.5s `/`, erratic (0.27s–7s same endpoint). `config:cache`+`route:cache` tested live → **no improvement**, reverted. opcache On; Xdebug loaded but `XDEBUG_MODE=off` (near-zero cost). Signature = Windows Docker Desktop bind-mount I/O contention. | ROOT CAUSE = infra, not code |

## Architecture Decisions

### Decision: DEBT-002 — idempotent test user via `firstOrCreate`
**Choice**: replace `DatabaseSeeder` L23-26 with
`User::firstOrCreate(['email' => 'test@example.com'], User::factory()->raw(['name' => 'Test User', 'email' => 'test@example.com']))`
(`User` = `Modules\Users\Infrastructure\Models\User`).
**Alternatives considered**: `updateOrCreate` (mutates `remember_token` on re-seed → non-deterministic); raw `create` without factory (loses factory-owned hashed password/columns).
**Rationale**: `email` is the natural key; matches `RoleAndPermissionSeeder`'s `firstOrCreate`; fully idempotent; `raw()` supplies the columns the factory owns.

### Decision: DEBT-001 — auto-fix on default preset, no pinned `pint.json`
**Choice**: run `vendor/bin/pint` (auto-fix) on the default preset, then re-run `--test` to 0 and run the full Pest suite to prove style-only.
**Alternatives considered**: introduce root `pint.json` pinning `laravel` preset — rejected for this change: adds a config surface + potential re-churn beyond the 30 issues; the default preset is already the effective baseline. Can be a separate hardening change.
**Rationale**: smallest reversible diff that clears the debt; suite-green proves no behavioral change.

### Decision: DEBT-004 — add missing S3 dependency, restore `local` default, bootstrap + probe commands
**Choice**:
1. `composer require league/flysystem-aws-s3-v3:"^3.0"` (matches Flysystem `^3.x` already pulled transitively by `laravel/framework ^12`; do NOT pin `^2`).
2. Set default disk back to `local`: `FILESYSTEM_DISK=local` in `.env` (config already reads `env('FILESYSTEM_DISK', 'local')` — S3 stays available, just not default).
3. New `app/Console/Commands/StorageBucketBootstrap.php` (`storage:bucket-bootstrap`) — idempotent create-if-missing on the `s3` disk client (`headBucket` → `createBucket` on 404). Manual/deploy step, never on request boot.
4. New `app/Console/Commands/StorageProbe.php` (`storage:probe`) — upload → read-back → assert-equal → delete a random temp key on the **explicitly targeted** `s3` disk (`Storage::disk('s3')`); prints PASS/FAIL only, never echoes credentials.
**Alternatives considered**: boot-time provider bucket check (per-request S3 round-trip, worsens latency); `mc mb` in Compose (not app-testable, not portable to real S3); folding probe into bootstrap (violates single-responsibility, probe must be re-runnable without re-provisioning).
**Rationale**: the disk cannot function without the package — this is a hard dependency, not empty-bucket state. Commands are idempotent, portable to real S3, additive (delete files to roll back), and keep the request path clean. `Storage::disk('s3')` is targeted explicitly so restoring `local` as default never masks the probe.

### Decision: DEBT-005 — document root cause, NO code-level latency fix
**Choice**: reclassify to **Accepted / root-cause documented / code confirmed not the cause**. Deliverable = an evidence-backed writeup: (a) warm latency measurements above, (b) proof that `config:cache`+`route:cache` did not help (tested live, reverted), (c) opcache On + Xdebug off ruled out, (d) root cause = Windows Docker Desktop bind-mount (virtiofs/9p/wsl2-vhdx) filesystem I/O contention on every PHP file read, (e) confirmation `/up` is Laravel's built-in lightweight health route (`bootstrap/app.php` `health: '/up'`) doing no extra work. Real mitigation (WSL2-native filesystem relocation, Docker Desktop settings, AV exclusions) is **infrastructure work outside this SDD change**, tracked as a follow-up.
**Alternatives considered**: config/route cache mitigation — **rejected: proven ineffective live**; `CACHE_STORE=redis` swap — not proposed as a latency fix (evidence shows app code has no lever); may have independent merit for cache-heavy paths but MUST NOT be claimed to solve latency; Octane/php-fpm — rejected by maintainer.
**Rationale**: the erratic same-endpoint variance under identical conditions is the classic bind-mount contention signature; no application code path can move the number. Committing a numeric threshold this change will hit would be dishonest given the evidence — so none is set.

## File Changes
| File | Action | Description |
|------|--------|-------------|
| `database/seeders/DatabaseSeeder.php` | Modify | `firstOrCreate` idempotent test user |
| `composer.json` / `composer.lock` | Modify | Add `league/flysystem-aws-s3-v3:^3.0` |
| `.env` (dev) | Modify | `FILESYSTEM_DISK=local` (restore local default) |
| `app/Console/Commands/StorageBucketBootstrap.php` | Create | Idempotent `storage:bucket-bootstrap` |
| `app/Console/Commands/StorageProbe.php` | Create | `storage:probe` upload/read/delete on `s3`, no secrets |
| repo-wide `*.php` | Modify | `vendor/bin/pint` auto-fix, style-only (30 issues) |
| `docs/technical-debt/README.md` | Modify | Update statuses + evidence, AFTER verify only |

## Testing / Probe Strategy (proves corrected completion condition)
| Debt | Completion proof | How |
|------|------------------|-----|
| 001 | `vendor/bin/pint --test` exits 0 repo-wide | run after auto-fix; then full Pest suite green to prove style-only (baseline: 30/133) |
| 002 | Repeated `db:seed` deterministic, no duplicate-key | run `db:seed` twice on the `testing` DB only (Sail's `create-testing-database.sql`); assert user row count stable, exit 0 |
| 004 | Package present; `s3` disk functional; idempotent bootstrap; real round-trip; no secret leak | `composer show league/flysystem-aws-s3-v3` resolves; `storage:bucket-bootstrap` twice (2nd = no-op); `storage:probe` PASS on `Storage::disk('s3')`; grep command output for `AWS_SECRET`/key values → absent |
| 005 | Evidence-backed root-cause doc + `/up` confirmed lightweight | attach warm-latency table + the cache-test-then-revert finding to the register; no threshold assertion; open infra follow-up item |

## Rollback
No Git → per-file backup before each edit.
- 001: restore backups or re-run Pint against prior state.
- 002 / `.env`: restore backup.
- 004 commands: additive → delete files; dependency → `composer remove league/flysystem-aws-s3-v3` + restore `composer.lock` backup; default disk → restore `.env`.
- 005: documentation-only, nothing to roll back.
Register updated only post-verification.

## Register Updates (post-verify)
- DEBT-001: Resolved (0 Pint issues, suite green).
- DEBT-002: Resolved (idempotent re-seed proven).
- DEBT-004: Resolved (dependency added, `local` default restored, S3 round-trip proven).
- DEBT-005: **Accepted — root cause documented, code confirmed not the cause; infrastructure-level remediation tracked separately.** (NOT "Resolved".)

## Open Questions
- [ ] Confirm `AWS_USE_PATH_STYLE_ENDPOINT=true` in `.env` so MinIO bucket ops resolve (path-style required for MinIO).
- [ ] Confirm `composer require` succeeds inside the container without a Flysystem major-version conflict (expected clean on `^3.0`).
- [ ] File the DEBT-005 infrastructure follow-up as its own tracked item (out of this change's scope).
