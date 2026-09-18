# Spec: School Management Foundation Scaffold

## Capability: project-foundation

### Purpose
Establish a buildable Laravel 12.64.0/PHP 8.3 modular-monolith scaffold with a dockerized dev environment and empty per-module skeletons, so future feature changes replicate one established pattern. The root Composer contract MUST require PHP `^8.3`.

The initial implementation on Laravel 11 is historical; Laravel 12.64.0 is the normative current baseline.

### Requirement: Dockerized dev environment boots all services
The system MUST provide a Laravel Sail configuration that boots the app container, PostgreSQL 16, Redis, and MinIO as healthy services.

#### Scenario: Fresh boot succeeds
- GIVEN a clean clone of the project with `.env` configured from `.env.example`
- WHEN the developer runs `sail up`
- THEN app, postgres, redis, and minio containers report healthy/running status
- AND the app is reachable over HTTP on the configured local port

#### Scenario: Database connection resolves
- GIVEN Sail is running
- WHEN `sail artisan migrate` is executed
- THEN migrations run against PostgreSQL 16 without connection errors

### Requirement: Modular monolith skeleton with layered convention
The system MUST use a Laravel-12-supported `nwidart/laravel-modules` release to scaffold seven modules — Users, Academic, Schedule, Grades, Files, Admin, Notifications — each with Domain, Application, and Infrastructure directories, autoloaded via Composer/PSR-4. The resolved baseline is v12.0.5.

#### Scenario: Module skeleton present and autoloads
- GIVEN the project is installed
- WHEN `sail artisan module:list` is run
- THEN all seven modules are listed as enabled
- AND each module directory contains empty `Domain/`, `Application/`, and `Infrastructure/` subfolders
- AND classes placed under each module's namespace autoload without manual `composer dump-autoload` errors

#### Scenario: Empty modules carry no business logic
- GIVEN any module other than Users
- WHEN its directory tree is inspected
- THEN it contains no feature/business logic — only the layered skeleton and module metadata files

### Requirement: Core packages installed and baseline-configured
The system MUST install Sanctum, spatie/laravel-permission, spatie/laravel-medialibrary, spatie/laravel-activitylog, spatie/laravel-backup, Horizon, Reverb, and Pest, with each package's config file published and its service provider registered.

#### Scenario: Config files reachable
- GIVEN the packages are installed via Composer
- WHEN `sail artisan vendor:publish --tag=config` (or package-specific publish command) is run for each package
- THEN a corresponding config file exists under `config/` (e.g. `config/permission.php`, `config/media-library.php`, `config/activitylog.php`, `config/backup.php`, `config/horizon.php`, `config/reverb.php`)

#### Scenario: Horizon and Reverb reachable
- GIVEN Redis is running
- WHEN `sail artisan horizon` and `sail artisan reverb:start` are executed
- THEN both processes start without configuration errors

#### Scenario: Pest baseline is green
- GIVEN the project is freshly installed
- WHEN `sail artisan test` (Pest) is run
- THEN the baseline test suite passes with zero failures

## Capability: auth-rbac-baseline

### Requirement: Base roles and permission scaffolding are seeded
The system MUST seed four base roles — student, teacher, staff/admin, super-admin — via a database seeder using spatie/laravel-permission, with Sanctum configured for SPA authentication.

#### Scenario: Roles exist after seeding
- GIVEN a fresh database
- WHEN `sail artisan db:seed` is run
- THEN exactly the roles student, teacher, staff/admin, super-admin exist in the `roles` table
- AND no permissions beyond scaffolding placeholders are attached (no business-logic permissions)

#### Scenario: Super-admin bootstrap fails closed
- GIVEN bootstrap email or password is absent, invalid, or uses a weak placeholder
- WHEN `sail artisan db:seed` attempts to create the super-admin
- THEN seeding fails before creating that account
- AND no predictable email or password fallback is used

#### Scenario: Explicit super-admin bootstrap succeeds
- GIVEN explicit valid email and a password of at least 16 characters containing mixed case, numbers, and symbols
- WHEN `sail artisan db:seed` is run
- THEN the configured account is created or updated
- AND the `super-admin` role is assigned

#### Scenario: Sanctum SPA session issued
- GIVEN a seeded user with a valid role
- WHEN the frontend authenticates via the Sanctum SPA flow (CSRF cookie + login)
- THEN a valid session/token is issued and subsequent authenticated requests succeed

## Capability: security-baseline

### Requirement: Security middleware defaults are present and enforced
The system MUST ship default HTTPS/HSTS configuration, Sanctum SPA CSRF protection through Laravel 12 `ValidateCsrfToken`, and rate-limiting middleware applied to the implemented API/web routes, with `.env` conventions documented. The password-reset limiter MUST remain deferred until a password-reset endpoint exists.

#### Scenario: HSTS header present
- GIVEN the app is served over HTTPS in a non-local environment config
- WHEN a response is returned
- THEN the `Strict-Transport-Security` header is present

#### Scenario: CSRF enforced on stateful requests
- GIVEN an unauthenticated cross-origin POST without a valid CSRF token
- WHEN the request hits a Sanctum-protected stateful route
- THEN the request is rejected (419/403)

#### Scenario: Rate limiting enforced by default
- GIVEN the default rate limiter configuration
- WHEN a client exceeds the configured request threshold on a throttled route
- THEN subsequent requests return HTTP 429 until the window resets

### Requirement: Tenant-aware api rate limiter
The system MUST key the existing `api` rate limiter (`RateLimiter::for('api', ...)` in `app/Providers/AppServiceProvider.php`) by the resolved tenant in addition to the existing user/IP key, so each school's request budget is independent of every other school's. The key composition MUST be `school:{schoolId}|{user-or-ip}` when a tenant is resolved. A request with no resolved tenant (landlord/unresolved `school_id`) MUST fall back to the existing user/IP key unchanged. The `login` rate limiter, which is keyed by `email|ip` and runs before tenant authentication, MUST NOT be made tenant-aware by this requirement.

#### Scenario: Two tenants consume independent api buckets
- GIVEN a request resolved to school 1 and a separate request resolved to school 2, both from the same authenticated user or same IP
- WHEN school 1 exhausts its `api` rate limit
- THEN school 2's `api` requests are not throttled by school 1's exhausted bucket

#### Scenario: A single tenant's api bucket still enforces its own limit
- GIVEN a request resolved to school 1
- WHEN the number of requests from that school (for the given user/IP sub-key) exceeds the configured `api` threshold
- THEN subsequent requests from that same school/user-or-IP combination return HTTP 429 until the window resets

#### Scenario: Landlord or unresolved-tenant requests fall back to the existing key
- GIVEN a request with no resolved `school_id` (landlord host or unresolved tenant)
- WHEN the `api` rate limiter evaluates the request
- THEN it uses the pre-existing user/IP key with no tenant segment, and landlord throttling behavior is unchanged from before this change

#### Scenario: The login limiter is unaffected
- GIVEN the `login` rate limiter's existing `email|ip` key
- WHEN this change is applied
- THEN the `login` limiter's key composition and behavior remain unchanged

## Capability: frontend-shell (part of project-foundation)

### Requirement: Inertia + Vue 3 base layout renders
The system MUST scaffold Inertia.js with Vue 3, Tailwind CSS, and Pinia, providing a base layout shell with no feature screens.

#### Scenario: Base page renders
- GIVEN the frontend build is compiled (`npm run build` or dev server)
- WHEN a browser requests the root route
- THEN the Inertia-rendered Vue 3 base layout loads successfully with Tailwind styles applied
- AND no console errors occur on initial render

### Requirement: Repository-wide Pint style compliance
The system MUST pass `vendor/bin/pint --test` with zero violations across the entire repository, with no behavioral changes introduced by style fixes.

#### Scenario: Pint test passes clean
- GIVEN the repository at its current HEAD
- WHEN `vendor/bin/pint --test` is run repository-wide
- THEN the command exits 0 with no reported style violations

#### Scenario: Style fixes do not alter behavior
- GIVEN Pint auto-fixes have been applied to resolve prior violations
- WHEN the full Pest suite is run afterward
- THEN all previously passing tests still pass, with no new failures attributable to the style changes

### Requirement: Idempotent database seeding
The system MUST allow `db:seed` to be run repeatedly against the same database without producing duplicate-key errors or duplicate records, including the default test user currently created via `factory()->create()` with a fixed `test@example.com` address in `DatabaseSeeder`.

#### Scenario: Repeated seeding succeeds
- GIVEN a database that has already been seeded once
- WHEN `sail artisan db:seed` is run again
- THEN the command completes successfully with no duplicate-key or unique-constraint error
- AND exactly one user record exists for `test@example.com`

#### Scenario: Seeded test user is updated, not duplicated
- GIVEN the `test@example.com` user already exists from a prior seed run
- WHEN `db:seed` runs again
- THEN the existing record is updated in place (e.g. via `updateOrCreate` keyed on email)
- AND no second row with the same email is created

### Requirement: Idempotent object-storage bootstrap and credential-safe probe
The system MUST make the `s3` disk actually functional (the `league/flysystem-aws-s3-v3` Composer package MUST be present — confirmed absent from `composer.json` today, which makes any use of the `s3` disk fail immediately with a missing-class error regardless of bucket state), MUST provide an idempotent bootstrap step that creates the configured object-storage bucket only if it does not already exist, and a probe that performs a real upload, read, and delete against that bucket using the `s3` disk, without ever exposing credential values in output or logs. The `local` disk MUST be restored as the default `FILESYSTEM_DISK` (confirmed currently set to `s3` in the running environment, contrary to intended default); the `s3`/MinIO disk MUST be used only when explicitly targeted.

#### Scenario: S3 disk dependency is installed and resolvable
- GIVEN `composer.json` before this change has no `league/flysystem-aws-s3-v3` dependency
- WHEN the package is added and `composer install` runs
- THEN resolving the `s3` filesystem disk no longer throws a missing-class error

#### Scenario: Bucket bootstrap is idempotent
- GIVEN the configured bucket already exists
- WHEN the bootstrap step runs again
- THEN it detects the existing bucket, makes no destructive change, and exits successfully

#### Scenario: Bucket bootstrap creates a missing bucket
- GIVEN the configured bucket does not yet exist
- WHEN the bootstrap step runs
- THEN the bucket is created
- AND a subsequent run of the same step is a no-op per the previous scenario

#### Scenario: Storage probe verifies real read/write/delete
- GIVEN the bucket exists and MinIO/S3 credentials are configured
- WHEN the probe command runs against the `s3` disk
- THEN it uploads a test object, reads it back and confirms content match, then deletes it
- AND the probe's output contains no credential values (keys, secrets, tokens)

#### Scenario: Local disk remains the default
- GIVEN default application configuration with no explicit disk override
- WHEN any component resolves the default filesystem disk
- THEN it resolves to `local`, not `s3`

### Requirement: Local latency root cause documented; no code-level threshold committed
Live measurement (10 warm requests, dev stack already running 1h+) confirmed the register's original latency finding: `/up` p50 ~2.7s, `/` p50 ~3.5s, with an erratic per-request spread (0.27s–7.2s on the same endpoint under identical conditions). A live test of config/route caching as a candidate fix was performed and reverted after showing no improvement, ruling out uncached Laravel config as the cause. The erratic spread is consistent with Windows Docker Desktop bind-mount filesystem I/O contention, an infrastructure characteristic outside the application code. The system MUST document this root-cause finding with the evidence above, MUST NOT claim a numeric latency threshold as met by an application-code change, and `/up` MUST remain a lightweight health check with no added heavy dependencies (e.g. DB/cache/queue checks) regardless.

#### Scenario: Root cause is documented with live evidence
- GIVEN the live latency measurements and the reverted config/route-cache experiment
- WHEN the debt register is updated for DEBT-005
- THEN it records the measured p50s, the ruled-out hypothesis (Laravel config caching), and the identified root cause (Windows bind-mount I/O contention) as evidence

#### Scenario: DEBT-005 is not marked Resolved by this change
- GIVEN no application-code fix eliminates bind-mount I/O contention
- WHEN the debt register status is updated
- THEN DEBT-005 is set to a status reflecting root-cause-documented-but-not-code-fixable (e.g. "Accepted — infrastructure follow-up required"), not "Resolved"

#### Scenario: `/up` stays lightweight
- GIVEN the current `/up` implementation
- WHEN `GET /up` is inspected
- THEN it performs no database, cache-store, or queue connectivity checks beyond a basic process-alive response

## Out of Scope (explicit non-requirements)
Windows-like desktop module, real feature/business logic in any module, CI/CD pipeline, Postgres read replicas, table partitioning, 2FA, antivirus scanning — none of these are covered by this spec.
