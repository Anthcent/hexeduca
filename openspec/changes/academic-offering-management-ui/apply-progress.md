# Apply Progress: Academic Offering Management UI

**Batch 3 of 3 — Phases 7-8 COMPLETE — ALL BATCHES DONE**

## Workload / PR Boundary
- Mode: chained PR slice (Work Unit 3 of 3, per tasks.md forecast)
- Current work unit: Unit 3 — Demo seeder + registration + verification (Phases 7-8)
- Boundary: starts from batch 2's green state (100/100 tests, Vite build green); ends with the demo seeder implemented, registered, verified via a fresh `migrate:fresh --seed`, a genuine end-to-end Pest smoke test added, and the full suite re-run green (101/101).
- Chain strategy: still `pending` at the tasks-artifact level — orchestrator must resolve `stacked-to-main` vs `feature-branch-chain` before any PR is opened. This batch's diff is self-contained (2 files modified + 1 new seeder + 1 new test) and independently revertable regardless of the chosen strategy.

## Completed Tasks

### Phase 1: Access Control Foundation (Batch 1)
- [x] 1.1 `bootstrap/app.php` — added `$middleware->alias([...])` registering `role`, `permission`, `role_or_permission` to the real spatie middleware classes.
- [x] 1.2 `Modules/Academic/routes/web.php` — removed `Route::resource('academic', ...)`; added the 4 named routes (`academic.ofertas.create`, `academic.ofertas.store`, `academic.matriculas.create`, `academic.matriculas.store`) under `->middleware(['auth', 'role:staff/admin'])`.
- [x] 1.3 Deleted `Modules/Academic/Infrastructure/Http/Controllers/AcademicController.php` (unrouted nwidart stub). Ran `composer dump-autoload` after deletion.
- [x] 1.4 Verified `AcademicServiceProvider::register()` binds all repository interfaces the two use cases depend on. No code change needed.

### Phase 2: FormRequests (Batch 1)
- [x] 2.1 Created `StoreOfertaRequest.php` — `grado_id`/`seccion_id` required+exists, `capacity` required+`min:1`, `teacher_id` nullable+exists.
- [x] 2.2 Created `StoreMatriculaRequest.php` — `oferta_academica_id`/`student_id` required+exists.

### Phase 3: Controllers (Batch 1)
- [x] 3.1 `OfertaAcademicaController::create()` — renders `Inertia::render('Academic::OfertaCreate', [grados, secciones, teachers, hasActivePeriodo, periodoName])`.
- [x] 3.2 `OfertaAcademicaController::store()` — server-side `hasPeriodo()` guard, `DomainException` → `ValidationException` on `grado_id`.
- [x] 3.3 `MatriculaController::create()` — renders `Inertia::render('Academic::MatriculaCreate', [ofertas, students])`.
- [x] 3.4 `MatriculaController::store()` — `DomainException` → `ValidationException` on `oferta_academica_id`.

### Phase 4: Backend Tests (Batch 1)
- [x] 4.1-4.5 Access control, happy path, and no-active-period Pest coverage.

### Phase 5: Flash Sharing (Batch 2)
- [x] 5.1 `app/Http/Middleware/HandleInertiaRequests.php::share()` — added `flash.success`/`flash.error` as shared Inertia props (lazy closures reading session), merged additively.

### Phase 6: Vue Pages (Batch 2)
- [x] 6.1 Created `Modules/Academic/Resources/js/Pages/OfertaCreate.vue` — `<script setup>` + `useForm`, `hasActivePeriodo`-gated disabled empty-state, per-field errors, flash banners.
- [x] 6.2 Created `Modules/Academic/Resources/js/Pages/MatriculaCreate.vue` — `<script setup>` + `useForm`, oferta (label = grado+seccion) + student selects, flash banners.

### Phase 7: Demo Seeder (Batch 3 — NEW)
- [x] 7.1 Implemented `Modules/Academic/Infrastructure/Database/Seeders/AcademicDatabaseSeeder.php` exactly per design's Seeder Plan sequence:
  1. `School::firstOrCreate(['subdomain' => 'demo'], ['name' => 'Demo School', 'is_active' => true])`.
  2. `app(TenantContext::class)->set($school)` — bound BEFORE any catalog/period row, per design's explicit ordering requirement.
  3. Active `PeriodoAcademico` (`name` "2026-2027", `is_active` true; `firstOrCreate` on `[school_id, name]`, then an explicit `update(['is_active' => true])` if a stale non-active row was found — keeps idempotent re-runs from ever leaving a seeded period inactive).
  4. `NivelAcademico` "Primaria" + 2 `Grado` ("1ro", "2do") + 2 `Seccion` ("A", "B") — all `firstOrCreate` on natural keys, all explicitly `school_id`-scoped.
  5. `staff@demo.test` (role `staff/admin`), `teacher@demo.test` (role `teacher`), `student1@demo.test` + `student2@demo.test` (role `student`) — all `firstOrCreate` by email, `school_id = $school->id`, roles assigned via `hasRole()`/`assignRole()` idempotency guards (spatie).
  6. **Critical fix discovered during verification** (see Issues Found): added `app(TenantContext::class)->forget()` at the end of `run()`.
- [x] 7.2 `database/seeders/DatabaseSeeder.php` — added `use Modules\Academic\Infrastructure\Database\Seeders\AcademicDatabaseSeeder;` and appended `AcademicDatabaseSeeder::class` to the existing `$this->call([...])` array, after `RoleAndPermissionSeeder::class` and `SuperAdminUserSeeder::class`. Purely additive — the existing `test@example.com` `firstOrCreate` line and the two prior seeders are untouched.

### Phase 8: Verification (Batch 3 — NEW)
- [x] 8.1 Full Pest suite re-run (see Test/Quality Gate Results below): 101/101 passed, 296 assertions, zero regressions.
- [x] 8.2 Manual smoke verification — performed two ways:
  - **Real `migrate:fresh --seed`** against the Sail Postgres database (not just the isolated Pest test DB), confirming the demo seeder genuinely works end-to-end from a clean schema. Verified via `artisan tinker` inspection (see output below) that the demo `School`, active `PeriodoAcademico`, 2 `Grado`, 2 `Seccion`, and the 4 demo users with correct roles all exist with the correct `school_id`.
  - **New permanent Pest test** `tests/Feature/Academic/DemoSeederSmokeTest.php` — uses `RefreshDatabase` + `$this->seed(RoleAndPermissionSeeder::class)` + `$this->seed(AcademicDatabaseSeeder::class)` (the REAL seeder classes, not hand-rolled factories) to prove the actual seeded output is usable through the real HTTP routes: `actingAs` the seeded `staff@demo.test`, GET `academic/ofertas/create` on host `demo.app.com` asserting `hasActivePeriodo: true` and exactly 2 grados / 2 secciones / 1 teacher, POST a real oferta, GET `academic/matriculas/create` asserting that oferta appears (1 result) alongside both seeded students, POST a real enrollment, and `assertDatabaseHas('matriculas', ...)`. This is CI-safe (isolated test DB via `RefreshDatabase`, no dependency on external seeded state) while still exercising the literal `AcademicDatabaseSeeder` class end-to-end.

## Files Changed
| File | Action | What Was Done |
|------|--------|---------------|
| `bootstrap/app.php` | Modified (Batch 1) | Registered `role`/`permission`/`role_or_permission` spatie middleware aliases |
| `Modules/Academic/routes/web.php` | Modified (Batch 1) | Replaced `Route::resource` stub with 4 named routes |
| `Modules/Academic/Infrastructure/Http/Controllers/AcademicController.php` | Deleted (Batch 1) | Unrouted nwidart stub |
| `Modules/Academic/Infrastructure/Http/Controllers/OfertaAcademicaController.php` | Created (Batch 1) | `create()`/`store()` |
| `Modules/Academic/Infrastructure/Http/Controllers/MatriculaController.php` | Created (Batch 1) | `create()`/`store()` |
| `Modules/Academic/Infrastructure/Http/Requests/StoreOfertaRequest.php` | Created (Batch 1) | Validation for oferta creation |
| `Modules/Academic/Infrastructure/Http/Requests/StoreMatriculaRequest.php` | Created (Batch 1) | Validation for matricula creation |
| `tests/Feature/Academic/OfertaAcademicaAccessControlTest.php` | Created (Batch 1) | Guest/wrong-role rejection coverage |
| `tests/Feature/Academic/MatriculaAccessControlTest.php` | Created (Batch 1) | Guest/wrong-role rejection coverage |
| `tests/Feature/Academic/CreateOfertaAcademicaControllerTest.php` | Created (Batch 1) | Happy path + duplicate-oferta rejection |
| `tests/Feature/Academic/MatricularEstudianteControllerTest.php` | Created (Batch 1) | Happy path + capacity-full rejection |
| `tests/Feature/Academic/NoActivePeriodTest.php` | Created (Batch 1), Modified (Batch 2) | No-active-period coverage; Batch 2 removed the `, false` component-existence skip flag |
| `app/Http/Middleware/HandleInertiaRequests.php` | Modified (Batch 2) | Added `flash.success`/`flash.error` to shared Inertia props |
| `Modules/Academic/Resources/js/Pages/OfertaCreate.vue` | Created (Batch 2) | Create-offering form page |
| `Modules/Academic/Resources/js/Pages/MatriculaCreate.vue` | Created (Batch 2) | Enroll-student form page |
| `Modules/Academic/Infrastructure/Providers/AcademicServiceProvider.php` | Modified (Batch 2) | Added `registerInertiaPages()` — registers `Academic` namespace with Inertia's `FileViewFinder` |
| `Modules/Academic/Infrastructure/Database/Seeders/AcademicDatabaseSeeder.php` | Modified (Batch 3) | Implemented the full demo-tenant seed sequence (was an empty nwidart stub) |
| `database/seeders/DatabaseSeeder.php` | Modified (Batch 3) | Added `AcademicDatabaseSeeder::class` to the `call([...])` array |
| `tests/Feature/Academic/DemoSeederSmokeTest.php` | Created (Batch 3) | End-to-end smoke test exercising the real seeder + both routes |
| `openspec/changes/academic-offering-management-ui/tasks.md` | Modified (Batch 1, 2, 3) | All 22/22 tasks now `[x]` |

## Deviations from Design
- Batch 2: registering the `Academic` namespace with Inertia's `FileViewFinder` (infrastructure/testing plumbing, not a behavioral/contract change — flagged in batch 2).
- Batch 3: **one necessary addition not explicit in design.md**: `app(TenantContext::class)->forget()` at the end of `AcademicDatabaseSeeder::run()`. See Issues Found below — this is a bugfix for a real regression discovered during verification, not a stylistic choice. Everything else in the seeder matches design.md's Seeder Plan sequence exactly (School → bind TenantContext → active PeriodoAcademico → NivelAcademico + 2 Grado + 2 Seccion → staff/admin + students + teacher, all explicitly `school_id`-scoped).
- Batch 3: design.md did not explicitly specify idempotency for the demo seeder. Implemented it as idempotent (`firstOrCreate`/`hasRole()` guards) to match this project's existing `DatabaseSeeder::run()` convention (`test@example.com` uses `firstOrCreate`; `SuperAdminUserSeeder` uses `updateOrCreate`) and because the hard constraint required it not to crash on a second `db:seed` run. Verified by running `db:seed` twice in a row against the same database — no crash, no duplicate rows, no duplicate role re-assignment errors.

## Issues Found
- (Batch 1, carried over) Deleting `AcademicController.php` left a stale Composer classmap entry — fixed by `composer dump-autoload`.
- (Batch 1, resolved in Batch 2) `HandleInertiaRequests::share()` did not share `flash.success`/`flash.error` — fixed in Phase 5.
- (Batch 1, resolved in Batch 2) `NoActivePeriodTest`'s `component('Academic::OfertaCreate', false)` workaround — fixed in Phase 6.
- **(Batch 3, found and fixed) `TenantContext` leak across seeders in the same process.** `TenantContext` is bound as a `scoped()` container singleton (`AppServiceProvider::register()`), which behaves like a plain per-process singleton during a single `artisan db:seed` run — it is NOT reset between seeder classes in the same `call([...])` chain. The first implementation of `AcademicDatabaseSeeder::run()` called `TenantContext::set($school)` but never released it. Running a real `migrate:fresh --seed` end to end surfaced the bug immediately: the root `DatabaseSeeder::run()`'s `User::firstOrCreate(['email' => 'test@example.com'], ...)` line executes AFTER `AcademicDatabaseSeeder` in the `call([...])` array, so `BelongsToTenant`'s `creating()` hook auto-stamped `test@example.com` with the leftover demo `school_id` instead of leaving it `null` (its correct landlord/no-tenant state, unchanged since before this feature). **Fix**: added `app(TenantContext::class)->forget()` as the last line of `AcademicDatabaseSeeder::run()`. Re-verified via `artisan tinker` after a second `migrate:fresh --seed`: `test@example.com` and the `SuperAdminUserSeeder`-created super-admin both have `school_id = NULL` again, and the demo users still have `school_id = 1` (the demo school). This directly satisfies the batch's hard constraint that existing seeded data must not be broken.
- **(Environment gap, pre-existing, NOT part of this change's scope)** The local `.env` has no `SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` set (only `.env.example` documents them), so a bare `php artisan migrate:fresh --seed` fails at `SuperAdminUserSeeder` with "Invalid super-admin bootstrap configuration: The email field is required." This is unrelated to the Academic module — `SuperAdminUserSeeder` and its `config/bootstrap.php` predate this change entirely. Worked around for verification purposes only by passing `-e SUPER_ADMIN_EMAIL=... -e SUPER_ADMIN_PASSWORD=...` inline to `docker compose exec` (no permanent `.env` change made). Flagging this as a risk/note for the user: anyone running a fresh clone of this repo will hit the same wall until they populate those two env vars locally.

## Test / Quality Gate Results (real run, Docker/Sail)

### Batch 1+2 results (carried forward, unchanged)
- `docker compose exec -T app npm run build` → PASS — Vite build green, both pages compiled to their own chunks.
- `docker compose exec -T app php artisan test` (before batch 3) → 100 passed, 0 failed, 264 assertions.

### Batch 3 results (new, this batch)
- `docker compose exec -T app ./vendor/bin/pint --test` → **PASS**, 215 files (214 + 1 new test file), zero style violations.
- `docker compose exec -T -e SUPER_ADMIN_EMAIL=... -e SUPER_ADMIN_PASSWORD=... app php artisan migrate:fresh --seed` → **SUCCESS** (run twice in a row to prove idempotency — second run completed with zero errors, zero crashes).
  - Post-seed `artisan tinker` inspection (first run, before the `TenantContext::forget()` fix — this is what CAUGHT the bug):
    ```
    School: Demo School / demo / active=1
    Periodos: 1
    Active periodo: 1
    Grados: 2
    Secciones: 2
    Users school_id=1: 5
    staff@demo.test -> staff/admin
    teacher@demo.test -> teacher
    student1@demo.test -> student
    student2@demo.test -> student
    test@example.com ->   ← BUG: school_id leaked to 1, no roles (landlord user should be school_id=null)
    ```
  - Post-fix re-verification (after adding `forget()`, fresh `migrate:fresh --seed` re-run):
    ```
    test@example.com school_id=NULL
    superadmin school_id=NULL
    total users: 6
    ```
- `docker compose exec -T app php artisan test` (full suite, after the fix + new smoke test) → **101 passed, 0 failed, 296 assertions**, duration ~332s.
  - `Tests\Feature\Academic\DemoSeederSmokeTest` → 1 passed, 32 assertions — genuinely exercises the real `AcademicDatabaseSeeder` class through both live HTTP routes (GET+POST oferta create, GET+POST matricula create).
  - Zero regressions: all 100 pre-existing tests remain green; assertion count grew by exactly 32 (the new smoke test), matching expectations.

## Demo Credentials (for manual login / screen walkthrough)
After running `php artisan migrate:fresh --seed` (with `SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` env vars populated — see Issues Found above), log in at the demo tenant's subdomain (`demo.<APP_BASE_DOMAIN>`, e.g. `demo.app.com` locally per `config('tenancy.base_domain')`) with:

| Role | Email | Password |
|------|-------|----------|
| staff/admin | `staff@demo.test` | `password` |
| teacher | `teacher@demo.test` | `password` |
| student | `student1@demo.test` | `password` |
| student | `student2@demo.test` | `password` |

Seeded catalog: 1 `NivelAcademico` ("Primaria") with 2 `Grado`s ("1ro", "2do") and 2 `Seccion`s ("A", "B"), plus one active `PeriodoAcademico` ("2026-2027"). As `staff@demo.test`, visit `/academic/ofertas/create` to create an offering, then `/academic/matriculas/create` to enroll a student into it.

## Remaining Tasks
None. 22/22 tasks complete across all 3 batches.

## Status
22/22 tasks complete (Phases 1-8, all done). Ready for `sdd-verify`.
