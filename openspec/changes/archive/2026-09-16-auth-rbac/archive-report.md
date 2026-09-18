# Archive Report: Auth + Role Assignment (auth-rbac)

**Date**: 2026-09-16  
**Change**: auth-rbac  
**Mode**: hybrid (OpenSpec filesystem + Engram persistence)  
**Status**: COMPLETE

## Change Summary

The auth-rbac change delivers the first real user authentication and role assignment surface for EDUCATIVO's multi-tenant Laravel school-management SaaS. 

**Context**: This change underwent 5 rounds of adversarial judgment-day review during the design phase before implementation began. Each round surfaced and closed critical gaps in the multi-tenant host-resolution architecture:
- **Round 1-4**: Identified the risk that `/register` called `Auth::login()` on the landlord host, creating an unscoped session for non-super-admins — a direct bypass of the "landlord-host login restricted to super-admin" decision. Round 4 confirmed the gap; Rounds 2-3 explored alternative routes and rejected them as inadequate.
- **Round 5 (final)**: Confirmed the fix (no `Auth::login()` on registration, use `?registered=1` query parameter instead of session flash) was correct, since session cookies don't survive cross-host redirects from landlord to tenant subdomain.

The design.md is therefore unusually detailed for a thin Inertia controller change — it captures the full multi-tenant host-resolution architecture: landlord-host-only registration via `RequireLandlordHost` middleware, host-unconstrained login with an in-controller super-admin-only check, registration never auto-authenticates (redirects to tenant subdomain login), and `UserPolicy` as the always-applicable tenant/escalation guard.

**Deliverables**: 
- Login GET+POST `/login` (Inertia page replacing the unreachable closure)
- Registration GET+POST `/register` (landlord-host-only, school `<select>`, no auto-login)
- Logout POST `/logout`
- Auth sharing: `HandleInertiaRequests::share()` lazy `auth.user = {id,name,email,role}`
- Minimal role-assignment UI: edit-user screen with role `<select>`, gated `auth` + `role:staff/admin|super-admin`, tenant-scoped

**Key decisions implemented**:
1. Registration is route-constrained to the landlord host via `RequireLandlordHost` middleware (not `Route::domain()`), reusing the exact casing-aware normalization `ResolveTenant` already uses.
2. Login/logout routes are host-unconstrained (reachable on tenant subdomains and the landlord host); on the landlord host only super-admin users can complete authentication.
3. Registration does NOT call `Auth::login()` or use session flash — it redirects with a `?registered=1` query parameter to the tenant subdomain `/login` page.
4. Role escalation (super-admin grant) is guarded by `UserPolicy::assignRole`, which blocks staff/admin from granting `super-admin` and always applies, on any host.

## Verification Status

**Verification Verdict**: PASS (0 CRITICAL, 0 WARNING)

- 130 tests passed, 413 assertions, 0 failures
- All 28/28 implementation tasks complete and marked in source
- All spec requirements covered by passing runtime tests
- Full spec compliance: landlord-host auth isolation, tenant-scoped role assignment, no regressions
- No unchecked tasks remaining

## Specs Synced to Main

### New Capability Specs Created
| Domain | Spec | Action | Details |
|--------|------|--------|---------|
| user-authentication | `openspec/specs/user-authentication/spec.md` | Created | 5 requirements, 18 scenarios: self-registration (landlord-host-only, no auto-login), login/logout (host-unconstrained, throttled), landlord-host super-admin restriction, auth.user sharing, protected-route rejection |
| role-assignment | `openspec/specs/role-assignment/spec.md` | Created | 5 requirements, 12 scenarios: default student role, staff/admin reassignment, tenant-scoped updates, super-admin-only escalation, Academic gate no-regression |

## Archive Contents

Archive location: `openspec/changes/archive/2026-09-16-auth-rbac/`

All artifacts present and verified:

- ✅ `proposal.md` — Intent (first usable auth surface), scope (login/register/logout/auth-sharing/role-assignment UI), approach (thin Inertia controller, public school `<select>`), risks, rollback, dependencies, success criteria
- ✅ `design.md` — Technical approach (thin Inertia controllers over Laravel `Auth` + Spatie roles), 4 load-bearing architecture decisions (middleware-gated registration, host-unconstrained login with in-controller role check, no registration auto-login, UserPolicy super-admin guard), data flow pseudocode, 14-row file changes table
- ✅ `tasks.md` — 28 tasks across 8 phases, all marked complete; 3 chained PR work units suggested
- ✅ `apply-progress.md` — Full 3-batch implementation log (Batch 1: register/login/logout backend+tests; Batch 2: role-assignment backend+tests; Batch 3: Inertia auth.user + Vue pages), all tasks marked complete, one filesystem-path correction documented (Edit.vue placed flat per resolver, not nested)
- ✅ `verify-report.md` — Full verification: 130 tests passed, Pint 229 files zero violations, design decisions source-level spot-checks (RequireLandlordHost/ResolveTenant normalization parity, landlord-host login gate matching design exactly, no registration auto-login, UserPolicy implementation, auth.user lazy sharing, Edit.vue flat-path correction confirmed necessary)
- ✅ `exploration.md` — Current state (no real auth, Spatie roles seeded but unused, no permission mechanism), affected areas, approaches considered (PermissionRegistry as future work)
- ✅ `specs/user-authentication/spec.md` — Full capability spec (5 requirements, 18 scenarios)
- ✅ `specs/role-assignment/spec.md` — Full capability spec (5 requirements, 12 scenarios)
- ✅ `archive-report.md` — This file

## Task Completion Verification

**28/28 tasks complete**, verified across all phases and implementation batches:

### Batch 1: Middleware + Routes + Auth Backend (Phases 1-3)
- `RequireLandlordHost` middleware created; `routes/web.php` `/login` closure removed; `Modules/Users/routes/web.php` login/logout/register routes added (register gated to landlord host)
- `LoginRequest` and `RegisterRequest` form requests created
- `AuthController` implemented with `showLogin`, `login`, `showRegister`, `register`, `logout` actions
- `School::loginUrl()` helper added

### Batch 2: Role Assignment Backend (Phase 4)
- `UpdateUserRoleRequest` form request created
- `UserPolicy::assignRole()` implemented exactly per design (super-admin bypass; else same-tenant + no super-admin grant)
- `UsersServiceProvider` wiring: `Gate::policy(User::class, UserPolicy::class)` in `boot()`
- `UsersController::edit()` and `update()` actions implemented
- `users.edit`/`users.update` routes added, gated `auth` + `role:staff/admin|super-admin`
- Base `Controller` class given `AuthorizesRequests` trait

### Batch 3: Inertia Auth Sharing + Vue Pages (Phases 5-7)
- `HandleInertiaRequests::share()` extended with lazy `auth.user` prop (`{id,name,email,role}` for authenticated users, `null` for guests)
- Three Vue SFCs created: `Login.vue` (email/password form, `registered` query-param banner), `Register.vue` (school `<select>`, all registration fields), `Edit.vue` (role `<select>`, flat-path placement per resolver correction)

### Phase 6 & 8: Tests + Verification
- 7 new test files: `RegistrationTest`, `LoginLogoutTest`, `LandlordHostLoginTest`, `HostScopingTest`, `RoleAssignmentTest`, `AcademicGateRegressionTest`, `UserPolicyTest`
- 3 new Inertia-specific tests: `InertiaAuthSharingTest` (guest/auth/post-update role reflection)
- 130 total tests passing, 413 assertions, zero regressions

## Key Architecture Decisions Honored

### 1. Landlord-Host Registration Constraint (not Route::domain)
- `RequireLandlordHost` middleware compares `strtolower($request->getHost())` against `array_map('strtolower', config('tenancy.landlord_hosts'))` — identical normalization to `ResolveTenant`, ensuring no casing-mismatch gaps
- Middleware approach (not `Route::domain()`) avoids route-name duplication collisions when multiple landlord hosts are configured
- `/register` unreachable on tenant subdomains, preventing cross-school self-registration

### 2. Host-Unconstrained Login with In-Controller Role Check
- `/login` route reachable on both tenant subdomains and the landlord host
- On tenant subdomains: `TenantScope` naturally scopes `Auth::attempt` lookup to that school (existing behavior, no change)
- On the landlord host: `ResolveTenant` binds no tenant, so `TenantScope` no-ops; an in-controller post-auth check (`onLandlordHost() && !$user->hasRole('super-admin')`) immediately logs out and rejects with the same generic error as a bad password (no account-enumeration leak)
- Rationale: `super-admin` users have `school_id = null` and are naturally excluded by `TenantScope` on real tenant subdomains, so no check is needed there

### 3. Registration Does Not Establish a Session
- `AuthController::register()` creates the user with explicit `school_id`, assigns `student` role, then redirects to `School::loginUrl().'?registered=1'` — does NOT call `Auth::login()`
- Rationale (from design review): on the landlord host, `TenantScope` no-ops; an `Auth::login()` here would create an unscoped authenticated session for a non-super-admin on exactly the host meant to restrict it to super-admin only
- `Login.vue` reads the `?registered=1` query parameter (survives cross-host redirect, unlike session cookies) to render a static "Account created" message

### 4. UserPolicy for Escalation + Tenant Enforcement
- `UserPolicy::assignRole(User $actor, User $target, string $role)` enforces three rules: super-admin bypasses everything; else actor and target must share `school_id`; staff/admin cannot grant `super-admin`
- Registered via `Gate::policy(User::class, UserPolicy::class)` in `UsersServiceProvider::boot()` (mandatory because the model lives outside `App\Models`, skipping auto-discovery)
- `TenantScope` filters queries when a tenant is bound (on real subdomains); `UserPolicy` always applies regardless of host, guaranteeing correctness on unbound hosts

## Scope Compliance Summary

### user-authentication Spec (5 requirements, 18 scenarios)
- ✅ Self-registration (landlord-host-only, no auto-login, school select, valid -> user with student role created and redirected to tenant subdomain login with `?registered=1`)
- ✅ Duplicate email + invalid school_id rejected
- ✅ Login/logout (throttled, session-regenerated, host-unconstrained routes)
- ✅ Landlord-host login restricted to super-admin (non-super-admin rejected with generic error)
- ✅ Auth.user shared lazily to frontend (`{id,name,email,role}` for authenticated, `null` for guests)
- ✅ Protected routes reject unauthenticated requests

### role-assignment Spec (5 requirements, 12 scenarios)
- ✅ Self-registered users get default `student` role
- ✅ Staff/admin + super-admin can reassign roles via dropdown, replacing current role
- ✅ Non-admin users (student/teacher) cannot access edit screen (403)
- ✅ Reassignment scoped to admin's own `school_id` (cross-tenant edits blocked by TenantScope + UserPolicy)
- ✅ Only super-admin can grant `super-admin` role
- ✅ Existing Academic `role:staff/admin` gate unchanged (staff/admin still passes, student still rejected)

All 30 scenarios (18 + 12) covered by real passing tests with database assertions and form validation checks.

## Regression Check

Full test suite: **130 passed, 0 failed, 413 assertions**

All pre-existing tests (102 from `academic-core-structure`, core app, Broadcasting, SecurityBaseline, Foundation) remain green. 28 new tests added (7 test files + 3 Inertia-specific tests) with appropriate new assertion coverage. Zero regressions confirmed. `academic-core-structure` role-gating and `TenantScope` behavior unchanged.

## Files Modified / Created in Implementation

### Controllers + Middleware
- `Modules/Users/Infrastructure/Http/Controllers/AuthController.php` — new (login/register/logout)
- `Modules/Users/Infrastructure/Http/Controllers/UsersController.php` — modified (edit/update for role assignment)
- `app/Tenancy/Http/Middleware/RequireLandlordHost.php` — new
- `app/Http/Controllers/Controller.php` — modified (AuthorizesRequests trait added)

### Form Requests
- `Modules/Users/Infrastructure/Http/Requests/LoginRequest.php` — new
- `Modules/Users/Infrastructure/Http/Requests/RegisterRequest.php` — new
- `Modules/Users/Infrastructure/Http/Requests/UpdateUserRoleRequest.php` — new

### Policies + Providers
- `Modules/Users/Infrastructure/Policies/UserPolicy.php` — new
- `Modules/Users/Infrastructure/Providers/UsersServiceProvider.php` — modified (Gate::policy registration)

### Routes + Middleware
- `routes/web.php` — modified (removed `/login` closure)
- `Modules/Users/routes/web.php` — modified (added login/logout/register/users routes)
- `app/Http/Middleware/HandleInertiaRequests.php` — modified (lazy auth.user prop)
- `app/Tenancy/Models/School.php` — modified (loginUrl() helper)

### Vue Pages
- `Modules/Users/Resources/js/Pages/Login.vue` — new
- `Modules/Users/Resources/js/Pages/Register.vue` — new
- `Modules/Users/Resources/js/Pages/Edit.vue` — new (flat path per resolver, not nested)

### Tests (28 new, 413 total assertions)
- `tests/Feature/Auth/RegistrationTest.php` — new (5 tests)
- `tests/Feature/Auth/LoginLogoutTest.php` — new (4 tests)
- `tests/Feature/Auth/LandlordHostLoginTest.php` — new (3 tests)
- `tests/Feature/Auth/HostScopingTest.php` — new (2 tests)
- `tests/Feature/Auth/InertiaAuthSharingTest.php` — new (3 tests)
- `tests/Feature/Users/RoleAssignmentTest.php` — new (5 tests)
- `tests/Feature/Users/AcademicGateRegressionTest.php` — new (2 tests)
- `tests/Unit/Policies/UserPolicyTest.php` — new (5 tests)
- `tests/Feature/FoundationTest.php` — modified (1 pre-existing test updated)

## Known Limitations (Accepted)

**Environment gap: SUPER_ADMIN_EMAIL/SUPER_ADMIN_PASSWORD missing from .env** — Pre-existing, out of scope. Bare `php artisan migrate:fresh --seed` fails at `SuperAdminUserSeeder`; requires env vars set inline or in `.env`. No action taken in this change (correct — it's not a regression caused by auth-rbac).

**No JS component test runner** — The project has Vite + Playwright e2e configured, but no component-level Jest/Vitest runner. The 3 new Vue pages (`Login.vue`, `Register.vue`, `Edit.vue`) have no automated Vue unit tests, only PHP Feature tests that exercise the routes and static review against Inertia prop contracts. This matches design.md's own Testing Strategy (PHP Feature/Unit only, Vue pages are manual-smoke items) and is not a regression introduced by this change.

## Out of Scope (Explicit Non-Requirements)

The following are explicitly deferred to future, separate changes:

- Permission-matrix UI and per-role permission granularity (deferred to `rbac-permission-matrix`)
- Per-module permission-registration mechanism
- Attaching any `permission:` records to roles (roles remain label-only, 4 seeded enum-like values)
- Password reset, email verification, multi-role-per-user
- Subdomain-based tenant resolution or per-school invite tokens (interim decision: public school `<select>`)
- Session `SESSION_DOMAIN` cross-host coordination (not needed by this change — each host authenticates independently; cross-host session transfer is a separate follow-up)

## Rollback Path

Rollback is simple and additive:

1. Delete new controllers: `AuthController.php`
2. Delete new FormRequests: `LoginRequest.php`, `RegisterRequest.php`, `UpdateUserRoleRequest.php`
3. Delete new Policy: `UserPolicy.php`
4. Delete new middleware: `RequireLandlordHost.php`
5. Delete new Vue pages: `Login.vue`, `Register.vue`, `Edit.vue`
6. Revert `routes/web.php` to restore the `/login` closure
7. Revert `Modules/Users/routes/web.php` to remove all new routes
8. Revert `UsersController` to remove `edit`/`update` actions
9. Revert `UsersServiceProvider` to remove `Gate::policy` registration
10. Revert `App/Http/Controllers/Controller` to remove `AuthorizesRequests` trait
11. Revert `School.php` to remove `loginUrl()` helper
12. Revert `HandleInertiaRequests` to remove `auth` prop
13. Delete all 28 new test files

No schema changes, no data migrations, no side effects. All additive and fully reversible.

## SDD Cycle Status

| Phase | Artifact | Status | Observation IDs |
|-------|----------|--------|-----------------|
| Proposal | proposal.md | Complete | #1585 (Engram) |
| Exploration | exploration.md | Complete | (archived in folder) |
| Spec | specs/{user-authentication,role-assignment}/spec.md | Complete | #1586 (Engram, user-auth spec final update) + merged to `openspec/specs/` |
| Design | design.md | Complete | #1587 (Engram, design final round-5 fix) |
| Tasks | tasks.md | Complete | #1588 (Engram, all 28 tasks checked) |
| Apply | apply-progress.md | Complete | (3-batch implementation, all phases delivered) |
| Verify | verify-report.md | Complete | #1591 (Engram, PASS verdict, 130 tests, 0 failures) |
| Archive | archive-report.md | Complete | This file + Engram persistence (topic_key: `sdd/auth-rbac/archive-report`) |

**The auth-rbac SDD cycle is closed.** All artifacts are archived. The change is production-ready and can be merged.

## Next Steps

None — the change is complete, verified, and archived. User authentication and role assignment are now functional. Future changes can build on this foundation:

- Index/listing views for user management
- Granular spatie permission-matrix UI and per-module permission registration (`rbac-permission-matrix` change)
- Subdomain-based tenant resolution or invite-link registration (improved tenant resolution)
- Password reset, email verification, multi-role support (enhanced auth)
- Session `SESSION_DOMAIN` coordination if cross-host session sharing becomes a requirement (cross-host auth flows)

---

**Archived by**: sdd-archive executor  
**Timestamp**: 2026-09-16  
**Mode**: hybrid (filesystem archive + Engram persistence)  
**Engram Observation IDs**: proposal #1585, spec #1586, design #1587, tasks #1588, verify-report #1591  
**Engram Topic Key**: `sdd/auth-rbac/archive-report`  
**Main Specs Created**: `openspec/specs/user-authentication/spec.md`, `openspec/specs/role-assignment/spec.md`  
**Design Review Context**: 5 rounds of adversarial judgment-day review during design phase closed critical multi-tenant host-resolution gaps (registration auto-login bypass, cross-host redirect handling, session scope leakage).
