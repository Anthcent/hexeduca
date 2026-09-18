# Technical Debt Register

This directory records accepted technical debt only when a maintainer explicitly chooses to register it. Automated reviews may suggest debt, but suggestions do not become backlog items automatically.

## How to register debt

1. Confirm the finding is real and intentionally deferred.
2. Add one row to the register with the next `DEBT-###` identifier.
3. Link evidence and define a measurable completion condition.
4. Change status to `Resolved` only after verification.

## Status values

| Status | Meaning |
|---|---|
| Proposed | Suggested, not yet accepted as debt |
| Accepted | Maintainer approved deferral |
| In progress | Remediation started |
| Resolved | Completion condition verified |
| Rejected | Finding is invalid or intentionally out of scope |

## Register

| ID | Status | Area | Debt | Evidence | Completion condition |
|---|---|---|---|---|---|
| DEBT-001 | Resolved | Code style | Full Pint baseline reported 30 pre-existing issues across 133 files (corrected count from live `pint --test`; prior register entry of 29/119 was stale). | `vendor/bin/pint --test` exits 0 across all 133 files after `vendor/bin/pint` auto-fix (default preset, no new `pint.json`); full Pest suite re-run afterward: 42 passed (135 assertions), no regressions. | `vendor/bin/pint --test` passes repository-wide. — MET. |
| DEBT-002 | Resolved | Database seeding | Historical test-user creation used raw `factory()->create()` on a fixed `test@example.com` address in `DatabaseSeeder`, causing a duplicate-key error on repeated seeding. | `DatabaseSeeder.php` now uses `User::firstOrCreate(['email' => 'test@example.com'], User::factory()->raw([...]))`; verified against Sail's `testing` Postgres database: `db:seed` run twice, both succeeded, exactly one `test@example.com` row (same `id`) after both runs. | Repeated `db:seed` runs produce deterministic records without duplicate-key failures. — MET. |
| DEBT-003 | Accepted | Production operations | Observability, backup/restore drills, deployment supervision, and broader readiness checks remain deferred. | Archived `school-management-foundation` review reports | Separate approved specifications define and verify each production capability. |
| DEBT-004 | Resolved | Object storage | `league/flysystem-aws-s3-v3` was absent from `composer.json`, making the `s3` disk throw a missing-class error regardless of bucket state; `filesystems.default` was also wrongly set to `s3` instead of `local`. | Added `league/flysystem-aws-s3-v3:^3.0` (resolved `^3.25.1`, no Flysystem conflict); `Storage::disk('s3')` now resolves to `Illuminate\Filesystem\AwsS3V3Adapter`; `FILESYSTEM_DISK` restored to `local` in `.env` (`config('filesystems.default')` = `local`); new `storage:bucket-bootstrap` command run twice against MinIO — first run created bucket `educativo`, second run detected it existing and made no change; new `storage:probe` command uploaded, read back, and deleted a real object on the `s3` disk (`PASS`), with output grepped for credential strings (`sail`, `password`, `AWS_SECRET`, `AKIA`) — none found. | Bucket creation/bootstrap is idempotent, and a real upload/read/delete probe passes without exposing credentials. — MET. |
| DEBT-005 | Accepted — root cause documented, infrastructure follow-up required | Local performance | `/` measured 4.45–16.37s and `/up` measured 3.51–10.10s despite low container usage. Live re-measurement (10 warm requests, stack running 1h+): `/up` p50 ~2.7s, `/` p50 ~3.5s, with an erratic 0.27s–7.2s spread on the same endpoint under identical conditions. A live `config:cache` + `route:cache` experiment was applied and reverted — it produced no measurable improvement, ruling out uncached Laravel config as the cause. Opcache is On and Xdebug is loaded but `XDEBUG_MODE=off` (near-zero cost), ruling those out too. The erratic same-endpoint variance is consistent with Windows Docker Desktop bind-mount (virtiofs/9p/wsl2-vhdx) filesystem I/O contention on every PHP file read — an infrastructure characteristic outside the application code, not fixable by an application-code change. `GET /up` remains Laravel's built-in lightweight health route (`bootstrap/app.php` `health: '/up'`) with no DB/cache/queue checks added. | Local runtime verification (design-phase live measurements, carried forward; no code-level fix applied in this change) | No numeric threshold is claimed as met by an application-code change. Root cause is identified and documented; `/up` confirmed to remain lightweight. Infrastructure-level remediation (WSL2-native filesystem relocation, Docker Desktop settings, AV exclusions) is tracked as a separate future concern, out of this change's scope. — NOT marked Resolved. |

## Entry template

```markdown
| DEBT-### | Accepted | Area | Concise problem statement. | File, report, issue, or command output | Objective verification condition. |
```

## Rules

- Do not register speculative reviewer findings without maintainer approval.
- Do not hide release blockers here; blockers must be fixed or explicitly accepted by the maintainer.
- Keep implementation tasks in the project task system. This register tracks deferred outcomes, not active work steps.
- Preserve resolved rows for audit history.
