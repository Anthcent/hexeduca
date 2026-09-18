# Design: School Management Foundation Scaffold

## Technical Approach
Laravel 12.64.0 / PHP 8.3 modular monolith. Approach 2: minimal core + one pattern-defining seed module (Users) + 6 empty skeletons mirroring it. Hexagonal (Domain/Application/Infrastructure) per module. Dev env via Sail (Postgres 16, Redis, MinIO). Frontend: Inertia + Vue 3 + Tailwind + Pinia. Foundation only — no business logic.

**Historical baseline**: the greenfield scaffold began on Laravel 11 and was upgraded from v11.54.0 during corrective remediation. Historical task evidence retains that fact; all normative decisions below describe the current Laravel 12 baseline.

## Architecture Decisions

### Decision: Module framework — resolved for Laravel 12
**Verification**: Packagist/Composer metadata for `nwidart/laravel-modules` v12.0.5 declares its development compatibility matrix as `laravel/framework ^v12.0` with Orchestra Testbench v10. Runtime verification covers seven enabled modules, optimized autoload, framework optimization/cache commands, migrations, and tests.
**Choice**: Require `"nwidart/laravel-modules": "^12.0"` with `laravel/framework ^12.61.1`.
**Historical note**: v11.1.10 was valid for the initial Laravel 11 scaffold but is not retained as Laravel 12 evidence.
**Fallback**: plain PSR-4 module folders remain a contingency only if a future supported package release cannot resolve; no fallback is currently needed.

### Decision: Layering contract (identical in both paths)
**Choice**: Domain = entities/value-objects/domain-events/repository interfaces (framework-agnostic, no Eloquent). Application = use-cases/commands/DTOs/service orchestration. Infrastructure = Eloquent models, repository implementations, HTTP controllers, requests, Inertia responses, providers, migrations.
**Rationale**: Dependencies point inward; Eloquent isolated in Infrastructure so domain stays testable and portable.

### Decision: Single-institution — NO tenant scoping
Load-bearing constraint: no `tenant_id` anywhere. Authorization is role + policy + Eloquent global scopes only.

## Module Layout (Users — canonical, others mirror)
```
Modules/Users/
├── module.json
├── composer.json                    (PSR-4: Modules\Users\)
├── config/config.php
├── Domain/
│   ├── Entities/User.php            (POPO, no Eloquent)
│   ├── ValueObjects/Email.php
│   ├── Events/UserRegistered.php
│   └── Repositories/UserRepositoryInterface.php
├── Application/
│   ├── UseCases/RegisterUser.php
│   ├── DTOs/UserData.php
│   └── Services/
├── Infrastructure/
│   ├── Models/User.php             (Eloquent; HasRoles, HasApiTokens)
│   ├── Persistence/EloquentUserRepository.php
│   ├── Http/Controllers/…
│   ├── Http/Requests/…
│   ├── Providers/{Users,Route,Event}ServiceProvider.php
│   └── Database/{Migrations,Seeders}/
├── Resources/js/Pages/…            (Inertia Vue pages)
└── Tests/{Unit,Feature}/
```
Skeletons (Academic, Schedule, Grades, Files, Admin, Notifications): same tree, empty stubs, no logic.

## Docker / Sail Composition
`docker-compose.yml` services:
| Service | Image/Base | Ports | Notes |
|---|---|---|---|
| app | Sail PHP 8.3 | 80, 5173 (Vite), 8080 (Reverb) | expose Reverb WS port from container |
| pgsql | postgres:16 | 5432 | volume `sail-pgsql` |
| redis | tested `redis:alpine` manifest pinned by digest | 6379 | cache/queue/session |
| minio | tested MinIO manifest pinned by digest | 9000, 8900 (console) | S3-compatible |

Horizon runs as `php artisan horizon` (sail exec or supervisor sidecar in prod). Reverb runs `php artisan reverb:start --host=0.0.0.0 --port=8080`; add `REVERB_PORT=8080` to Sail's `SAIL_XDEBUG`-style env and publish port in compose. Both are processes, not extra images.

## Package Install + Config Touchpoints
| Package | Install/config touchpoint |
|---|---|
| Sanctum | `config/sanctum.php` → `stateful` domains from `SANCTUM_STATEFUL_DOMAINS` (localhost, :5173); `EnsureFrontendRequestsAreStateful` in `api` group |
| spatie/laravel-permission | publish migrations (`permission_tables`), `config/permission.php`; seeder creates roles student/teacher/staff/super-admin |
| spatie/laravel-medialibrary | publish migration + `config/media-library.php`, disk `s3`(MinIO); no collections used yet |
| spatie/laravel-activitylog | publish migration + `config/activitylog.php`; no loggers wired yet |
| spatie/laravel-backup | `config/backup.php` → destination disk `s3`, notify off in dev |
| Horizon | `config/horizon.php`, `QUEUE_CONNECTION=redis`, `redis` queue supervisors |
| Reverb | `config/reverb.php` + `broadcasting.php` connection `reverb`; `BROADCAST_CONNECTION=reverb` |
| Pest | `pest`, `phpunit.xml`, `tests/Pest.php`, `Feature`/`Unit` suites; per-module tests discovered |

## Frontend Architecture
- `resources/js/app.js`: `createInertiaApp` + `createApp`, `createPinia()`, resolve pages from module `Resources/js/Pages` via glob.
- `resources/js/Layouts/AppLayout.vue`: base shell (nav slot + `<slot/>`), no feature screens.
- Tailwind: `tailwind.config.js` content globs include `Modules/**/Resources/js/**`.
- **Ziggy** wired for typed named routes in Vue (`@routes` Blade directive + `ziggy-js`) — avoids hardcoded URLs; recommended given many future module routes.
- Vite via `laravel-vite-plugin`, HMR on 5173.

## Security Scaffolding
- Middleware order (`api` group): `EnsureFrontendRequestsAreStateful` → throttle → `SubstituteBindings`. Laravel 12 web group keeps `ValidateCsrfToken` and `HandleInertiaRequests`.
- Sanctum SPA CSRF: SPA calls `GET /sanctum/csrf-cookie` → `XSRF-TOKEN` cookie → subsequent requests send `X-XSRF-TOKEN`; same top-level domain required.
- Named rate limiters (`AppServiceProvider::boot`): implemented `login` (5/min by email+IP) and `api` (60/min by user). Password-reset limiter is intentionally deferred because no password-reset endpoint exists in foundation scope.
- Production `TrustProxies` + force HTTPS (`URL::forceScheme('https')` when `APP_ENV=production`); HSTS via `Strict-Transport-Security` header middleware (`max-age=31536000; includeSubDomains; preload`).

## Migration / Seeder Order & Naming
Execution order (timestamp-prefixed): 1) framework core (users, cache, jobs, sessions) → 2) Sanctum personal_access_tokens → 3) permission tables → 4) media, activity_log → 5) per-module migrations (loaded after core via module providers).
Naming: `YYYY_MM_DD_HHMMSS_verb_table.php` (`create_`, `add_x_to_`, `2026_..._create_roles`). Module migrations live in `Modules/{M}/Infrastructure/Database/Migrations`.
Seeder order in `DatabaseSeeder`: `RoleAndPermissionSeeder` → `SuperAdminUserSeeder` → (future) module seeders. Roles seeded before any user assignment.

Super-admin bootstrap values flow through `config/bootstrap.php`. Email and password have no fallback; the seeder fails closed for missing/invalid credentials and requires a 16+ character mixed-case, numeric, symbolic password. Tests inject credentials through configuration rather than shipping fixture credentials.

## Testing Strategy
| Layer | Test | Approach |
|---|---|---|
| Unit | Domain entities/VOs | Pest, no DB |
| Feature | boot, auth CSRF flow, role seeder, Inertia render | Pest + RefreshDatabase (pgsql) |
| Smoke | `sail up` boots app+pg+redis+minio | manual/CI health check |

## Resolved and Deferred Decisions
- [x] Module package: nwidart v12.0.5 on Laravel 12.
- [x] Frontend: Vue 3.
- [ ] Deployment target remains unset; Reverb/Horizon production supervision stays deferred.
