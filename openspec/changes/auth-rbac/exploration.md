# Exploration: auth-rbac

## Goal

Real login flow + extensible role-based access control (RBAC) for this multi-tenant Laravel school-management SaaS. When a user is provisioned, they get assigned a role; admins can check/uncheck which functions, modules, and views each role has access to. The module/permission catalog must grow as new modules are built — it cannot be a hardcoded, one-time list.

## Current State

Auth today is a single closure `POST /login` in `routes/web.php` (`Auth::attempt` + session regenerate) — no controller, no login page, no logout, no register.

RBAC plumbing is more built than assumed:
- `spatie/laravel-permission ^6.25` is installed; migration `database/migrations/2026_07_11_100001_create_permission_tables.php` already created the roles/permissions tables.
- `database/seeders/RoleAndPermissionSeeder.php` already seeds 4 GLOBAL roles (`student`, `teacher`, `staff/admin`, `super-admin`). `teams: false` in `config/permission.php` is an explicit, documented design decision — roles are not per-tenant.
- `SuperAdminUserSeeder.php` provisions a landlord super-admin with `school_id = null`.
- `bootstrap/app.php` already registers Spatie's `role`/`permission`/`role_or_permission` middleware aliases, and `Modules/Academic/routes/web.php` already uses `role:staff/admin` on its admin routes — role-gating is a proven working pattern, not something to invent.
- What's missing entirely: login UI, logout, any permission actually attached to roles, and any per-module permission-registration mechanism.
- `HandleInertiaRequests` shares only `flash` today — no authenticated user or roles/permissions reach the frontend yet.

`modules_statuses.json` confirms 7 enabled modules (`Users`, `Academic`, `Schedule`, `Grades`, `Files`, `Admin`, `Notifications`); only `Users` (partial) and `Academic` (full) have real content — the other 5 are bare nwidart scaffolds, concretely proving the "catalog must grow" requirement.

`Modules/Users/Domain/Entities/User.php` and `UserRepositoryInterface.php` exist but there are no Role/Permission domain entities yet. `Modules/Users/Infrastructure/Database/Migrations/` is empty.

Frontend stack is Inertia + Vue 3 (confirmed in `academic-offering-management-ui`). Existing module pattern to mirror: hexagonal layering (`Domain/{Entities,ValueObjects,Repositories,Events}`, `Application/{UseCases,DTOs}`, `Infrastructure/{Models,Persistence,Database/Migrations}`).

## Affected Areas

- `routes/web.php` / `Modules/Users/Infrastructure/Http/Controllers/` — needs a real `AuthController` (GET/POST login, POST logout).
- `app/Http/Middleware/HandleInertiaRequests.php` — must share authenticated user + roles/permissions for the frontend.
- `Modules/Users/Domain/` — no Role/Permission domain entities yet.
- Each module's `Infrastructure/Providers/*ServiceProvider.php` (7 total) — candidate hook point for permission registration; none exists today.
- `database/seeders/RoleAndPermissionSeeder.php` — creates roles but attaches zero permissions.
- `resources/js/Layouts/AppLayout.vue`, `resources/js/Pages/` — no login page or RBAC admin screen exists.

## Approaches Considered (permission catalog extensibility)

1. **Static registration via module ServiceProviders.** Each module declares `[module, permission-key, label, group]` via a new `PermissionRegistry`, synced to DB via a console command. Pros: matches the existing per-module provider convention; catalog grows automatically as modules are built. Cons: needs a boot-order + naming-collision convention. Effort: Medium.
2. **Config-file catalog per module** (`Modules/{X}/config/permissions.php`), collected centrally. Pros: minimal code change, fits the existing `registerConfig()` merge pattern. Cons: two sources of truth if sync is missed; less flexible for runtime-dependent permissions. Effort: Low-Medium.
3. **Fully dynamic/DB-driven catalog** with no code declaration. Pros: maximum flexibility. Cons: drifts from what code actually enforces — rejected as the sole mechanism. Effort: Medium, high drift risk.

**Direction for `sdd-propose`**: hybrid of (1)/(2) — code stays the source of truth for which permissions exist and are synced into Spatie's tables; the admin UI only toggles role↔permission checkboxes, it never invents new permission strings.

## Open Questions (not decided — for sdd-propose)

- Login controller placement: `Modules/Users` vs top-level.
- Registration: admin-provisioned only (likely, given the seeded-only user base and school/tenant model) vs. a self-serve path — flag explicitly, don't assume.
- Scope/redaction of what `HandleInertiaRequests` shares about the user per role.

## Risks

- `teams: false` in `config/permission.php` is a deliberate decision tying role meaning to global labels, with tenant isolation handled separately via `school_id`/`BelongsToTenant`. Must be revisited deliberately, not silently overridden, if any requirement needs school-varying permissions for the same role.
- `staff/admin` is already used in a live `role:staff/admin` gate on Academic routes with zero permissions attached — rollout must not break that gate; likely needs `staff/admin` granted all relevant permissions by default.
- Spatie roles being global does NOT automatically stop a `staff/admin` at school A from managing school B's roles/users — tenant-aware enforcement must be added explicitly via `TenantContext`, it is not a side effect of the permission middleware.
- Spatie permission cache (`events_enabled: false`) — cache invalidation behavior on role/permission changes needs verification.
- No existing per-module permission-registration hook — this is new architecture, higher design uncertainty than typical CRUD work.

## Ready for Proposal

Yes.
