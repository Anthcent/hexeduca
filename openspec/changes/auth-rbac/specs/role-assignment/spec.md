# Spec: Role Assignment

## Capability: role-assignment

### Purpose
Give self-registered users a default role and let an authorized admin reassign a user's single role over the 4 fixed seeded Spatie roles (`student`, `teacher`, `staff/admin`, `super-admin`), tenant-scoped by `school_id`.

### Requirement: Self-registered users default to role `student`
Every user created through `POST /register` MUST be assigned exactly the `student` role and no other role.

#### Scenario: New registration gets the default role
- GIVEN a successful registration
- WHEN the user record is created
- THEN Spatie `assignRole('student')` is applied and no other role is attached

### Requirement: Authorized admin can reassign a user's role via a single-role dropdown
An authenticated user with role `staff/admin` or `super-admin` MUST be able to change another user's role through a single-select dropdown limited to the 4 fixed seeded roles, replacing (not adding to) the user's current role.

#### Scenario: Staff/admin reassigns a student to teacher
- GIVEN an authenticated `staff/admin` and a target user with role `student` in the same `school_id`
- WHEN the admin submits the edit form selecting `teacher`
- THEN the target user's role becomes `teacher` and the previous role is removed

#### Scenario: Non-admin cannot access the role-reassignment screen
- GIVEN an authenticated user with role `student` or `teacher`
- WHEN they request the user-edit route
- THEN the system responds with 403

### Requirement: Role reassignment is scoped to the admin's own tenant
The update action MUST reject attempts to modify a user whose `school_id` differs from the authenticated admin's `school_id`. The `users.edit`/`users.update` routes are per-tenant routes (no landlord-host constraint — reachable on the admin's own school subdomain; see `auth-rbac` design), so `TenantScope` scopes `User::findOrFail($id)` to the admin's own tenant only when a tenant is bound to the request (i.e. on a real tenant subdomain). `UserPolicy::assignRole`'s explicit `school_id` comparison is the enforcement mechanism that always applies, on any host, bound or not — `TenantScope` acts only as an additional narrowing filter when a tenant happens to be bound.

#### Scenario: Admin edits a user in their own school
- GIVEN a `staff/admin` with `school_id = 1` and a target user with `school_id = 1`, on that school's own subdomain
- WHEN the admin submits a role change for that user
- THEN the update succeeds

#### Scenario: Admin attempts to edit a user in a different school
- GIVEN a `staff/admin` with `school_id = 1` on their own subdomain, attempting to target a user with `school_id = 2`
- WHEN the admin submits a role change for that user
- THEN `TenantScope` already excludes the school-2 user from lookup on the admin's subdomain and, independently, `UserPolicy::assignRole` would reject the request (403) if reached; the target user's role is unchanged

### Requirement: Only `super-admin` can grant the `super-admin` role
When the selected role in the dropdown is `super-admin`, the update MUST be rejected unless the authenticated admin performing it already holds the `super-admin` role.

#### Scenario: Super-admin grants super-admin
- GIVEN an authenticated `super-admin` editing a target user
- WHEN they select `super-admin` and submit
- THEN the target user's role becomes `super-admin`

#### Scenario: Staff/admin cannot grant super-admin
- GIVEN an authenticated `staff/admin` editing a target user in their own school
- WHEN they select `super-admin` and submit
- THEN the system rejects the request and the target user's role is unchanged

### Requirement: Existing `role:staff/admin` gate on Academic routes keeps working unchanged
This change MUST NOT alter the behavior of the existing `role:staff/admin` middleware gate on Academic Offering routes.

#### Scenario: Staff/admin still passes the Academic gate
- GIVEN an authenticated `staff/admin` user
- WHEN they request an Academic Offering route gated by `role:staff/admin`
- THEN access is granted, unchanged from current behavior

#### Scenario: Student still rejected by the Academic gate
- GIVEN an authenticated `student` user (e.g. via self-registration)
- WHEN they request an Academic Offering route gated by `role:staff/admin`
- THEN the system responds with 403, unchanged from current behavior

## Out of Scope (explicit non-requirements)
Granular permission-matrix UI, per-module permission registration, attaching `permission:` records to roles, and multi-role per user are explicitly out of scope for this capability (see proposal `auth-rbac`).
