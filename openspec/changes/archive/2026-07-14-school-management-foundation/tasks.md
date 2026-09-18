# Tasks: School Management Foundation Scaffold

**Status**: 34/34 complete after corrective gate retry (Phase 3 tasks 3.1-3.7 remain preserved as one cumulative completion row).

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 2500-4000 (7 module skeletons x ~15 files, docker compose, 7 package configs, migrations, frontend shell, tests) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | Unit 1 (env+packages) -> Unit 2 (Users module+RBAC+security) -> Unit 3 (6 skeleton modules) -> Unit 4 (frontend+tests) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

Note: project has no git/PR workflow. Treat "work units" as sequential apply batches with manual verification checkpoints, not literal PRs. Orchestrator should still ask which chain strategy (or "size:exception" as single continuous apply) before sdd-apply.

### Suggested Work Units
| Unit | Goal | Notes |
|------|------|-------|
| 1 | Docker/Sail env + core packages installed | foundation for everything else |
| 2 | Users module (canonical) + RBAC + Sanctum + security baseline | pattern-defining module |
| 3 | 6 skeleton modules mirroring Users | depends on Unit 2 pattern |
| 4 | Frontend shell + Pest baseline tests | depends on Units 1-3 |

## Phase 1: Environment Setup
- [x] 1.1 **Historical implementation**: installed Laravel 11/PHP 8.3 skeleton; corrective remediation later established Laravel 12.64.0 and root PHP `^8.3` as current baseline.
- [x] 1.2 Add Laravel Sail; configure `docker-compose.yml` services: app (PHP 8.3, ports 80/5173/8080), pgsql (postgres:16), redis (redis:alpine), minio (9000/8900).
- [x] 1.3 Configure `.env.example` with DB_CONNECTION=pgsql, REDIS_HOST, MinIO S3 vars, SANCTUM_STATEFUL_DOMAINS, REVERB_PORT=8080, QUEUE_CONNECTION=redis, BROADCAST_CONNECTION=reverb.
- [x] 1.4 **VERIFIED (batch 2, Docker now working)**: `docker compose up -d --build` brings up all 4 services (app, pgsql, redis, minio) healthy. `docker compose exec app php artisan migrate --force` runs cleanly against real PostgreSQL 16 (PHP 8.3.32 confirmed inside container). No connection errors.

## Phase 2: Core Packages
- [x] 2.1 **Historical implementation**: installed nwidart v11.1.10 for Laravel 11. Current resolved baseline is nwidart v12.0.5, whose Composer metadata explicitly tests Laravel `^v12.0`.
- [x] 2.2 `composer require laravel/sanctum spatie/laravel-permission spatie/laravel-medialibrary spatie/laravel-activitylog spatie/laravel-backup laravel/horizon laravel/reverb`; `composer require pestphp/pest --dev` + pest-plugin-laravel. All installed.
- [x] 2.3 Publish configs + migrations. **CORRECTED in batch 2**: Sanctum's `personal_access_tokens` migration was NOT actually auto-loaded from vendor as batch-1 assumed — `SanctumServiceProvider` only registers it via `publishesMigrations()`, meaning it must be physically published to run at all. Ran the REAL `php artisan vendor:publish --tag=sanctum-migrations --force` inside the container and renamed the resulting file from today's date to `2026_07_11_100000_create_personal_access_tokens_table.php` so it sorts before the permission tables per design's required order. Verified via `migrate:fresh` that all 9 migrations run in the correct order with zero errors.
- [x] 2.4 **VERIFIED (batch 2, re-confirmed batch 3)**: `docker compose exec app php artisan test` (Pest) — baseline suite passes: `Tests\Unit\ExampleTest` and `Tests\Feature\ExampleTest`, 2 passed, 0 failures.
- [x] 2.5 **VERIFIED (batch 2)**: `php artisan horizon` and `php artisan reverb:start --host=0.0.0.0 --port=8080` both start and stay running with zero configuration errors inside the real PHP 8.3 container.

## Phase 3: Module Skeleton (Users canonical) — COMPLETED in batch 2
- [x] 3.1 through 3.7 — all complete and verified in batch 2 (see Engram revision for full detail). `module:list` shows all 7 modules `[Enabled]`; `composer dump-autoload` clean (8142 classes); `migrate:fresh` + `db:seed` + `php artisan test` all pass with all 7 modules registered.

## Phase 4: Database & RBAC — COMPLETED in batch 3
- [x] 4.1 Migration ordering (framework core -> Sanctum personal_access_tokens -> permission tables -> media/activitylog tables) — done and verified in batch 2; re-verified clean in batch 3 via `migrate:fresh --force`. No per-module migrations needed yet (no module owns tables).
- [x] 4.2 Created `database/seeders/RoleAndPermissionSeeder.php` — seeds roles `student`, `teacher`, `staff/admin`, `super-admin`. No business-logic permissions attached.
- [x] 4.3 Created `database/seeders/SuperAdminUserSeeder.php`; wired seeder order in `DatabaseSeeder.php`.
- [x] 4.4 Configured Sanctum SPA flow in `bootstrap/app.php` and `routes/api.php`; runtime verified CSRF cookie and unauthenticated JSON behavior.
- [x] 4.5 Verified `db:seed --force`; exactly four roles and seeded super-admin assignment exist.

## Phase 5: Frontend Scaffold — COMPLETED in batch 3
- [x] 5.1 Installed and configured Inertia, Vue, Pinia, Ziggy, Vue Vite plugin, Tailwind forms, middleware, and page resolution.
- [x] 5.2 Created `resources/js/Layouts/AppLayout.vue` base shell.
- [x] 5.3 Added module Tailwind globs and forms plugin.
- [x] 5.4 Wired Ziggy through `@routes` and `ZiggyVue`; runtime verified named-route payload.
- [x] 5.5 Verified production frontend build and browser runtime: Playwright loads root shell, observes Tailwind-computed background style, and records zero console/page errors.

## Phase 6: Security Baseline
- [x] 6.1 Add and apply named `login` (5/min email+IP) and `api` (60/min per user/IP) rate limiters exactly once per route. `password-reset` limiter registration is deferred because no password-reset endpoint exists in foundation scope; no unused security registration is claimed.
- [x] 6.2 Configure safe-empty `TRUSTED_PROXIES` IP/CIDR allowlist + production HTTPS forcing; add HTTPS-only, non-local HSTS and production-secure session-cookie defaults.
- [x] 6.3 Verify middleware order in `api` group: `EnsureFrontendRequestsAreStateful` -> throttle -> `SubstituteBindings`; confirm Laravel 12 `web` group retains `ValidateCsrfToken` + `HandleInertiaRequests`.
- [x] 6.4 Behavior tests cover missing/invalid CSRF rejection, trusted/untrusted proxy handling, exact API quota boundary, HSTS positive/negative paths, and production cookie policy.

## Phase 7: Test Setup
- [x] 7.1 Write Pest Unit tests for `Modules/Users/Domain` entities/VOs (no DB).
- [x] 7.2 Pest Feature tests use genuine CSRF-cookie -> named-limited `/login` -> session-authenticated `/api/user` flow without `Sanctum::actingAs()`; role seeding remains covered.
- [x] 7.3 Pest verifies Inertia response and Playwright verifies browser render, applied Tailwind CSS, and zero initial console errors.
- [x] 7.4 Confirm Docker Pest suite, Playwright, corrective-scope Pint, and production build pass.

## First Post-Task Corrective Gate Remediation (historical, no new task IDs)

- Laravel upgraded from v11.54.0 to v12.64.0 under `^12.61.1`; three Composer advisory ignores removed; locked audit is clean.
- Production secure-cookie evidence now verifies an emitted session cookie is `Secure`, `HttpOnly`, and `SameSite=lax` with `SESSION_SECURE_COOKIE` absent.
- Playwright first added CI focus, retries, workers, and local Docker-server reuse; retry 1/1 below hardened CI parsing and execution proof.
- Historical verification at that checkpoint: focused Pest 15/61, full Pest 21/73, Vite build 611 modules, host Playwright 1/1, changed-file Pint pass.

## Automatic Gate Corrective Retry 1/1 (no new task IDs)

- Normative proposal/spec/design synchronized to Laravel 12.64.0, PHP `^8.3`, nwidart v12.0.5, `ValidateCsrfToken`, and deferred password-reset limiter; Laravel 11 statements retained only as historical evidence.
- Super-admin bootstrap now uses `config/bootstrap.php`, requires explicit strong credentials, and fails closed without them; tests inject deterministic credentials explicitly.
- Production cookie contract moved behind a separately launched PHPUnit process; normal-process regression proves environment/config remain testing defaults.
- Playwright uses explicit CI parsing, `failOnFlakyTests`, deterministic retries/workers, CI JUnit plus line reporters, external-base-URL server disablement, and explicit local server reuse.
- Redis and MinIO images use tested local manifest digests rather than floating tags.
- Verification: focused Pest 20/72; random-order security Pest 12/43 (seed 20260714); full Pest 26/84; targeted Pint passes 5 changed PHP files; Composer validate/audit/platform checks pass; Vite build transforms 611 modules.
- Clean CI Playwright proof: `docker compose down` removed the prior stack; `CI=1 npm run test:e2e` started all four services through Playwright webServer and passed Chromium 1/1; explicit `docker compose down` then removed the stack; normal development compose was restarted and all dependencies became healthy.
- Full-repository Pint remains explicitly waived at the unchanged baseline of 29 pre-existing style issues (119 files scanned); no broad formatting performed.

## Batch 3 Environment Gotcha
Prefer `docker compose exec -u sail app <cmd>` for Artisan commands. Root-context writes can make `storage/` and `bootstrap/cache/` unwritable by the web process; repair with `chown -R sail:sail storage bootstrap/cache` if needed.
