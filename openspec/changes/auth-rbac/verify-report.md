# Verify Report: Auth + Role Assignment (auth-rbac)

**Verdict: PASS**

## Independently Re-Run Evidence (not copied from apply-progress.md)

| Check | Command | Result |
|---|---|---|
| Full Pest suite | docker compose exec -T app php artisan test | 130 passed, 0 failed, 413 assertions, ~431s. Matches claimed numbers exactly. |
| Pint style | docker compose exec -T app ./vendor/bin/pint --test | PASS, 229 files, zero violations. Matches claim. |
| TODO/stub sweep | grep across all 16 backend/frontend files this change touched | No matches. Clean. |

## Source-Level Verification (design.md decisions checked against actual code, not test names)

1. RequireLandlordHost vs ResolveTenant normalization parity - Both use the identical pattern: strtolower on request host compared with in_array against array_map strtolower over config tenancy.landlord_hosts, strict comparison. Confirmed byte-for-byte equivalent normalization in app/Tenancy/Http/Middleware/RequireLandlordHost.php lines 24-27 and app/Tenancy/Http/Middleware/ResolveTenant.php lines 28-32. AuthController::onLandlordHost() (private helper, lines 121-127) uses the exact same third copy of this logic for the landlord-host login check. No casing-mismatch gap between the three call sites.

2. AuthController::login landlord-host super-admin gate - Confirmed in Modules/Users/Infrastructure/Http/Controllers/AuthController.php lines 60-83: Auth::attempt runs first; on success, session regenerate happens, then a check for onLandlordHost AND NOT hasRole super-admin triggers Auth::logout plus session invalidate plus regenerateToken plus throws a ValidationException with the generic message. The same INVALID_CREDENTIALS_MESSAGE constant (line 32) is used both for a wrong password (lines 65-67) and for the landlord-host rejection (lines 77-79) - genuinely indistinguishable error responses, no user-enumeration leak. Matches design.md's landlord-host-restricted-to-super-admin decision exactly.

3. AuthController::register - no auto-login, correct redirect target - Confirmed in AuthController.php lines 93-109: creates the User with explicit school_id, calls assignRole('student'), and returns a redirect away to School::loginUrl() plus the registered query flag. No Auth::login() call anywhere in the method or file. School::loginUrl() (app/Tenancy/Models/School.php lines 62-68) builds the URL using request()->getScheme() (not hardcoded), matching design.md exactly. redirect()->away(...) is correct since this is a genuine cross-host redirect.

4. UserPolicy::assignRole matches the exact design rule - Modules/Users/Infrastructure/Policies/UserPolicy.php lines 25-34 is essentially identical to the design.md pseudocode: super-admin bypasses everything (any tenant, any role); otherwise actor school_id is not null AND actor school_id equals target school_id AND role is not super-admin. Registered via Gate::policy(User::class, UserPolicy::class) inside UsersServiceProvider::boot() (line 36) - confirmed present and correctly placed in boot() (not register()), with a comment explaining why auto-discovery would miss it (model lives outside App\Models).

5. HandleInertiaRequests::share() - app/Http/Middleware/HandleInertiaRequests.php lines 43-50: lazy closure returning user id, name, email, role (getRoleNames first) when authenticated, null for guests. Matches the spec's shape exactly.

6. Academic gate regression is genuinely tested, not just named - Read tests/Feature/Users/AcademicGateRegressionTest.php directly: it issues a real HTTP GET to the Academic module's ofertas/create route (gated by role:staff/admin) via actingAs, asserting assertOk() for staff/admin and assertForbidden() for student. This is a real runtime request through the full middleware stack, not a mocked or renamed pre-existing test - confirms no regression from Gate::policy() registration, the new AuthorizesRequests trait on the base Controller, or the new routes.

7. Routes match design.md's File Changes table exactly - Modules/Users/routes/web.php: users resource route excludes edit/update; users.edit/users.update under auth plus role:staff/admin|super-admin with no host constraint; /login (GET named login, POST throttled) and /logout host-unconstrained; /register (GET+POST) wrapped in RequireLandlordHost. Root routes/web.php confirmed the old unreachable POST /login closure is gone - only the welcome route remains.

8. Edit.vue flat-path deviation is real and correctly resolved - Confirmed Modules/Users/Resources/js/Pages/ contains Edit.vue, Login.vue, Register.vue all flat (no Users/ subfolder). Traced resources/js/app.js's Inertia resolve(): for a name containing double-colon, it splits only on that separator giving moduleName Users, pageName Edit, then builds the path Modules/Users/Resources/js/Pages/Edit.vue - it does not further split pageName on a slash. UsersController::edit() calls Inertia::render('Users::Edit', ...) (grep-confirmed). The flat file placement is therefore the only path that actually resolves at runtime; had the file been created nested under Users/Edit.vue per design.md's literal table entry, resolvePageComponent would fail to find it in the import.meta.glob map. This is a correctly-identified and correctly-fixed infrastructure bug, not scope creep - apply-progress's deviation note is accurate and the PHP call site required no change.

9. FormRequests match spec validation rules - RegisterRequest: school_id required/integer/exists(schools,id where is_active); name required/string/max 255; email required/email/unique; password required/confirmed/min 8. UpdateUserRoleRequest: role required, in the 4 fixed seeded roles - both authorize() methods correctly return true with comments explaining route-middleware/policy is the real gate (not defense-in-depth theater - accurately documented).

10. Vue pages match the form/error contracts - Login.vue reads a registered boolean prop to show the static banner, posts email/password to the login route, displays field errors. Register.vue posts all 5 RegisterRequest fields with a school_id select populated from the schools prop and displays errors for every field except password_confirmation (correct - Laravel's confirmed rule attaches the error to password, not password_confirmation). Edit.vue renders a role select limited to the roles prop (the 4 fixed roles), submits a PUT to users.update, displays the role error.

## Tasks / Apply-Progress Cross-Check

- 28/28 tasks marked complete in tasks.md; every task traced to real files/behavior verified above - no phantom completions found.
- apply-progress.md's claimed test/lint numbers (130 passed / 413 assertions, Pint 229 files) match this independent re-run exactly.
- The one documented deviation (Edit.vue flat path vs. design.md's nested Users/Edit.vue) was independently traced through resources/js/app.js and confirmed correct and necessary, not a shortcut.

## Scope Completeness Against Both Spec Files

user-authentication/spec.md - all 5 requirements covered: self-registration (landlord-host-only, no auto-login, correct redirect, duplicate-email/invalid-school rejection - RegistrationTest), login/logout with throttle+session-regenerate (LoginLogoutTest), landlord-host super-admin restriction (LandlordHostLoginTest), Inertia auth.user sharing (InertiaAuthSharingTest), protected-route rejection of unauthenticated requests (pre-existing auth middleware convention, exercised incidentally by RoleAssignmentTest's non-admin-403 case and AcademicGateRegressionTest). No gaps found.

role-assignment/spec.md - all 5 requirements covered: default student role on registration (RegistrationTest), staff/admin role reassignment via dropdown + non-admin 403 (RoleAssignmentTest, UserPolicyTest), tenant-scoped reassignment (RoleAssignmentTest cross-tenant case), super-admin-only super-admin grant (RoleAssignmentTest, UserPolicyTest), unchanged Academic gate (AcademicGateRegressionTest, independently confirmed as a real HTTP test above). No gaps found.

## Issues

None CRITICAL. None WARNING. One pre-existing SUGGESTION-level item already flagged by apply-progress itself and not part of this change's required scope: no JS/component test runner exists in this project (only Vite + Playwright e2e configured), so the 3 new Vue pages have no automated component-level test - this matches design.md's own Testing Strategy table (Feature/Unit PHP only, Vue pages are manual-smoke items) and is not a regression or gap introduced by this change.

## Skipped Dimensions

None - both spec files, design, and tasks were all present; full verification performed across completeness, correctness, and design coherence.

## Final Verdict: PASS

Ready for sdd-archive.
