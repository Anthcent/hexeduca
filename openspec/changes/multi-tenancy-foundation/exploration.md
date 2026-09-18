# Exploration: multi-tenancy-foundation

## Current State

Laravel 12.64.0 / PHP 8.3 modular monolith (nwidart/laravel-modules ^12.0), no git repo yet. 7 modules exist as empty Domain/Application/Infrastructure skeletons; only Users has real code.

Routing: `bootstrap/app.php` registers root `routes/web.php` (home + POST /login) and `routes/api.php` (`auth:sanctum` `/user`). Every module has an identical-pattern `{Module}ServiceProvider` + `RouteServiceProvider` (verified Users vs Academic side by side — same code). Each module's `RouteServiceProvider::map()` independently applies `web`/`api` middleware groups to its own routes files. This means a tenant-resolution middleware registered per-module would require touching all 7 files; registering it once in `bootstrap/app.php`'s `withMiddleware()` (alongside the existing `AddStrictTransportSecurity`/`HandleInertiaRequests` appends) is the single correct integration point.

No tenant-resolution middleware, no `Route::domain()` constraint, no landlord/tenant DB split — single connection for everything. `config/session.php` `domain` = `env('SESSION_DOMAIN')`, unset by default (no wildcard subdomain cookie scoping today). `config/sanctum.php` `stateful` is a static array (localhost variants + `Sanctum::currentApplicationUrlWithPort()`) — Sanctum's stateful-domains list is not natively wildcard-aware, a real technical risk for `*.app.com`.

Users module: `Modules\Users\Infrastructure\Models\User` (Authenticatable, `HasApiTokens, HasFactory, HasRoles, Notifiable`). Its migration lives at root `database/migrations/0001_01_01_000000_create_users_table.php` (not inside the module) and also creates `password_reset_tokens` and `sessions` — no `school_id`/`tenant_id` anywhere, confirmed.

Roles: `spatie/laravel-permission` `config/permission.php` has `'teams' => false`, but the permission migration is teams-*capable* (conditionally adds `team_id` to `roles`/`model_has_roles`/`model_has_permissions` if teams is enabled — not present in DB today since flag is false). `database/seeders/RoleAndPermissionSeeder.php` seeds exactly `student, teacher, staff/admin, super-admin` and its docblock literally says "Base roles for the single-institution school management system" — concrete evidence the current seeder design assumes single-tenant. `SuperAdminUserSeeder` exists separately, implying one global bootstrap admin — cross-tenant behavior is undecided.

Confirmed absent: `stancl/tenancy` in composer.json, any global scope in the codebase, any landlord/tenant connection split.

## Affected Areas

- `database/migrations/0001_01_01_000000_create_users_table.php` — `users` needs `school_id` FK; `sessions` has no tenant column either
- `Modules/Users/Infrastructure/Models/User.php` — needs `school_id` handling + scoping mechanism, and a decision on how `HasRoles` interacts with tenant boundaries
- `database/seeders/RoleAndPermissionSeeder.php` — docblock/semantics assume single-institution, needs explicit re-scoping decision
- `database/seeders/SuperAdminUserSeeder.php` — needs decision: landlord-level account (no `school_id`) vs per-tenant admin
- `bootstrap/app.php` — correct single registration point for tenant-resolution middleware (`withMiddleware()`), avoids duplicating across 7 module `RouteServiceProvider`s
- `config/session.php` (`domain`) + `config/sanctum.php` (`stateful`) — both need wildcard-subdomain-aware config
- All future per-module migrations (Academic, Schedule, Grades, Files, Admin, Notifications) — currently empty, but the `school_id` + global-scope convention must be established now as the pattern they'll all follow

## Approaches Considered

1. **Global middleware (registered once in `bootstrap/app.php`) + `school_id` column + Eloquent Global Scope, subdomain resolution** (agreed strategy)
   - Pros: Laravel-native, no new dependency, single registration point compatible with existing route architecture, testable
   - Cons: Global scopes are easy to bypass (raw queries, `withoutGlobalScopes()`, queue/console jobs with no request context) — needs disciplined enforcement and test coverage
   - Effort: Medium

2. **stancl/tenancy package**
   - Pros: Battle-tested subdomain identification, more automatic scope-bypass protection
   - Cons: Contradicts the already-agreed shared-DB/single-schema decision unless run in single-database mode (losing most of its value); new architectural dependency
   - Effort: Medium-High

3. **Spatie Permission "teams" feature repurposed for tenant-scoped roles** (complementary, not standalone)
   - Pros: Reuses installed infra; roles could differ per school
   - Cons: Only solves role scoping, not business-data scoping; teams columns don't exist yet (flag is false); conflates authz-team semantics with tenancy
   - Effort: Low (roles slice only)

## Recommendation

Approach 1, exactly the already-agreed strategy — confirmed compatible with the existing codebase (`bootstrap/app.php` is the right middleware registration point; nothing conflicts). Within it, resolve one real open sub-decision: keep roles GLOBAL (not team-scoped) for v1, since no business permissions exist yet — defer Spatie teams until a concrete per-school permission-difference requirement appears.

## Risks

- Global scope bypass surface (raw queries, `withoutGlobalScopes()`, queued jobs/console commands with no subdomain context) needs explicit test coverage and a documented safe-bypass pattern for super-admin
- Super-admin cross-tenant behavior is undecided — must be resolved explicitly in the proposal (landlord-only bypass vs per-tenant admin)
- Sanctum's static `stateful` domains array is not wildcard-aware — needs a concrete resolution strategy before subdomain + CSRF/session auth works end-to-end
- `sessions` table has no `school_id` — additional migration if per-tenant session scoping is ever needed
- `RoleAndPermissionSeeder`'s single-institution assumption (docblock + logic) needs revisiting for multi-school idempotency
- Zero existing tenancy-related tests or prior art in this repo — the pattern is being established from scratch

## Open Decisions for Proposal Phase

1. Roles: global vs tenant-scoped (recommend global for v1)
2. Exact super-admin cross-tenant bypass mechanism
