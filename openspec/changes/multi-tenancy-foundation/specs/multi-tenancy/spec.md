# Spec: Multi-Tenancy Foundation

## Capability: multi-tenancy

### Purpose
Establish shared-database, single-schema multi-tenancy for educativo: a `School` tenant identity, subdomain-based tenant resolution, and an opt-out-not-opt-in Eloquent scoping pattern (`BelongsToTenant`) that every future per-module table and model inherits, plus an explicit landlord bypass for cross-tenant administration.

### Requirement: School tenant model and migration
The system MUST provide a `School` model and migration in the tenancy core (outside the 7 business modules) with `id`, `name`, `subdomain` (unique), `is_active`, and timestamps.

#### Scenario: School created with unique subdomain
- GIVEN no existing school with subdomain `school1`
- WHEN a `School` is created with `subdomain=school1`
- THEN the record persists and a second `School` with the same subdomain fails uniqueness validation

#### Scenario: Inactive school is distinguishable
- GIVEN a `School` with `is_active=false`
- WHEN the tenant resolver evaluates it
- THEN it is treated as unavailable for tenant resolution

### Requirement: Subdomain tenant resolution fails closed
The system MUST resolve the current tenant from the request host via a `ResolveTenant` middleware registered once in `bootstrap/app.php` for the `web` and `api` groups, executing before any tenant-scoped query runs. Unknown or inactive subdomains MUST fail closed, not fall through to an implicit or default tenant.

#### Scenario: Known active subdomain resolves
- GIVEN a request to `school1.app.com` where `school1` is an active `School`
- WHEN the request is processed
- THEN the tenant context is bound to that `School` before any controller/query executes

#### Scenario: Unknown subdomain is rejected
- GIVEN a request to `unknown.app.com` with no matching `School`
- WHEN the request is processed
- THEN the request fails closed (e.g. 404 tenant-not-found) and no tenant-scoped query executes

#### Scenario: Inactive subdomain is rejected
- GIVEN a request to `school1.app.com` where `school1` exists but `is_active=false`
- WHEN the request is processed
- THEN the request fails closed and no tenant-scoped query executes

### Requirement: BelongsToTenant trait auto-scopes and auto-stamps
The system MUST provide a `BelongsToTenant` trait applying an Eloquent global scope that filters reads by the resolved tenant's `school_id` and stamps `school_id` on the model at creation time. Tenant-scoped models across all modules, including `users`, MUST use this trait.

#### Scenario: Reads are scoped to the current tenant
- GIVEN records exist for tenant 1 and tenant 2 on a `BelongsToTenant` model
- WHEN a query runs under tenant 1's resolved context
- THEN only tenant 1's records are returned

#### Scenario: Inserts auto-stamp the current tenant
- GIVEN a request resolved to tenant 1
- WHEN a new `BelongsToTenant` model is created without explicitly setting `school_id`
- THEN the persisted record has `school_id` equal to tenant 1's id

### Requirement: Landlord bypass is explicit opt-out only
The system MUST treat `school_id = NULL` on `users` as the landlord marker, MUST NOT bypass the global scope implicitly based on role, and MUST expose a single explicit bypass path (e.g. `withoutTenantScope()`) usable only from the landlord surface. The landlord MUST operate from a separate host (e.g. `admin.app.com`), not a tenant subdomain.

#### Scenario: Landlord bypass returns cross-tenant rows
- GIVEN records exist for tenant 1 and tenant 2
- WHEN the landlord explicitly invokes the bypass path
- THEN records from both tenants are returned

#### Scenario: No implicit bypass exists
- GIVEN a user with any role (including landlord role) makes a normal request through a tenant subdomain
- WHEN a `BelongsToTenant` query executes without the explicit bypass call
- THEN the global scope still applies and only the resolved tenant's data is returned

#### Scenario: Landlord host has no resolved tenant
- GIVEN a request to `admin.app.com`
- WHEN the middleware evaluates the host
- THEN no tenant is bound implicitly, and tenant-scoped reads do not run under an implicit tenant

### Requirement: Scope-bypass leak paths are test-covered
The system MUST have automated test coverage for the four classic tenant-isolation leak paths, so that a regression in any of them is caught before release.

#### Scenario: Default scoping isolation is asserted
- GIVEN a record created under tenant 1's context
- WHEN a query executes under tenant 2's context
- THEN the tenant-1 record is invisible, and the reverse also holds

#### Scenario: Bypass is provably landlord-only
- GIVEN the explicit bypass path
- WHEN it is invoked from the landlord surface
- THEN it returns multiple tenants' rows, AND a test asserts no normal (non-landlord) request path can obtain the same result

#### Scenario: Raw-query discipline is documented and enforced by convention
- GIVEN tenant-owned data
- WHEN business code queries it
- THEN the documented convention requires Eloquent models (which carry the scope), not `DB::table(...)`, and this rule is stated in project conventions for review/lint enforcement

#### Scenario: Queued jobs and console commands re-hydrate tenant context explicitly
- GIVEN a job dispatched for tenant 1 (no HTTP request context at execution time)
- WHEN the job runs
- THEN it explicitly carries and re-binds `school_id` for tenant 1 at handle-time, and a test asserts it does not read or write tenant 2 data
- AND a console command run without `--school` operates as landlord only, never as an implicit default tenant
