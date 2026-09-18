# Apply Progress: School Management Foundation Scaffold

**Mode**: Standard (`strict_tdd: false`)
**Status**: 34/34 original tasks complete
**Corrective gate**: automatic retry 1/1 complete; no new task IDs

## Cumulative Completed Tasks

- [x] Batch 1, tasks 1.1-2.5: historical Laravel 11/PHP 8.3 scaffold, Sail services, packages, migrations, Pest, Horizon, and Reverb.
- [x] Batch 2, tasks 3.1-3.7: Users canonical module plus six layered skeleton modules.
- [x] Batch 3, tasks 4.1-5.5: migration order, RBAC, super-admin bootstrap, Sanctum, and frontend shell.
- [x] Batch 4, tasks 6.1-7.4: security middleware, genuine CSRF/session flow, domain/feature/browser tests, and build checks.

Historical implementation facts remain in `tasks.md`. Laravel 11.54.0 and nwidart v11.1.10 describe the initial scaffold only; current normative baseline is below.

## Automatic Gate Corrective Retry 1/1

### Current Runtime and Dependency Contract

- Root Composer now requires PHP `^8.3`, matching Sail PHP 8.3 and locked production dependencies.
- Laravel remains v12.64.0 under `laravel/framework:^12.61.1`; Composer audit has no ignored or active advisories.
- `nwidart/laravel-modules` upgraded v11.1.10 -> v12.0.5 under `^12.0`. Published Composer metadata explicitly uses `laravel/framework ^v12.0` and Orchestra Testbench v10 in its compatibility matrix.
- Redis and MinIO use locally tested immutable manifest digests. PostgreSQL remains explicit major `postgres:16`.

### Super-Admin Bootstrap

- Added `config/bootstrap.php`; seeder reads project configuration, not direct environment calls.
- Removed `admin@educativo.test` and `password` fallbacks.
- Email and password are mandatory. Password requires at least 16 characters with mixed case, numbers, and symbols; placeholder values are rejected.
- Seeder throws before account creation for invalid configuration and updates the explicitly configured account on valid input.
- `.env.example` documents required values without shipping credentials.
- Tests cover absent credentials, weak credentials, explicit strong credentials, password hashing, and role assignment.

### Secure Cookie Isolation

- Split local defaults from production response contract.
- Production contract runs through a separately launched PHPUnit process, boots production with `SESSION_SECURE_COOKIE` removed from all environment sources, writes a real web session, and asserts emitted `Secure`, `HttpOnly`, and `SameSite=lax` attributes.
- Normal Pest process regression asserts environment remains `testing`, secure-cookie config remains false, and no secure-cookie override leaks.
- Random-order execution passed, proving order-independent isolation.

### Playwright Determinism

- CI parsing accepts only `CI=1` or `CI=true`; values such as `CI=false` remain local mode.
- CI enables `forbidOnly`, `failOnFlakyTests`, two retries, one worker, line output, and JUnit output.
- Supplying `PLAYWRIGHT_BASE_URL` omits local `webServer`; local default reuses an existing server only outside CI.
- E2E uses semantic `main`, visible foundation copy, and computed Tailwind background behavior instead of `.min-h-screen`.
- Clean proof: removed running compose stack, executed `CI=1 npm run test:e2e`, observed Playwright start app/PostgreSQL/Redis/MinIO, and passed Chromium 1/1. Because Docker containers remained after Playwright terminated its launcher, explicit `docker compose down` removed them; normal detached development compose was then restarted and reached healthy dependency status.

### Normative Artifact Reconciliation

- Proposal, spec, design, tasks, and apply progress now describe Laravel 12.64.0, PHP `^8.3`, nwidart v12.0.5, Laravel 12 `ValidateCsrfToken`, and deferred password-reset limiter because no endpoint exists.
- Laravel 11 statements are marked historical rather than erased.
- Sentry/APM, SLO alerting, backup/restore architecture, and broad Docker readiness redesign remain explicitly out of scope.

## Verification Evidence

| Command | Result |
|---------|--------|
| `docker compose exec -u sail app php artisan test tests/Feature/FoundationTest.php tests/Feature/SecurityBaselineTest.php --stop-on-failure` | PASS — 20 tests, 72 assertions |
| `docker compose exec -u sail app php artisan test tests/Feature/SecurityBaselineTest.php --order-by=random --random-order-seed=20260714 --stop-on-failure` | PASS — 12 tests, 43 assertions |
| `docker compose exec -u sail app php artisan test` | PASS — 26 tests, 84 assertions |
| `docker compose exec -u sail app vendor/bin/pint --test config/bootstrap.php database/seeders/SuperAdminUserSeeder.php tests/Feature/FoundationTest.php tests/Feature/SecurityBaselineTest.php tests/Isolated/ProductionSessionCookieContract.php` | PASS — 5 changed PHP files |
| `docker compose exec -u sail app vendor/bin/pint --test` | WAIVED BASELINE — 29 unchanged pre-existing issues; 119 files scanned |
| `docker compose exec -u sail app composer validate --strict` | PASS |
| `docker compose exec -u sail app composer audit --locked` | PASS — no advisories |
| `docker compose exec -u sail app composer check-platform-reqs --no-dev` | PASS — PHP/php-64bit 8.3.32 and all extensions |
| `docker compose exec -u sail app composer show nwidart/laravel-modules --locked` | PASS — v12.0.5; Laravel 12 development metadata |
| `docker compose exec -u sail app composer dump-autoload --optimize` plus Users domain `class_exists` probe | PASS — 8,335 classes; module class autoloads |
| `docker compose exec -u sail app php artisan module:list` | PASS — seven enabled modules |
| `docker compose exec -u sail app php artisan optimize` then `optimize:clear` | PASS — config/events/routes/views cached and cleared |
| `docker compose exec -u sail app php artisan migrate:fresh --force` plus explicit-credential `db:seed --force` | PASS — nine migrations and bootstrap seeding |
| `docker compose exec -u sail app php artisan db:seed --force` without credentials | EXPECTED FAIL-CLOSED — email required before super-admin creation |
| `docker compose exec -u sail app npm run build` | PASS — 611 modules transformed |
| clean `docker compose down`; `CI=1 npm run test:e2e` | PASS — Playwright started stack; Chromium 1/1 |
| explicit post-test `docker compose down`; `docker compose up -d`; `docker compose ps` | PASS — clean removal, normal restart, PostgreSQL/Redis/MinIO healthy |

## Files Changed in Retry 1/1

- `.env.example`
- `composer.json`, `composer.lock`
- `config/bootstrap.php`
- `database/seeders/SuperAdminUserSeeder.php`
- `docker-compose.yml`
- `playwright.config.js`
- `tests/e2e/frontend-shell.spec.js`
- `tests/Feature/FoundationTest.php`
- `tests/Feature/SecurityBaselineTest.php`
- `tests/Isolated/ProductionSessionCookieContract.php`
- `openspec/changes/school-management-foundation/proposal.md`
- `openspec/changes/school-management-foundation/specs/project-foundation/spec.md`
- `openspec/changes/school-management-foundation/design.md`
- `openspec/changes/school-management-foundation/tasks.md`
- `openspec/changes/school-management-foundation/apply-progress.md`

## Remaining Issues

- Full-repository Pint's 29 pre-existing findings are explicitly waived for this retry; changed PHP files pass.
- `DatabaseSeeder`'s historical test-user factory is not idempotent on repeated seeding. Fresh migration plus first seed passes and this was not a confirmed corrective blocker.
- No archive, git, commit, or PR action performed.

## Gate Status

Confirmed in-scope blockers and advisories are resolved. Ready for fresh SDD verification; archive remains a later phase.
