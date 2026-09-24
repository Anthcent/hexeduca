# hexeduca

Multi-tenant school management system: one school per subdomain. Laravel 12, nwidart/laravel-modules, Spatie Permission, Inertia + Vue 3, Tailwind.

@AGENTS.md

## Setup and commands

- Fresh Linux container: `bash scripts/cloud-setup.sh` (installs PHP 8.3, Composer, and dependencies, and creates `.env` + `APP_KEY`).
- Tests: `php vendor/bin/pest --testsuite=Unit,Feature,Architecture`. They use in-memory SQLite (forced in `phpunit.xml`), so no database server is needed.
- Style: `php vendor/bin/pint --test`. Frontend build: `npm run build`.
- Local Windows machine only: `php` on PATH is 8.0 — use the WinGet PHP 8.3 binary.

## Architecture rules (enforced by tests/Architecture)

- Modules live in `Modules/{Name}` with `Domain/`, `Application/`, `Infrastructure/`, `Public/`. Siblings may import only `Modules\{Other}\Public\...` and must list it in `module.json` `dependencies`. `app/` never imports `Modules\`.
- Every route uses `auth` + `module:{alias}`. The module gate returns 404 when a module is inactive or the school is not entitled; a missing role returns 403.
- Tenant data uses `App\Tenancy\Concerns\BelongsToTenant` + `school_id`. Take the tenant from `TenantContext`, never from request input.
- Cross-module side effects: an integration event in `Public/Events`, recorded with `OutboxEventRecorder` inside the same `DB::transaction`.
- New modules: follow the `create-module` skill. Scaffold with `php artisan make:project-module`, then `modules:enable {alias} --promote`.
- Tests go in `tests/Feature/{Module}` and `tests/Unit/{Module}`. `Modules/*/Tests` is NOT run by any suite.
- Never modify `tests/Architecture/**` to make code pass.

## Conventions

- Conventional commits, no AI attribution. Commit by work unit.
- Code, comments, and tests in English. UI copy in Spanish.
- Keep it simple: the app is in development, so avoid speculative abstractions.

## Local run (browser)

Tenant is resolved from the Host subdomain: landlord `admin.localhost:8000`, demo school `demo.localhost:8000`. Seeded demo users: `staff@demo.test`, `teacher@demo.test`, `student1@demo.test` (password `password`). Super-admin credentials come from the `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` env vars. Delete `public/hot` before `php artisan serve`, otherwise every page is blank.

## Known gaps (2026-09-24)

- `/api/v1/users` has no role check; FormRequests mostly `authorize() = true`.
- Sidebar is not filtered by role (teacher/student see links that return 403).
- 8 extracted module pages have no layout; Schedule, Files, and Notifications are skeletons; the legacy `Academic` module still coexists with the extracted modules.
- Validation messages are in English (no `es` locale); the dashboard shows mock data.
- Full system summary: `resumen/resumen-final.html`.
