# Proposal: School Management Foundation Scaffold

## Intent
Greenfield single-institution school management system. Before any feature can be built, the project needs a consistent, secure architectural foundation. This change stands up the buildable scaffold (Laravel 12 modular monolith + dev environment + core packages + module skeletons) so every future feature change replicates ONE established pattern instead of inventing structure ad hoc. No business logic — foundation only.

**Historical baseline**: the original scaffold was implemented on Laravel 11 and later upgraded from v11.54.0 to the current Laravel v12.64.0 baseline during corrective security remediation.

## Scope
### In Scope
- Laravel 12.64.0 / PHP 8.3 baseline with root Composer constraint `php:^8.3`; Laravel Sail (Docker: app, Postgres 16, Redis, MinIO)
- Modular monolith via nwidart/laravel-modules; per-module Domain/Application/Infrastructure layout
- Empty skeleton modules: Users, Academic, Schedule, Grades, Files, Admin, Notifications
- Core packages installed + baseline config (NOT full impl): Sanctum, spatie/laravel-permission, medialibrary, activitylog, backup, Horizon+Redis, Reverb, Pest
- PostgreSQL 16 base connection + read/write split scaffolding (structure only)
- Base roles/permissions seeders: student, teacher, staff/admin, super-admin; super-admin bootstrap credentials are explicit and fail closed when absent or weak
- Frontend scaffold: Inertia + Vue 3 + Tailwind + Pinia; base layout shell (no feature screens)
- Security scaffolding: HTTPS/HSTS, Sanctum SPA CSRF flow, rate-limit middleware defaults, .env conventions documented

### Out of Scope (future changes)
- Windows-like desktop/windowing module (deferred to own change)
- Any grade/schedule/attendance business logic; real-time notification features
- Production infra/CI-CD, hosting, deploy pipeline
- Read replica wiring, table partitioning implementation
- 2FA implementation, antivirus scanning
- Sentry/APM, production SLO alerting, backup/restore architecture, and broad Docker readiness redesign

## Capabilities
### New Capabilities
- `project-foundation`: Laravel modular monolith scaffold, module skeletons, dev environment
- `auth-rbac-baseline`: Sanctum + spatie roles/permissions structure and seeders
- `security-baseline`: HTTPS/HSTS, CSRF, rate limiting, secrets/.env conventions
### Modified Capabilities
- None (greenfield)

## Approach
Exploration Approach 2: minimal core scaffold + one pattern-defining seed module (Users, since auth depends on it) + generated empty skeletons for the rest. Establish the hexagonal-per-module convention once; future changes replicate it.

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Module package/framework compatibility | Resolved | `nwidart/laravel-modules` v12.0.5 metadata tests against `laravel/framework ^v12.0`; runtime module/cache/migration verification required |
| Vue 3 assumption (React not confirmed) | Med | Documented assumption, revisitable before frontend scaffold |
| Empty module over-engineering | Low | Skeletons only, no logic |

## Rollback Plan
No git in use. Rollback = delete generated project files/containers; Engram artifacts remain. Nothing in production.

## Dependencies
- Docker/Sail available locally; Composer/Node toolchain

## Success Criteria
- [x] `sail up` boots app + Postgres + Redis + MinIO
- [x] 7 module skeletons present with Domain/Application/Infrastructure layout
- [x] Role/permission seeders run; base roles exist; super-admin bootstrap requires explicit strong credentials
- [x] Inertia+Vue3 shell renders; Pest baseline is green

## Open Questions (resolve before/during design)
- Module package resolved: `nwidart/laravel-modules` v12.0.5 for Laravel 12
- Deployment target + CI/CD tooling (unconfirmed)
- Legacy data/spreadsheet import tooling needed later?
- Frontend framework resolved: Vue 3
