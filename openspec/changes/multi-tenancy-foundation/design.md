# Design: Multi-Tenancy Foundation

## Technical Approach
Shared database, single schema, subdomain-identified tenancy with a `school_id` column + Eloquent global scope (proposal Approach 1, all 5 open questions confirmed as defaults). Tenancy is cross-cutting infrastructure consumed by `bootstrap/app.php` and every business module, so it lives at **app level under `App\Tenancy`** — NOT in a business module and NOT in a new `Modules/Tenancy` module. Rationale below. No new Composer package. Isolation is opt-out: the global scope applies unconditionally; only an explicit landlord path (`School::withoutTenantScope()`) crosses tenants.

## Architecture Decisions

### Decision: Tenancy core placement — `App\Tenancy` (app-level), not a module
**Choice**: `App\Tenancy\{Models\School, Scopes\TenantScope, Concerns\BelongsToTenant, TenantContext, Http\Middleware\ResolveTenant}`.
**Alternatives considered**: (a) `Modules/Tenancy` nwidart module; (b) `School` inside `Modules/Admin`.
**Rationale**: The middleware already registers in `bootstrap/app.php` (app-level), and `BelongsToTenant`/`TenantContext` are app-level primitives — `School` belongs beside them. A `Modules/Tenancy` module adds `module.json`, providers, and enablement ceremony for zero domain logic, and forces every business module to declare an inbound dependency on another module. `App\Tenancy` is always autoloaded (PSR-4 `App\` → `app/`), keeps the modular-monolith dependency direction clean (modules → shared app core, never module → module), and matches the existing `App\Support`/`App\Http\Middleware` convention. School is infrastructure, not a business aggregate, so hexagonal per-module layering does not apply.

### Decision: Global scope is unconditional; landlord bypass is explicit
**Choice**: `TenantScope::apply()` filters `where school_id = TenantContext::current()->id` only when a tenant is bound; landlord crosses via `Model::withoutTenantScope()` (thin wrapper over `withoutGlobalScope(TenantScope::class)`).
**Alternatives considered**: an `if (auth()->user()?->isSuperAdmin()) return;` branch inside the scope.
**Rationale**: An implicit role check makes every query silently trust the current role — one mis-set role or impersonation bug leaks all tenants. Explicit opt-out keeps isolation the default and makes cross-tenant reads few, grep-able, and individually test-covered.

### Decision: Nullable `users.school_id` as landlord marker; roles stay global
**Choice**: `school_id` nullable FK; `NULL` = landlord. `permission.teams` stays `false`. User uses `BelongsToTenant` but the scope no-ops when no tenant is bound (landlord host), so a landlord row is never scoped away from itself.
**Rationale**: Confirmed proposal defaults 3 + 4. Landlord is a rare platform account, not worth a second table; global roles suffice because the *user row and its data* are scoped even though the role label is shared.

## TenantContext + host classification
`ResolveTenant` classifies the request host into three classes and binds accordingly:

    Request host
        │
        ├─ landlord host (admin.app.com | bare app.com | APP_LANDLORD_HOSTS) ──► no tenant bound → landlord context (scope no-ops)
        ├─ tenant host (<label>.app.com, active School)              ──► TenantContext::set(school) singleton bind
        └─ unrecognized (unknown/inactive subdomain, foreign host)   ──► abort(404) — FAIL CLOSED

Subdomain extraction: strip the configured base domain (`config('tenancy.base_domain')`, e.g. `app.com`) suffix from `$request->getHost()`; the remaining leftmost label is the subdomain. `www` and empty label → treated as landlord/none, never a tenant lookup. Lookup: `School::query()->where('subdomain', $label)->where('is_active', true)->first()`, executed with the scope disabled (schools table itself is not tenant-scoped).

Binding: `app()->scoped(TenantContext::class, ...)` holds the resolved `School`; helper `current_tenant(): ?School` and `TenantContext::current()/set()/hasTenant()` expose it. The scope reads `TenantContext` at query-build time.

## File Changes
| File | Action | Description |
|------|--------|-------------|
| `app/Tenancy/Models/School.php` | Create | Eloquent `School`; `$fillable = ['name','subdomain','is_active']`; `is_active` bool cast; `withoutTenantScope()` static helper for landlord reads |
| `app/Tenancy/Concerns/BelongsToTenant.php` | Create | Trait: `bootBelongsToTenant()` adds `TenantScope` + `creating` hook stamping `school_id`; `scopeWithoutTenantScope()` |
| `app/Tenancy/Scopes/TenantScope.php` | Create | `implements Scope`; `apply()` adds `school_id` filter only when `TenantContext::hasTenant()` |
| `app/Tenancy/TenantContext.php` | Create | Holds current `School`; `set/current/hasTenant/forget` |
| `app/Tenancy/Http/Middleware/ResolveTenant.php` | Create | Host classification + container bind + fail-closed 404 |
| `app/Tenancy/helpers.php` (or provider) | Create | `current_tenant()` accessor |
| `config/tenancy.php` | Create | `base_domain`, `landlord_hosts` (env-driven) |
| `database/migrations/2026_09_02_000000_create_schools_table.php` | Create | `id, name, subdomain (unique index), is_active (default true), timestamps` |
| `database/migrations/0001_01_01_000000_create_users_table.php` | Modify | Add `foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete()->index()` |
| `Modules/Users/Infrastructure/Models/User.php` | Modify | `use BelongsToTenant`; add `school_id` to `$fillable` |
| `bootstrap/app.php` | Modify | Append `ResolveTenant::class` to `web` and `api` groups (after `SubstituteBindings` so route model binding still resolves; before controllers run) |
| `config/sanctum.php` | Modify | Wildcard-aware `stateful` (see below) |
| `config/session.php` env | Modify | `SESSION_DOMAIN=.app.com` |
| `database/seeders/SuperAdminUserSeeder.php` | Modify | Seed landlord with `school_id = NULL`; keep credential validation |
| `database/seeders/RoleAndPermissionSeeder.php` | Modify | Correct docblock: global-role-in-multi-tenant semantics, not single-institution |
| `database/factories/SchoolFactory.php` | Create | Factory for tests |
| `tests/Feature/Tenancy/*.php` | Create | 4 scope-bypass test files |
| `TENANCY.md` or module doc | Create | `school_id` convention + safe cross-tenant querying |

## Sanctum wildcard stateful resolution
Sanctum matches `stateful` entries with `Str::is()`, which already supports `*` wildcards — so no `EnsureFrontendRequestsAreStateful` override is needed. Change `config/sanctum.php` `stateful` to include a wildcard entry:

```php
'stateful' => array_filter(array_merge(
    explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', '')),
    ['*.app.com', 'app.com', 'localhost', 'localhost:5173', '127.0.0.1'],
)),
```

Env-drive `*.app.test` for local. Paired with `SESSION_DOMAIN=.app.com` so the session/`XSRF-TOKEN` cookie is shared across every subdomain, making cross-subdomain CSRF/session auth work. The existing `SecurityBaselineTest` middleware-order assertions remain unaffected (ResolveTenant appends after the asserted trio).

## Interfaces / Contracts
```php
namespace App\Tenancy;
final class TenantContext {
    public function set(School $school): void;
    public function current(): ?School;   // null under landlord/none
    public function hasTenant(): bool;
}
// trait
trait BelongsToTenant {                    // adds TenantScope + creating hook
    public function scopeWithoutTenantScope($q) { return $q->withoutGlobalScope(TenantScope::class); }
}
function current_tenant(): ?App\Tenancy\Models\School;
```

## Testing Strategy (Pest, RefreshDatabase, matches existing style)
| File | Asserts |
|------|---------|
| `tests/Feature/Tenancy/DefaultScopingTest.php` | Model created under tenant 1 invisible to a query bound to tenant 2; insert auto-stamps correct `school_id` |
| `tests/Feature/Tenancy/LandlordBypassTest.php` | `withoutTenantScope()` returns rows across tenants AND a landlord user (NULL `school_id`) is visible to itself; no normal request path returns cross-tenant rows |
| `tests/Feature/Tenancy/RawQueryDisciplineTest.php` | Documents `DB::table()` is unscoped by design; asserts Eloquent models scope; convention enforced by review, not runtime |
| `tests/Feature/Tenancy/QueuedJobTenantContextTest.php` | A job dispatched for tenant 1 that re-binds `TenantContext` from a carried `school_id` never reads/writes tenant 2 data |
| `tests/Feature/Tenancy/ResolveTenantMiddlewareTest.php` | tenant host binds School; landlord host binds none (scope no-ops); unknown/inactive subdomain → 404 |

Use a lightweight test-only tenant-scoped model (or the seeded modules) under a factory. Follow existing `beforeEach` route-registration + `RefreshDatabase` pattern seen in `SecurityBaselineTest`.

## Migration / Rollout
No production/tenant data. Order: `create_schools_table` runs before the modified `users` migration edit takes effect on a fresh migrate; on an existing DB use `migrate:fresh` (greenfield, empty tables). Seeder order in `DatabaseSeeder`: `RoleAndPermissionSeeder` → (optional demo `SchoolSeeder`) → `SuperAdminUserSeeder` (landlord). Rollback per proposal: drop `schools`, drop `users.school_id`, revert middleware + config + files.

## Local dev (Windows, pragmatic)
Wildcard subdomains need real host entries (Windows `hosts` has no wildcard). Add to `C:\Windows\System32\drivers\etc\hosts` the specific test subdomains used, e.g. `127.0.0.1 app.test`, `127.0.0.1 school1.app.test`, `127.0.0.1 school2.app.test`, `127.0.0.1 admin.app.test`. Set `SANCTUM_STATEFUL_DOMAINS=*.app.test,app.test`, `SESSION_DOMAIN=.app.test`, `APP_URL=http://app.test`. Herd/Valet users get `*.app.test` automatically via dnsmasq; document both. Tests do not need real DNS — they set the host via `->get('http://school1.app.test/...')`.

## Open Questions
- [ ] Confirm production base domain string (`app.com` placeholder) for `config/tenancy.php` default and Sanctum wildcard entry.
- [ ] Landlord host list: `admin.app.com` only, or also bare `app.com`? Design supports an env list (`APP_LANDLORD_HOSTS`); default assumed `admin.<base>`.
