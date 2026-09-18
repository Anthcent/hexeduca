# Proposal: Multi-Tenancy Foundation

## Intent
The educativo foundation was built explicitly single-institution: the `RoleAndPermissionSeeder` docblock says so, there is no `school_id`/`tenant_id` anywhere, no tenant resolution, and no query scoping. To become a school-management SaaS, one running deployment must safely serve many schools whose data never bleeds across boundaries. This change establishes the multi-tenancy foundation — tenant identity, tenant resolution, and query scoping — so every future per-module feature inherits ONE tenant-isolation pattern instead of each module inventing its own (or forgetting it and leaking data).

**Why now**: no business data exists yet in the 6 non-Users modules. Introducing the `school_id` + global-scope convention while tables are still empty is nearly free; retrofitting it after Academic/Grades/Schedule/etc. ship real migrations and models would be a costly, error-prone, data-migration-heavy rework with a live risk of cross-tenant leakage. This is the cheapest moment in the project's life to get isolation right.

**What success looks like**: a request to `school1.app.com` can only ever see school 1's data; the same code deployed once serves `school2.app.com` with zero data overlap; the isolation is enforced by default (opt-out, not opt-in) so a developer writing a normal Eloquent query in a future module gets tenant scoping automatically; and the few legitimate cross-tenant paths (landlord super-admin, queued jobs) are explicit, documented, and test-covered.

## Scope
### In Scope
- **`School` tenant model + migration** (chosen location justified below) representing a tenant/institution, keyed by a unique `subdomain`.
- **Subdomain-resolution middleware** registered once in `bootstrap/app.php`'s `withMiddleware()` (web + api groups), resolving the current `School` from the request host and binding it into the container/context BEFORE any tenant-scoped query can run.
- **`BelongsToTenant` trait + Eloquent Global Scope** applied to tenant-scoped models across all modules, auto-filtering reads by the resolved `school_id` and auto-stamping `school_id` on insert.
- **`school_id` column on `users`** (root migration `0001_01_01_000000_create_users_table.php`), plus a documented convention listing which future per-module tables must carry `school_id` when their migrations are eventually written.
- **Sanctum stateful-domains + session cookie domain configuration** for wildcard `*.app.com` subdomains (dynamic/wildcard-aware `stateful` resolution and `SESSION_DOMAIN=.app.com`).
- **Explicit super-admin / landlord bypass design** — how a landlord account operates outside the tenant scope safely.
- **Roles scoping decision** — global vs tenant-scoped Spatie roles for v1, decided and justified.
- **Test strategy** for scope-bypass risks: raw queries, `withoutGlobalScopes()`, queued jobs / console commands running without HTTP request context.

### Out of Scope (future separate SDD changes)
- Billing / subscriptions / plan limits per tenant.
- Tenant self-service signup / provisioning UI and onboarding flows.
- CI/CD, observability, APM, per-tenant analytics.
- Per-module business logic and its tests (Academic, Grades, Schedule, Files, Admin, Notifications feature work).
- `sessions` table `school_id` column (only add if/when per-tenant session isolation or analytics is actually required — not needed for correctness of auth here).
- Database-per-tenant isolation, read-replica-per-tenant, table partitioning.
- Per-tenant custom domains / vanity domains (only `*.app.com` subdomains in v1).

## Capabilities
### New Capabilities
- `multi-tenancy`: `School` tenant model, subdomain tenant resolution, `BelongsToTenant` global-scope isolation pattern, landlord bypass.
### Modified Capabilities
- `auth-rbac-baseline`: `users.school_id` added; super-admin re-framed as landlord-level; roles scoping decision applied to the seeder.
- `security-baseline`: Sanctum stateful-domains + session cookie domain made wildcard-subdomain-aware for cross-subdomain CSRF/session auth.

## Approach
Exploration Approach 1 (already agreed with the user, not re-litigated): shared database, single schema, tenant identified via subdomain, scoping via a `school_id` column + Laravel Eloquent Global Scope. Rejected in exploration: `stancl/tenancy` (contradicts shared-schema, adds a dependency the foundation didn't anticipate) and database-per-tenant (ops overhead unjustified at this stage).

Mechanics:
1. A `School` model + migration (`id`, `name`, `subdomain` unique, `is_active`, timestamps).
2. `ResolveTenant` middleware appended to the `web` and `api` groups in `bootstrap/app.php` — parses the leading subdomain label from the host, looks up the active `School`, and binds it as a scoped/singleton "current tenant" context in the container. Missing/unknown/inactive subdomain fails closed (404/tenant-not-found), NOT silent fallthrough. Central registration avoids touching all 7 module `RouteServiceProvider::map()` methods.
3. A `BelongsToTenant` trait that (a) `addGlobalScope` filtering `where school_id = <current tenant>`, and (b) a `creating` model hook stamping `school_id` from the current tenant. Tenant-scoped module models `use` this trait. `users` uses it too (tenant users) with the landlord carve-out below.
4. `users.school_id` FK added; documented convention that every future tenant-owned table (`academic_*`, `grades_*`, `schedule_*`, `files_*`, notifications, tenant-side admin data) carries `$table->foreignId('school_id')->constrained()` and its model uses `BelongsToTenant`.
5. Sanctum `stateful` computed to match the `*.app.com` wildcard (dynamic resolution at boot rather than a static list) and `SESSION_DOMAIN=.app.com` so the session/CSRF cookie is shared across subdomains.

### Location decision: where does `School` live?
Chosen: a lightweight **core/shared location** (a `Tenancy` concern outside the 7 business modules — e.g. app-level `App\Tenancy` or a dedicated thin module), NOT inside `Modules/Admin`.
- Rationale: the tenant boundary is cross-cutting infrastructure that Users, Academic, Grades, Schedule, Files, Notifications AND Admin all depend on. Placing `School` inside `Modules/Admin` would invert dependencies — every other module would have to depend on Admin purely to reference the tenant, coupling business modules to an administrative feature module. `BelongsToTenant` and `ResolveTenant` are already app-level (the middleware registers in `bootstrap/app.php`), so `School` belongs alongside them at the tenancy core. This keeps the modular-monolith dependency direction clean (modules depend on shared tenancy core, not on each other). Final placement (`App\Tenancy` vs a dedicated `Tenancy` module) is a design-phase call; the constraint is: outside the business modules, no inbound dependency from a business module into another business module.

### Key decisions (resolved, with rationale)

**Decision 1 — Roles are GLOBAL for v1 (Spatie `teams` stays `false`).**
- The 4 seeded roles (student, teacher, staff/admin, super-admin) carry NO attached permissions yet and there is no business requirement for a role to mean different things at different schools. A `teacher` is a `teacher` everywhere in v1.
- Enabling Spatie `teams` now would add `team_id` columns to three permission tables and conceptually couple "authorization team" to "tenant" before any concrete need exists — premature and reversible-with-cost.
- Tenant isolation of *people* is still enforced: a user belongs to exactly one school via `users.school_id`, so a `teacher` at school 1 is a distinct user row from a `teacher` at school 2 and only ever sees school 1's data via the global scope. The role label is shared; the user and their data are scoped. That is sufficient isolation for v1.
- Deferred (documented): when a real requirement for per-school permission divergence appears, revisit Spatie `teams` (or per-school role rows) as its own change. The seeder docblock's "single-institution" wording is corrected to reflect global-role-in-multi-tenant semantics.

**Decision 2 — Super-admin is a LANDLORD-level account that bypasses the tenant scope; tenant admins do not.**
- Two distinct concepts, deliberately separated:
  - **Landlord super-admin**: a platform operator account with `school_id = NULL`, not bound to any subdomain, whose purpose is cross-tenant administration/support. It is the ONLY identity permitted to bypass the global scope.
  - **Tenant `staff/admin`**: the top administrator *within* one school — fully tenant-scoped, `school_id` set, cannot see other schools. This is the role most "admin" users actually are.
- Bypass mechanism (explicit, security-sensitive escape hatch): the global scope is applied unconditionally; cross-tenant access is only obtained by an *explicit* opt-out (`School::withoutTenantScope()` / an explicit landlord query path), never by an implicit "if super-admin, skip scope" branch inside the scope itself. Reasoning: an implicit skip means every single query silently trusts the current user's role, so one mis-set role or one impersonation bug leaks all tenants at once. An explicit opt-out means the default is always isolation, and cross-tenant reads are grep-able, few, and individually test-covered.
- Consequence for `users.school_id`: the column is **nullable** — `NULL` marks the landlord account(s); every tenant user has a non-null `school_id`. `SuperAdminUserSeeder` is re-framed to seed a landlord account (no school), separate from any per-tenant admin.
- Landlord resolution: when the middleware cannot resolve a tenant (e.g. bare `app.com` or a dedicated `admin.app.com` landlord host), tenant-scoped reads must not run under an implicit tenant; landlord context is entered explicitly. The exact landlord host/route surface is a design-phase detail.

### Test strategy (scope-bypass risk coverage)
Isolation is only real if it survives the four classic leak paths. The design/apply phases must include tests asserting:
1. **Default scoping**: model A created under tenant 1 is invisible to a query executed under tenant 2 (and vice versa); insert auto-stamps the correct `school_id`.
2. **`withoutGlobalScopes()` / explicit bypass**: only the landlord path returns cross-tenant rows; a regression that removes the scope elsewhere is caught. Assert the landlord bypass returns multiple tenants' rows AND that no normal request path can.
3. **Raw queries**: documented as UNSCOPED by design (global scopes are Eloquent-only). The convention: business code must use Eloquent models, not `DB::table(...)`, for tenant-owned data; a guard/lint or code-review rule flags raw queries against tenant tables. Test coverage asserts the models scope; raw-query discipline is a documented rule, not a runtime guarantee.
4. **Queued jobs / console commands (no HTTP request, no subdomain)**: tenant context is lost outside the request lifecycle. Convention: jobs must carry `school_id` explicitly and re-enter tenant context (bind current tenant) at handle-time; console commands operate as landlord or take an explicit `--school` argument. Tests assert a job dispatched for tenant 1 does not read/write tenant 2 data.

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Global-scope silently bypassed (raw SQL, `withoutGlobalScopes`, eager loads on unscoped parents) leaking cross-tenant data | High if uncontrolled | Opt-out-not-opt-in scope; base trait all tenant models use; explicit landlord-only bypass path; the 4 test categories above; documented "how to query across tenants" pattern |
| Sanctum `stateful` is a static array, not wildcard-aware — CSRF/session auth breaks across `*.app.com` | High (confirmed gap) | Dynamic/wildcard-aware stateful-domain resolution at boot + `SESSION_DOMAIN=.app.com`; verified end-to-end in design/apply before subdomain routing is declared working |
| Queued jobs / commands run without tenant context and touch the wrong (or every) tenant | Med-High | Jobs carry and re-hydrate `school_id`; commands are landlord or `--school`-scoped; test-covered |
| Landlord bypass becomes an over-broad footgun (impersonation, accidental cross-tenant writes) | Med | Single explicit bypass API, `school_id = NULL` landlord identity only, grep-able call sites, dedicated tests |
| Nullable `users.school_id` mis-set (tenant user left NULL) accidentally becomes a landlord | Med | Seeder/registration enforces non-null `school_id` for tenant users; landlord creation is a separate, explicit path |
| `School` placement couples business modules to Admin | Low (decided) | `School` lives in tenancy core outside business modules; documented dependency-direction rule |
| Establishing the pattern with zero prior art in-repo | Med | Elevated design-review importance; judgment-day recommended after design/apply per repo policy |

## Rollback Plan
No git in use and no production/tenant data yet. Rollback = drop the `schools` table and `users.school_id` column, revert `bootstrap/app.php` middleware registration, revert `config/session.php` / `config/sanctum.php` changes, and remove the `BelongsToTenant`/`ResolveTenant`/`School` files. Engram artifacts remain. Because no real tenant data exists yet, rollback is low-risk — which is precisely why doing this now is cheap.

## Dependencies
- Existing installed stack only: Laravel 12, Sanctum, spatie/laravel-permission. NO new Composer package (stancl/tenancy explicitly rejected).
- Local wildcard-subdomain testing capability (e.g. `*.app.test` hosts / DNS) for verifying subdomain resolution and cookie scoping.

## Success Criteria
- [ ] `School` model + migration exist in the tenancy core (outside business modules), keyed by unique `subdomain`.
- [ ] `ResolveTenant` middleware registered once in `bootstrap/app.php`; unknown/inactive subdomain fails closed.
- [ ] `BelongsToTenant` trait auto-scopes reads and auto-stamps `school_id` on insert.
- [ ] `users.school_id` added (nullable = landlord); tenant users non-null.
- [ ] Sanctum stateful + `SESSION_DOMAIN` work across `*.app.com` (CSRF/session verified across two subdomains).
- [ ] Landlord super-admin can access cross-tenant data ONLY via the explicit bypass; no implicit role-based skip in the scope.
- [ ] Roles remain global (`permission.teams=false`); seeder docblock corrected.
- [ ] All 4 scope-bypass test categories present and green.
- [ ] Documented convention for `school_id` on future per-module tables + safe cross-tenant querying.

## Proposal question round (for user review before spec/design)
The two architectural open decisions are resolved above with the exploration-recommended answers. Flagging the assumptions that most change the design if wrong — the user can confirm, correct, or request a second round:
1. **Subdomain-only tenancy** (`*.app.com`), no per-tenant custom/vanity domains in v1. Confirm?
2. **Landlord operates on a separate host** (bare `app.com` / `admin.app.com`) rather than logging into a tenant subdomain. Confirm the landlord surface, or is landlord access expected from within tenant subdomains?
3. **Global roles for v1** (a `teacher` means the same everywhere). Confirm no near-term need for per-school permission divergence?
4. **Nullable `users.school_id`** as the landlord marker. Acceptable, or prefer a fully separate `landlords`/admin table so the `users` table stays strictly tenant-scoped?
5. **Tenant provisioning is out of scope** here (schools seeded/created manually for now). Confirm signup/onboarding is a later change?
