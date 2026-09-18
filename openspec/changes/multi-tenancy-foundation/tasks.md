# Tasks: Multi-Tenancy Foundation

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 700-1000 (9 new core files, 8 new test files, 1 doc, 8 modified files: bootstrap/app.php, users migration, User.php, sanctum/session config, 2 seeders, composer.json) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | Unit 1 (tenancy core) -> Unit 2 (resolution + users wiring) -> Unit 3 (sanctum/session + seeders) -> Unit 4 (tests + docs) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending — user must choose before apply |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units
| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | `School` model/migration/factory + `TenantContext`/`TenantScope`/`BelongsToTenant`/helpers + `config/tenancy.php` | PR 1 | No dependents yet; independent base |
| 2 | `ResolveTenant` middleware + `bootstrap/app.php` wiring + `users.school_id` migration + `User` model | PR 2 | Depends on Unit 1 |
| 3 | Sanctum wildcard `stateful` + `SESSION_DOMAIN` + seeder rework (landlord + docblock) | PR 3 | Depends on Unit 2 (users.school_id) |
| 4 | 8 test files + `TENANCY.md` | PR 4 | Depends on Units 1-3 |

## Phase 1: Tenancy Core Data Model
- [x] 1.1 Create `config/tenancy.php`: `base_domain` (`app.com` placeholder, swap later), `landlord_hosts` (env-driven, default `admin.<base_domain>` only — no bare-root landlord).
- [x] 1.2 Create `database/migrations/..._create_schools_table.php`: `id, name, subdomain (unique), is_active (default true), timestamps`. NOTE: filename changed from the spec's `2026_09_02_000000_...` to `0000_01_01_000000_...` — see Deviations in apply-progress.
- [x] 1.3 Create `app/Tenancy/Models/School.php`: fillable `name, subdomain, is_active`, `is_active` cast, `withoutTenantScope()` static helper.
- [x] 1.4 Create `database/factories/SchoolFactory.php` for tests.
- [x] 1.5 Modify `database/migrations/0001_01_01_000000_create_users_table.php`: add `foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete()->index()`.

## Phase 2: Tenant Context & Scoping
- [x] 2.1 Create `app/Tenancy/TenantContext.php`: `set/current/hasTenant/forget`, bound scoped in container.
- [x] 2.2 Create `app/Tenancy/Scopes/TenantScope.php`: `apply()` filters `school_id` only when `TenantContext::hasTenant()`.
- [x] 2.3 Create `app/Tenancy/Concerns/BelongsToTenant.php`: adds `TenantScope` + `creating` hook stamping `school_id`; `scopeWithoutTenantScope()`.
- [x] 2.4 Create `app/Tenancy/helpers.php`: `current_tenant(): ?School`; register in `composer.json` `autoload.files`.

## Phase 3: Subdomain Resolution
- [x] 3.1 Create `app/Tenancy/Http/Middleware/ResolveTenant.php`: classify host (landlord/tenant/unrecognized), strip `base_domain`, lookup active `School` with scope disabled, bind `TenantContext`, abort 404 on unknown/inactive (fail closed).
- [x] 3.2 Modify `bootstrap/app.php`: append `ResolveTenant::class` to `web` and `api` groups, after `SubstituteBindings`.

## Phase 4: Users Integration & Landlord
- [x] 4.1 Modify `Modules/Users/Infrastructure/Models/User.php`: `use BelongsToTenant`, add `school_id` to `$fillable`.
- [x] 4.2 Modify `database/seeders/SuperAdminUserSeeder.php`: seed landlord with `school_id = NULL`, keep fail-closed credential validation.
- [x] 4.3 Modify `database/seeders/RoleAndPermissionSeeder.php`: correct docblock to global-role-in-multi-tenant semantics; confirm `config/permission.php` `teams` stays `false`.
- [x] 4.4 Verify `DatabaseSeeder.php` order: `RoleAndPermissionSeeder` -> `SuperAdminUserSeeder`.

## Phase 5: Sanctum & Session Wildcard
- [x] 5.1 Modify `config/sanctum.php`: wildcard `stateful` (`*.app.com`, `app.com`, env-merged via `SANCTUM_STATEFUL_DOMAINS`).
- [ ] 5.2 BLOCKED — Modify `.env.example`: `SESSION_DOMAIN=.app.test`, `SANCTUM_STATEFUL_DOMAINS=*.app.test,app.test`. Sandbox tooling (Read/Grep/Bash) denies all access to `.env.example` in this environment; apply manually (see apply-progress notes for exact lines).

## Phase 6: Documentation
- [x] 6.1 Create `TENANCY.md`: `school_id` convention for future per-module tables, safe cross-tenant querying, raw-query discipline rule, console-command `--school` convention.

## Phase 7: Tests (map every spec scenario)
- [ ] 7.1 `tests/Feature/Tenancy/SchoolModelTest.php`: unique-subdomain scenario; inactive school distinguishable to resolver.
- [ ] 7.2 `tests/Feature/Tenancy/ResolveTenantMiddlewareTest.php`: known active subdomain binds tenant before controller runs; unknown subdomain 404s; inactive subdomain 404s; landlord host binds no tenant.
- [ ] 7.3 `tests/Feature/Tenancy/DefaultScopingTest.php`: tenant-1 record invisible under tenant-2 query and vice versa; insert auto-stamps `school_id`.
- [ ] 7.4 `tests/Feature/Tenancy/LandlordBypassTest.php`: `withoutTenantScope()` returns cross-tenant rows; no normal request path can; asserts no implicit role-based skip.
- [ ] 7.5 `tests/Feature/Tenancy/RawQueryDisciplineTest.php`: documents `DB::table()` unscoped by design; asserts Eloquent models scope.
- [ ] 7.6 `tests/Feature/Tenancy/QueuedJobTenantContextTest.php`: job dispatched for tenant 1 re-binds context, never touches tenant 2; add test-only Artisan command asserting no `--school` defaults to landlord, never an implicit tenant.
- [ ] 7.7 `tests/Feature/Tenancy/SanctumWildcardStatefulTest.php`: CSRF/session works across two tenant subdomains; new subdomain works without config change; non-wildcard host is not treated as stateful.
- [ ] 7.8 `tests/Feature/Auth/LandlordSeederTest.php`: explicit valid credentials create landlord with `school_id = NULL`, `super-admin` role, distinct from tenant `staff/admin`; bootstrap fails closed on invalid/weak credentials.
