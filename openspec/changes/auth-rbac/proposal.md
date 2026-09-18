# Proposal: Auth + Role Assignment (auth-rbac)

## Intent
EDUCATIVO has no real auth: `routes/web.php` holds a bare `POST /login` closure, no `AuthController`, no login/register/logout pages, and `HandleInertiaRequests` shares nothing about the user. Meanwhile `role:staff/admin` middleware already gates Academic routes against 4 seeded global roles — so the gating works, but no human can log in through a screen or get a role assigned. This change ships the **first usable auth surface**: real login, self-service registration into a tenant, logout, session handling, auth sharing to the frontend, and a minimal admin screen to change a user's role.

**Why now**: protected routes exist but are unreachable without seeded credentials; self-registration + role assignment is the smallest slice that makes the app usable by real users.

**Success**: a visitor registers (resolving their school), lands with a default `student` role, logs in/out through real pages, the Vue frontend knows who they are and their role, and a `staff/admin` can promote them via a role dropdown — all respecting `school_id` tenant isolation.

## Scope
### In Scope
- **Login**: GET `/login` (Inertia page) + POST `/login` replacing the closure; keep `throttle` + `session()->regenerate()`.
- **Registration**: GET `/register` + POST `/register`; new users get default role `student` and a resolved `school_id`.
- **Logout**: POST `/logout` (invalidate + regenerate token).
- **Auth sharing**: extend `HandleInertiaRequests::share()` with `auth.user` = `{ id, name, email, role }` (single primary role name only — no permission arrays).
- **Role-assignment UI**: minimal edit-user screen (`role` `<select>` over the 4 fixed roles), gated `auth` + `role:staff/admin|super-admin`, scoped to the admin's own `school_id`.
- **Controller placement**: `AuthController` in `Modules/Users/Infrastructure/Http/Controllers/`; routes in `Modules/Users/routes/web.php`.

### Out of Scope (deferred to follow-up `rbac-permission-matrix`)
- Admin screen to check/uncheck which functions/modules/views each ROLE can access.
- Per-module permission-registration mechanism / `PermissionRegistry` and `permissions:sync`.
- Attaching any `permission:` records to roles (roles stay label-only, as seeded).
- Password reset / email verification / multi-role per user.

## Capabilities
### New Capabilities
- `user-authentication`: login (GET+POST), self-service registration with tenant resolution, logout, session handling, and auth-user sharing to Inertia.
- `role-assignment`: default role on registration + admin single-role reassignment UI over the 4 fixed seeded roles.

### Modified Capabilities
- None. (`academic-offering-ui` role-gating is consumed unchanged; no existing spec requirement changes.)

## Approach
Thin controller mirroring the `academic-offering-management-ui` precedent (thin controller + module-namespaced Inertia page + server-computed props; no domain re-implementation). `AuthController` uses `Auth::attempt`/`Auth::login`/`Auth::logout` and Spatie's `assignRole('student')` on registration — no new Role/Permission domain entities (roles stay fixed/enum-like at this stage). Vue pages live in `Modules/Users/Resources/js/Pages/` (`Login.vue`, `Register.vue`, `Users/Edit.vue`).

**Tenant resolution (interim decision)**: registration form includes a **school `<select>`** populated from active schools; the chosen `school_id` is set on the new user (super-admin's `school_id = null` model unaffected). Chosen for zero new infra and true self-serve. Tradeoff: publicly enumerates school names. Preferred production path (subdomain-based resolution or per-school invite token) is a documented **follow-up**, not built now.

## Affected Areas
| Area | Impact | Description |
|------|--------|-------------|
| `routes/web.php` | Modified | Remove `/login` closure; point to `Modules/Users` routes |
| `Modules/Users/routes/web.php` | New/Modified | Named login/register/logout + user-edit routes |
| `Modules/Users/Infrastructure/Http/Controllers/AuthController.php` | New | login/register/logout actions returning `Inertia::render`/redirects |
| `Modules/Users/Infrastructure/Http/Controllers/UsersController.php` | Modified | `edit`/`update` for role reassignment (tenant-scoped) |
| `Modules/Users/Resources/js/Pages/` | New | `Login.vue`, `Register.vue`, `Users/Edit.vue` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Modified | Share `auth.user` (id, name, email, role) |
| Form Requests (`Modules/Users/.../Requests`) | New | Login/Register/UpdateUserRole validation |

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Public school `<select>` leaks/enumerates tenant list | Med | Accept as interim; flag subdomain/invite as follow-up; show name only, no counts |
| Admin edits user of another `school_id` | Med | Scope `update` query by `TenantContext`/`school_id`; authorize before save |
| Sharing role to frontend leaks data or drifts from Spatie 24h cache (`events_enabled=false`) | Med | Share single role name only; recompute per request, no cached permission arrays |
| New registration path breaks existing `role:staff/admin` gate | Low | Default role `student` never satisfies existing gates; no middleware changes |
| Registration creates users with no school (null `school_id`) | Low | `school_id` required in Register request; null reserved for super-admin seeder only |

## Rollback Plan
Restore the `/login` closure in `routes/web.php`, delete `AuthController`, the new Vue pages, the user-edit action, and the Register/Login Form Requests; revert the `HandleInertiaRequests::share()` addition. No schema changes (uses existing `users`, Spatie tables, seeded roles). Fully additive and reversible.

## Dependencies
- Existing: Laravel 12, nwidart modules, Inertia + Vue 3, `spatie/laravel-permission` (4 roles seeded, `teams:false`), `BelongsToTenant`/`TenantContext`, Sanctum. No new packages.
- `Modules/Users` `User` model already has `HasRoles` + `BelongsToTenant`.

## Success Criteria
- [ ] Visitor registers, selects a school, is created with `school_id` set and default `student` role.
- [ ] Login/logout work through real Inertia pages with session regeneration + throttling.
- [ ] Frontend receives `auth.user` (id, name, email, role); guests receive null.
- [ ] `staff/admin` can change a user's role via dropdown; cross-tenant edits are blocked.
- [ ] Existing `role:staff/admin` Academic gate still passes for admins, still rejects students.
- [ ] Permission-matrix UI and per-module permission registration confirmed OUT of scope.

## Proposal question round (for user review before spec/design)
Decisions were pre-aligned in conversation and formalized here; flagging assumptions that most change design if wrong:
1. **Tenant resolution = public school `<select>`** as the interim self-serve mechanism (subdomain/invite deferred). Confirm this tradeoff is acceptable.
2. **Default self-registered role = `student`.** Confirm.
3. **Single-role model** (one role per user via dropdown), not multi-role. Confirm.
4. **Auth sharing exposes only the primary role name**, not permission arrays. Confirm sufficient for now.
5. **`AuthController` lives in `Modules/Users`**, routes move off top-level `routes/web.php`. Confirm placement.
6. Open: should the role dropdown expose `super-admin` to a `staff/admin`, or only to `super-admin`? Design to decide (leaning: `super-admin` assignable only by `super-admin`).
