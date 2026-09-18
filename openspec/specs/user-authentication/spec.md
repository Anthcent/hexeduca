# Spec: User Authentication

## Capability: user-authentication

### Purpose
Provide the first real auth surface: self-service registration into a tenant, login, logout, and auth-state sharing to the Inertia frontend — replacing the current unreachable `POST /login` closure.

### Requirement: Visitor can self-register into a resolved school
The system MUST provide `GET /register` (Inertia page with a school `<select>`) and `POST /register`, registered on the landlord host only (see `auth-rbac` design). On valid submission it MUST create a `User` with the selected `school_id`, assign the default `student` role, and redirect the visitor to their chosen school's own subdomain login page (`School::loginUrl()`) with a `?registered=1` query flag — it MUST NOT authenticate the visitor, start a session on the landlord host, or rely on a session-based flash message (the redirect crosses hosts, and a landlord-host session cookie is never sent to the tenant subdomain).

#### Scenario: Valid registration creates a student in the chosen school
- GIVEN a visitor on the registration form with an active school listed
- WHEN they submit valid name/email/password and select that school
- THEN a `User` is created with that `school_id`, role `student` is assigned, and the visitor is redirected to that school's subdomain login page with a `?registered=1` query flag (e.g. `https://schoolA.app.com/login?registered=1`), which `Login.vue` reads to render a static "account created" message

#### Scenario: Registration does not establish a session on the landlord host
- GIVEN a visitor submitting a valid registration on the landlord host
- WHEN the `User` record is created
- THEN `Auth::login()` is never called, no session is regenerated, and the request ends with the visitor unauthenticated on the landlord host — the first authenticated session for this account is only ever established later, via `POST /login` on the school's own subdomain

#### Scenario: Registration is unreachable from a tenant subdomain
- GIVEN a tenant subdomain host (not a configured landlord host)
- WHEN a request is made to `GET /register` or `POST /register` on that host
- THEN the `RequireLandlordHost` middleware rejects the request (404), preventing a visitor on one tenant's subdomain from self-registering into a different school

#### Scenario: Duplicate email is rejected
- GIVEN an existing user with email `x@y.com`
- WHEN a new registration is submitted with the same email
- THEN the system returns a field-level validation error and creates no user

#### Scenario: Missing or invalid school selection is rejected
- GIVEN a registration submission with no `school_id` or a `school_id` that does not match an active school
- WHEN the form is submitted
- THEN the system returns a field-level validation error and creates no user

### Requirement: Registered/existing user can log in and out through real pages
The system MUST provide `GET /login` (Inertia page) and `POST /login` using `Auth::attempt`, applying `throttle` and `session()->regenerate()` on success, registered with no route-level host constraint — reachable both on a school's own subdomain (e.g. `schoolA.app.com/login`) and on the landlord host (see `auth-rbac` design and the "Login on the landlord host is restricted to the super-admin" requirement below for the landlord-host case). The system MUST provide `POST /logout` that invalidates the session and regenerates the CSRF token, also with no route-level host constraint.

#### Scenario: Valid credentials start an authenticated session on the user's own subdomain
- GIVEN a registered user with valid credentials, on their school's own subdomain (e.g. `schoolA.app.com`)
- WHEN they submit `POST /login`
- THEN they are authenticated, the session ID is regenerated, and they are redirected to an authenticated area — with `Auth::attempt`'s user lookup naturally scoped to that tenant by `TenantScope`

#### Scenario: Invalid credentials are rejected without authenticating
- GIVEN a registered user
- WHEN they submit `POST /login` with a wrong password
- THEN authentication fails and no session is established

#### Scenario: Excessive login attempts are throttled
- GIVEN repeated failed login attempts from the same client beyond the throttle threshold
- WHEN another `POST /login` is submitted
- THEN the request is rejected by throttling before credentials are checked

#### Scenario: Logout ends the session
- GIVEN an authenticated user
- WHEN they submit `POST /logout`
- THEN the session is invalidated, the CSRF token is regenerated, and subsequent requests are treated as unauthenticated

### Requirement: Login on the landlord host is restricted to the super-admin
`/login` carries no route-level host constraint (it is reachable both on a tenant subdomain and on the landlord host). On the landlord host, `ResolveTenant` binds no tenant, so `TenantScope` no-ops and `Auth::attempt`'s user lookup is globally unscoped. The system MUST reject a successful `Auth::attempt` on the landlord host unless the authenticated user has the `super-admin` role: it MUST log that user out immediately (invalidate session, regenerate CSRF token) and return the same generic invalid-credentials error used for a wrong password, without revealing that the account exists but is barred from this host.

#### Scenario: Non-super-admin login attempt on the landlord host is rejected
- GIVEN a registered user who does not have the `super-admin` role (e.g. `student`, `teacher`, or `staff/admin` of some school), with valid credentials
- WHEN they submit `POST /login` on the landlord host (e.g. `admin.app.com`)
- THEN `Auth::attempt` may succeed internally, but the system immediately logs them out and returns the same generic `assertSessionHasErrors('email')` used for invalid credentials; no session remains authenticated

#### Scenario: Super-admin login attempt on the landlord host succeeds
- GIVEN the seeded super-admin user (`school_id = null`, role `super-admin`, created by `SuperAdminUserSeeder`), with valid credentials
- WHEN they submit `POST /login` on the landlord host
- THEN they are authenticated, the session ID is regenerated, and they are redirected to an authenticated area

#### Scenario: Super-admin login attempt on a tenant subdomain already fails, no new check needed
- GIVEN the seeded super-admin user (`school_id = null`)
- WHEN they submit `POST /login` on any real tenant subdomain (e.g. `schoolA.app.com`)
- THEN `TenantScope` already excludes the `school_id = null` row from the scoped `User::where('email', ...)` lookup, so `Auth::attempt` fails naturally — this is existing `TenantScope` behavior and requires no additional host-restriction logic

### Requirement: Inertia frontend receives the authenticated user's identity and role
`HandleInertiaRequests::share()` MUST expose `auth.user` as `{ id, name, email, role }` (single primary role name, no permission arrays) for authenticated requests, and `null` for guests.

#### Scenario: Authenticated request shares user identity and role
- GIVEN an authenticated user with an assigned role
- WHEN any Inertia page is rendered for that request
- THEN `auth.user` contains that user's `id`, `name`, `email`, and single role name

#### Scenario: Guest request shares null
- GIVEN no authenticated session
- WHEN any Inertia page is rendered for that request
- THEN `auth.user` is `null`

### Requirement: Protected routes reject unauthenticated requests
Routes gated by `auth` (or `auth` + `role:*`) MUST reject requests with no authenticated session per the app's existing auth convention.

#### Scenario: Unauthenticated access to a protected route is rejected
- GIVEN no authenticated session
- WHEN a route gated by `auth` is requested
- THEN the request is rejected (redirect to login or 401/403), consistent with existing gated routes

## Out of Scope (explicit non-requirements)
Password reset, email verification, and multi-role-per-user are explicitly out of scope for this capability (see proposal `auth-rbac`).
