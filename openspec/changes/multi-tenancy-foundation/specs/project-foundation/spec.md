# Delta for project-foundation

## MODIFIED Requirements

### Requirement: Base roles and permission scaffolding are seeded
The system MUST seed four base roles — student, teacher, staff/admin, super-admin — via a database seeder using spatie/laravel-permission, with Sanctum configured for SPA authentication. Roles remain GLOBAL across all tenants (Spatie `permission.teams` stays `false`): a role label (e.g. `teacher`) carries the same meaning at every school. Tenant isolation of people and their data is enforced via `users.school_id`, not per-tenant role rows. The seeder's docblock MUST describe global-role-in-multi-tenant semantics, not single-institution semantics.
(Previously: seeder docblock and role model assumed a single-institution deployment with no tenant concept.)

#### Scenario: Roles exist after seeding
- GIVEN a fresh database
- WHEN `sail artisan db:seed` is run
- THEN exactly the roles student, teacher, staff/admin, super-admin exist in the `roles` table
- AND no permissions beyond scaffolding placeholders are attached (no business-logic permissions)

#### Scenario: Landlord bootstrap fails closed
- GIVEN bootstrap email or password is absent, invalid, or uses a weak placeholder
- WHEN `sail artisan db:seed` attempts to create the landlord super-admin account
- THEN seeding fails before creating that account
- AND no predictable email or password fallback is used

#### Scenario: Explicit landlord bootstrap succeeds
- GIVEN explicit valid email and a password of at least 16 characters containing mixed case, numbers, and symbols
- WHEN `sail artisan db:seed` is run
- THEN the configured landlord account is created or updated with `school_id = NULL`
- AND the `super-admin` role is assigned
- AND this account is distinct from any per-tenant `staff/admin` account

#### Scenario: Sanctum SPA session issued
- GIVEN a seeded user with a valid role belonging to a resolved tenant
- WHEN the frontend authenticates via the Sanctum SPA flow (CSRF cookie + login) on that tenant's subdomain
- THEN a valid session/token is issued and subsequent authenticated requests succeed

## ADDED Requirements

### Requirement: users.school_id nullable tenant/landlord marker column
The system MUST add a nullable `school_id` foreign key column to the `users` table (root migration `0001_01_01_000000_create_users_table.php`). `NULL` marks a landlord account; every tenant user MUST have a non-null `school_id`.

#### Scenario: Tenant user has a non-null school_id
- GIVEN a user is created through the normal tenant registration/seeding path
- WHEN the record is persisted
- THEN `school_id` is set to the resolved tenant's id

#### Scenario: Landlord account has a null school_id
- GIVEN the landlord seeder creates the platform operator account
- WHEN the record is persisted
- THEN `school_id` is `NULL`, and the account is not addressable as a member of any tenant

### Requirement: Sanctum stateful domains and session cookie are wildcard-subdomain-aware
The system MUST resolve Sanctum's `stateful` domains dynamically at boot to match the `*.app.com` wildcard, rather than a static domain list, and MUST set `SESSION_DOMAIN=.app.com` so the session/CSRF cookie is valid across tenant subdomains and the landlord host.

#### Scenario: CSRF/session works across two tenant subdomains
- GIVEN a user authenticated on `school1.app.com`
- WHEN a subsequent stateful request is made from `school1.app.com`
- THEN the session cookie and CSRF token are honored without a domain-mismatch rejection

#### Scenario: New subdomain works without a code/config change
- GIVEN a newly created `School` with subdomain `school2`
- WHEN a client authenticates on `school2.app.com`
- THEN Sanctum treats it as a valid stateful domain without adding it to a static list

#### Scenario: Non-wildcard host is not treated as stateful
- GIVEN a request from a host outside `*.app.com` (and outside the configured landlord host)
- WHEN a stateful-only action is attempted
- THEN it is not granted stateful/session trust reserved for `*.app.com` and the landlord host
