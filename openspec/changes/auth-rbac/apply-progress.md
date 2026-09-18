# Apply Progress: Auth + Role Assignment (auth-rbac)

**Batch 1 of 3 (PR 1: login/register/logout backend + tests) — COMPLETE**
**Batch 2 of 3 (PR 2: role-assignment backend + tests) — COMPLETE**
**Batch 3 of 3 (PR 3: Inertia auth.user sharing + Vue pages + final verification) — COMPLETE — FINAL**

## ALL BATCHES COMPLETE — 28/28 tasks done. Ready for sdd-verify.

## Workload / PR Boundary
- Mode: chained PR slice (stacked-to-main / feature-branch-chain still `pending` at the tasks-artifact level — orchestrator must resolve before any PR is opened)
- Batch 1 (Unit 1): `RequireLandlordHost`, route changes, `LoginRequest`/`RegisterRequest`, `AuthController`, `School::loginUrl()`, backend tests (Phases 1-3, login/register/logout subset of Phase 6).
- Batch 2 (Unit 2): `UpdateUserRoleRequest`, `UserPolicy`, `UsersServiceProvider` `Gate::policy()` wiring, `UsersController::edit`/`update`, `users.edit`/`users.update` routes, role-assignment backend tests (Phase 4, role-assignment subset of Phase 6).
- Batch 3 (Unit 3 — this batch, FINAL): `HandleInertiaRequests::share()` lazy `auth.user` prop (Phase 5), 3 Vue 3 SFCs (`Login.vue`, `Register.vue`, `Edit.vue`) (Phase 7), and final full-suite + style verification (Phase 8). Starts from Batch 2's clean, green state (127/127); ends with 130/130 passing, zero regressions, style-clean, all 28 tasks `[x]`.

## Completed Tasks

### Phase 1: Middleware + Routes (Batch 1)
- [x] 1.1 Created `app/Tenancy/Http/Middleware/RequireLandlordHost.php`.
- [x] 1.2 `routes/web.php` — removed the old unreachable `POST /login` closure.
- [x] 1.3 `Modules/Users/routes/web.php` — added `login`/`logout`/`register` routes (register gated). `users.edit`/`users.update` added in Batch 2 (see Phase 4 below).

### Phase 2: FormRequests (Batch 1)
- [x] 2.1 Created `LoginRequest.php`.
- [x] 2.2 Created `RegisterRequest.php`.

### Phase 3: AuthController + School helper (Batch 1)
- [x] 3.1 `School::loginUrl()` helper.
- [x] 3.2 Created `AuthController` — `showLogin`, `showRegister`.
- [x] 3.3 `AuthController::login`.
- [x] 3.4 `AuthController::register`.
- [x] 3.5 `AuthController::logout`.

### Phase 4: Role Assignment (Policy + UsersController) — Batch 2
- [x] 4.1 Created `Modules/Users/Infrastructure/Http/Requests/UpdateUserRoleRequest.php` — `role` required|`Rule::in(['student','teacher','staff/admin','super-admin'])`, `authorize()` returns `true` (route middleware + policy do the real authorization).
- [x] 4.2 Created `Modules/Users/Infrastructure/Policies/UserPolicy.php` — `assignRole(User $actor, User $target, string $role): bool`, exactly per design: `super-admin` bypasses everything; otherwise `$actor->school_id !== null && $actor->school_id === $target->school_id && $role !== 'super-admin'`.
- [x] 4.3 `Modules/Users/Infrastructure/Providers/UsersServiceProvider.php::boot()` — added `Gate::policy(User::class, UserPolicy::class)`.
- [x] 4.4 `UsersController::edit($id)` — `User::findOrFail($id)`, `$this->authorize('assignRole', [$user, $user->getRoleNames()->first()])`, renders `Users::Edit` with `{user: {id,name,email,role}, roles: [4 fixed roles]}`.
- [x] 4.5 `UsersController::update(UpdateUserRoleRequest $request, $id)` — `findOrFail`, `$this->authorize('assignRole', [$user, $role])`, `$user->syncRoles([$role])`, `back()->with('success', ...)`.
- [x] Added `users.edit`/`users.update` routes to `Modules/Users/routes/web.php`, gated `['auth', 'role:staff/admin|super-admin']`, per-tenant.
- [x] Added `AuthorizesRequests` trait to base `App\Http\Controllers\Controller`.

### Phase 5: Inertia Auth Sharing — Batch 3 (this batch)
- [x] 5.1 Modified `app/Http/Middleware/HandleInertiaRequests.php::share()` — added a lazy `auth` prop: `['user' => fn () => $request->user() ? {id, name, email, role: getRoleNames()->first()} : null]`. Lazy closure (Inertia partial-reload friendly, matches the existing `flash` prop pattern in the same file), recomputed per request so a `syncRoles()` write is reflected on the very next request, per design's "single role name in share(), recomputed per request" decision.

### Phase 6 (subset): Backend Tests — login/register/logout (Batch 1)
- [x] 6.1 `tests/Feature/Auth/RegistrationTest.php` — 5 tests.
- [x] 6.2 `tests/Feature/Auth/LoginLogoutTest.php` — 4 tests.
- [x] 6.3 `tests/Feature/Auth/LandlordHostLoginTest.php` — 3 tests.
- [x] 6.4 `tests/Feature/Auth/HostScopingTest.php` — 2 tests.

### Phase 6 (subset): Backend Tests — role assignment (Batch 2)
- [x] 6.5 Created `tests/Feature/Users/RoleAssignmentTest.php` — 5 tests.
- [x] 6.6 Created `tests/Feature/Users/AcademicGateRegressionTest.php` — 2 tests.
- [x] 6.7 Created `tests/Unit/Policies/UserPolicyTest.php` — 5 tests.

### Phase 7: Vue Pages — Batch 3 (this batch)
- [x] 7.1 Created `Modules/Users/Resources/js/Pages/Register.vue` — `<script setup>` + `useForm({school_id, name, email, password, password_confirmation})`, school `<select>` populated from the `schools` prop, `AppLayout` wrapper, `form.errors.<field>` display for every `RegisterRequest` key, posts to `route('register')`.
- [x] 7.2 Created `Modules/Users/Resources/js/Pages/Login.vue` — `<script setup>` + `useForm({email, password})`, reads the `registered: Boolean` prop passed by `AuthController::showLogin` (`$request->boolean('registered')` — server-side query-param parsing rather than client-side `URLSearchParams` re-parsing, since the prop is already supplied) to render a static "Account created — log in below." banner, `form.errors.email`/`form.errors.password` display, posts to `route('login')`.
- [x] 7.3 Created `Modules/Users/Resources/js/Pages/Edit.vue` — role-reassignment form, `<script setup>` + `useForm({role: props.user.role})`, `role` `<select>` over the `roles` prop (the 4 fixed roles), `form.errors.role` display, `form.put(route('users.update', props.user.id))`. **Path deviation from design.md/tasks.md — see "Deviations from Design" below.**

### Phase 8: Verification — Batch 3 (this batch)
- [x] 8.1 Full Pest suite run in Docker: **130 passed, 0 failed, 413 assertions** (127 from Batches 1-2 + 3 new `InertiaAuthSharingTest` tests). Zero regressions across the entire suite, including all pre-existing `academic-core-structure`/`Academic` Feature tests, `Broadcasting`, `SecurityBaseline`, and `Foundation` tests.
- [x] 8.2 Manual smoke — covered via automated Feature test coverage rather than an interactive browser session in this environment (no browser/Docker port forwarding available to this agent): `RegistrationTest` exercises register-on-landlord-host -> redirect to tenant subdomain login with `?registered=1`; `LoginLogoutTest`/`LandlordHostLoginTest` exercise login as a new student, login as seeded super-admin on the landlord host, and rejection of non-super-admin login on the landlord host; `RoleAssignmentTest` exercises role reassignment as staff/admin and the super-admin escalation guard. All pass. `Login.vue`'s `registered` banner and the 3 new Vue pages themselves were verified by static review against `AuthController`/`UsersController`'s prop contracts and the project's `resources/js/app.js` Inertia resolver (no JS test runner — only Playwright e2e and Vite are configured in `package.json`; no Playwright spec was added, which is a residual verification gap noted below).

### Pre-existing test fix (Batch 1, required by that batch's route replacement)
- [x] `tests/Feature/FoundationTest.php` — updated to match the new `AuthController::login` contract.

## Files Changed

### Batch 1
| File | Action | What Was Done |
|------|--------|---------------|
| `app/Tenancy/Http/Middleware/RequireLandlordHost.php` | Created | Landlord-host-only route guard |
| `routes/web.php` | Modified | Removed the old unreachable `POST /login` JSON closure |
| `Modules/Users/routes/web.php` | Modified | Added `login`/`logout`/`register` routes |
| `Modules/Users/Infrastructure/Http/Requests/LoginRequest.php` | Created | `email`/`password` validation |
| `Modules/Users/Infrastructure/Http/Requests/RegisterRequest.php` | Created | `school_id`/`name`/`email`/`password` validation |
| `Modules/Users/Infrastructure/Http/Controllers/AuthController.php` | Created | `showLogin`, `showRegister`, `login`, `register`, `logout` |
| `app/Tenancy/Models/School.php` | Modified | Added `loginUrl(): string` helper |
| `tests/Feature/Auth/RegistrationTest.php` | Created | 5 tests |
| `tests/Feature/Auth/LoginLogoutTest.php` | Created | 4 tests |
| `tests/Feature/Auth/LandlordHostLoginTest.php` | Created | 3 tests |
| `tests/Feature/Auth/HostScopingTest.php` | Created | 2 tests |
| `tests/Feature/FoundationTest.php` | Modified | Updated 1 pre-existing test |

### Batch 2
| File | Action | What Was Done |
|------|--------|---------------|
| `Modules/Users/Infrastructure/Http/Requests/UpdateUserRoleRequest.php` | Created | `role` validated against the 4 fixed seeded roles |
| `Modules/Users/Infrastructure/Policies/UserPolicy.php` | Created | `assignRole(actor, target, role)`, exactly per design |
| `Modules/Users/Infrastructure/Providers/UsersServiceProvider.php` | Modified | `Gate::policy(User::class, UserPolicy::class)` in `boot()` |
| `Modules/Users/Infrastructure/Http/Controllers/UsersController.php` | Modified | Implemented `edit`/`update`, added `ROLES` const |
| `Modules/Users/routes/web.php` | Modified | Excluded `edit`/`update` from `Route::resource('users', ...)`; added gated `users.edit`/`users.update` routes |
| `app/Http/Controllers/Controller.php` | Modified | Added `AuthorizesRequests` trait |
| `tests/Feature/Users/RoleAssignmentTest.php` | Created | 5 tests |
| `tests/Feature/Users/AcademicGateRegressionTest.php` | Created | 2 tests |
| `tests/Unit/Policies/UserPolicyTest.php` | Created | 5 tests |
| `openspec/changes/auth-rbac/tasks.md` | Modified | Marked Phase 4 and 6.5-6.7 `[x]` |

### Batch 3 (this batch, FINAL)
| File | Action | What Was Done |
|------|--------|---------------|
| `app/Http/Middleware/HandleInertiaRequests.php` | Modified | Added lazy `auth.user` prop `{id, name, email, role}` / `null` for guests |
| `Modules/Users/Resources/js/Pages/Login.vue` | Created | Login form; `registered` prop -> static confirmation banner |
| `Modules/Users/Resources/js/Pages/Register.vue` | Created | Registration form with school `<select>` |
| `Modules/Users/Resources/js/Pages/Edit.vue` | Created | Role-reassignment form (see path deviation note below) |
| `tests/Feature/Auth/InertiaAuthSharingTest.php` | Created | 3 tests: guest `auth.user` is `null`; authenticated `auth.user` shape/role; role change reflected on the next request |
| `openspec/changes/auth-rbac/tasks.md` | Modified | Marked Phase 5, 7, 8 `[x]` — all 28 tasks now `[x]` |

## Deviations from Design

**Batches 1-2**: None — see prior batches' notes (the `AuthorizesRequests` trait addition to the base `Controller`, needed to make `$this->authorize(...)` callable at all, was the only implementation detail not explicitly spelled out in design.md, and is standard Laravel scaffolding with no effect on other controllers).

**Batch 3 — one real deviation, found and fixed during implementation**:
`design.md`'s File Changes table and `tasks.md` task 7.3 both specify `Modules/Users/Resources/js/Pages/Users/Edit.vue` (nested under a `Users/` subfolder). This path is **incompatible** with the project's actual Inertia page resolver in `resources/js/app.js`:
```js
if (name.includes('::')) {
    const [moduleName, pageName] = name.split('::');
    return resolvePageComponent(`../../Modules/${moduleName}/Resources/js/Pages/${pageName}.vue`, modulePages);
}
```
`UsersController::edit()` (written in Batch 2) calls `Inertia::render('Users::Edit', ...)`. The resolver splits only on `::`, giving `moduleName = 'Users'`, `pageName = 'Edit'` — it does **not** further split `pageName` on `/`. The resulting lookup path is therefore `Modules/Users/Resources/js/Pages/Edit.vue` (flat), not `Modules/Users/Resources/js/Pages/Users/Edit.vue`. Had the file been created at the nested path exactly as written in design.md/tasks.md, `resolvePageComponent` would fail to find the component in the `import.meta.glob` map at runtime (a real, previously-undetected bug, invisible to the PHP Feature test suite because `assertInertia()`/`Inertia::render()` assertions only check the component *name* server-side and never actually resolve or compile the Vue file).

**Fix applied**: created the file at `Modules/Users/Resources/js/Pages/Edit.vue` (flat, matching the actual resolver behavior and the `Login.vue`/`Register.vue` pages, which already correctly sit at the flat path because `AuthController` renders `'Users::Login'`/`'Users::Register'` with no internal `/`). No PHP-side change was needed or made — `UsersController::edit()`'s `Inertia::render('Users::Edit', ...)` call (from Batch 2) is correct as-is and untouched; only the Vue file's location was corrected to match it. `tasks.md` task 7.3 has been annotated with this correction. This is a filesystem-path correction only — the page's props, form fields, and behavior match the design/spec exactly.

**Batch 3 — noted verification gap (not a code deviation)**: Phase 8.2's "manual smoke" was executed as automated Feature-test coverage of the equivalent server-side flows (see Phase 8 task notes above) rather than an interactive browser session, since no browser or forwarded dev-server port was available to this agent in the Docker environment. The 3 new Vue pages were verified by static review against their prop contracts and the Inertia resolver, and by the fact that `php artisan test` (which does render `Inertia::render()` responses, just not compile Vue) exercises every route that serves them without error. No JS test runner is configured in this project (`package.json` only has Vite + Playwright e2e, no unit/component test runner), so no automated Vue-level test was added; this matches the design's own Testing Strategy table, which lists only Feature/Unit (PHP) coverage and treats Vue pages as manual-smoke items.

## Issues Found
None beyond the `Edit.vue` path/resolver mismatch documented above, which was caught and fixed during this batch before it could reach a PR.

## Test / Quality Gate Results (real run, Docker)
- `docker compose exec -T app php artisan test tests/Feature/Auth` (isolated re-run, loads the shared `tenantUrl()` Pest helper) → **17 passed, 93 assertions**, including the 3 new `InertiaAuthSharingTest` tests.
- `docker compose exec -T app ./vendor/bin/pint --test` → **229 files, 0 style issues**.
- `docker compose exec -T app php artisan test` (full suite) → **130 passed, 0 failed, 413 assertions**, duration ~430s. Zero regressions — 127 tests from Batches 1-2 plus 3 new Batch 3 tests, all green.

## Remaining Tasks
None. All 28 tasks across Phases 1-8 are `[x]` in `tasks.md`.

## Status
**ALL BATCHES COMPLETE.** 28/28 tasks done, 130/130 tests passing (0 failures, 413 assertions), 0 Pint style issues, no leftover TODO/stub markers in any file this change touched. One filesystem-path deviation from design.md was found and corrected during Batch 3 (see "Deviations from Design" — `Edit.vue` placed flat, not nested under `Users/`, to match the actual Inertia resolver). Ready for `sdd-verify`.
