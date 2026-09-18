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

## Capability: frontend-shell (part of project-foundation)

### Requirement: Inertia + Vue 3 base layout renders
The system MUST scaffold Inertia.js with Vue 3, Tailwind CSS, and Pinia, providing a base layout shell with no feature screens.

#### Scenario: Base page renders
- GIVEN the frontend build is compiled (`npm run build` or dev server)
- WHEN a browser requests the root route
- THEN the Inertia-rendered Vue 3 base layout loads successfully with Tailwind styles applied
- AND no console errors occur on initial render

## Out of Scope (explicit non-requirements)
Windows-like desktop module, real feature/business logic in any module, CI/CD pipeline, Postgres read replicas, table partitioning, 2FA, antivirus scanning — none of these are covered by this spec.
