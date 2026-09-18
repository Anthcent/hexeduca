# Proposal: Resolve Foundation Technical Debt (DEBT-001, 002, 004, 005)

## Intent
Four maintainer-accepted foundation debts block confident feature work: an unclean Pint baseline (DEBT-001), non-idempotent seeding (DEBT-002), an unusable object-storage bucket (DEBT-004), and unexplained local latency (DEBT-005). Resolve them now, with evidence, before any product module starts. DEBT-003 (production operations) stays deferred to its own future change.

## Scope

### In Scope
- **DEBT-001**: Make `vendor/bin/pint --test` pass repository-wide, no behavior changes.
- **DEBT-002**: Make repeated `db:seed` deterministic/idempotent (fix `DatabaseSeeder` fixed-email `factory()->create()`).
- **DEBT-004**: Idempotent bucket bootstrap for `AWS_BUCKET` + credential-safe upload/read/delete probe.
- **DEBT-005**: Root-cause + repeatable post-warm-up latency baseline meeting a maintainer-approved threshold; keep `/up` lightweight.

### Out of Scope
- **DEBT-003** (observability, backup/restore, deployment supervision, readiness) — deferred; separate approved change.
- Any Academic/Schedule/Grades/Files/Admin/Notifications feature logic.
- Production-server tuning; paid vendors; new external services.

## Capabilities

### New Capabilities
- `object-storage-bootstrap`: idempotent provisioning of the configured S3/MinIO bucket plus a credential-safe upload/read/delete storage probe (DEBT-004).

### Modified Capabilities
- `project-foundation`: add repository-wide Pint compliance (DEBT-001), deterministic/idempotent seeding (DEBT-002), and a post-warm-up local latency baseline with a lightweight `/up` health endpoint (DEBT-005).

## Approach
- DEBT-001: run Pint fix, review diff for style-only changes, gate on `--test`.
- DEBT-002: replace the raw `factory()->create()` test user with `updateOrCreate` on email (matches existing idempotent seeders).
- DEBT-004: add an idempotent Artisan bootstrap command (create-if-missing) + a probe command/test using the `s3` disk; never log secrets.
- DEBT-005: measure warm cold/warm latency for `/` and `/up`, confirm suspected root cause (`artisan serve` single-thread + Windows bind-mount + uncached config/db-cache), apply low-risk dev-side mitigation, agree threshold with maintainer.

## Affected Areas
| Area | Impact | Description |
|------|--------|-------------|
| `database/seeders/DatabaseSeeder.php` | Modified | Idempotent test user |
| `app/Console/Commands/*` (new) | New | Bucket bootstrap + storage probe |
| repo-wide `*.php` | Modified | Pint style fixes only |
| `bootstrap/app.php` / dev config | Modified | Keep `/up` lightweight; latency mitigation |
| `docs/technical-debt/README.md` | Modified | Status after verification |

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Pint auto-fix alters behavior | Low | Style-only diff review + full Pest suite |
| Bucket bootstrap leaks credentials | Med | Probe reads env only; assert no secret in output |
| Latency root cause not fully fixable locally | Med | Identify cause; agree realistic threshold; document constraints |

## Rollback Plan
No Git repo: keep per-file backups before edits. Revert seeder/config/command files individually; Pint diff is reversible; new commands are additive (delete to remove). Debt register only updated after verification.

## Dependencies
- Running Compose stack (app, pgsql, redis, minio) for live probes/latency.
- Maintainer approval of DEBT-005 threshold before apply.

## Success Criteria
- [ ] `vendor/bin/pint --test` passes repository-wide.
- [ ] Repeated `db:seed` succeeds with identical records, no duplicate-key error.
- [ ] Bucket bootstrap idempotent; upload/read/delete probe passes, no secret exposed.
- [ ] Post-warm-up `/` and `/up` meet approved threshold; root cause documented; `/up` stays lightweight.
- [ ] Full Pest suite green; debt register updated with evidence.

## Proposal question round
Live probes (bucket listing, fresh latency, `pint --test`) could not be run here — no shell/Docker tool is exposed to this phase; static code evidence grounds the findings and live runs move to spec/verify. Before finalizing, maintainer input requested on:
1. **DEBT-005 threshold**: proposed acceptance is post-warm-up p50 `/up` < 300 ms and `/` < 1 s locally, measured over 10 warm requests. Accept, or set your own numbers?
2. **DEBT-005 mitigation appetite**: acceptable to switch dev serving off single-thread `artisan serve` (e.g. Octane/php-fpm) or enable config/route cache in dev, or keep runtime as-is and only document the bind-mount root cause?
3. **DEBT-004 default disk**: keep `FILESYSTEM_DISK=local` and only ensure `s3` works on demand, or is switching any default expected? (Assumption: no default change.)
