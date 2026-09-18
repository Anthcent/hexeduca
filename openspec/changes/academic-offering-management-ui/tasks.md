# Tasks: Academic Offering Management UI

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 800-1050 (middleware alias, routes, 2 FormRequests, 2 controllers, flash-share edit, 2 Vue SFCs, seeder + registration, 5 test files) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (backend wiring + access-control/happy-path tests) → PR 2 (Vue pages + flash sharing) → PR 3 (demo seeder + verification) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units
| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Middleware alias, routes, FormRequests, controllers, backend Pest tests (Phases 1-4) | PR 1 | Independent; tests use factories, not the seeder |
| 2 | Vue pages + `HandleInertiaRequests` flash check (Phases 5-6) | PR 2 | Depends on PR 1's controllers/props contract |
| 3 | Demo seeder + registration + verification (Phases 7-8) | PR 3 | Depends on PR 1's models/roles; independent of PR 2 |

## Phase 1: Access Control Foundation
- [x] 1.1 Modify `bootstrap/app.php`: add `$middleware->alias([...])` registering spatie `role`/`permission`/`role_or_permission` middleware classes.
- [x] 1.2 Modify `Modules/Academic/routes/web.php`: remove `Route::resource('academic', ...)` stub; add the 4 named routes under `->middleware(['auth','role:staff/admin'])` per design's Route Definitions.
- [x] 1.3 Delete `Modules/Academic/Infrastructure/Http/Controllers/AcademicController.php` (unrouted stub).
- [x] 1.4 Spot-check `AcademicServiceProvider` resolves `CreateOfertaAcademica`/`MatricularEstudiante` via method injection (no code change expected).

## Phase 2: FormRequests
- [x] 2.1 Create `StoreOfertaRequest.php`: `grado_id`/`seccion_id` exist, `capacity` `min:1`, `teacher_id` nullable + exists.
- [x] 2.2 Create `StoreMatriculaRequest.php`: `oferta_academica_id`/`student_id` exist.

## Phase 3: Controllers
- [x] 3.1 Create `OfertaAcademicaController::create()`: reads `TenantContext`, `PeriodoContext::hasPeriodo()`, `Grado::all()`, `Seccion::all()`, `User::role('teacher')->get()`; `Inertia::render('Academic::OfertaCreate', [...])`.
- [x] 3.2 Create `OfertaAcademicaController::store(StoreOfertaRequest, CreateOfertaAcademica)`: server-side `hasPeriodo()` guard, build DTO, `handle()`, catch `DomainException` → `ValidationException::withMessages(['grado_id' => ...])`.
- [x] 3.3 Create `MatriculaController::create()`: `OfertaAcademica::with('grado','seccion')->get()` (tenant+period scoped), `User::role('student')->get()`.
- [x] 3.4 Create `MatriculaController::store(StoreMatriculaRequest, MatricularEstudiante)`: build DTO, `handle()`, catch `DomainException` → `ValidationException` on `oferta_academica_id`/`student_id`.

## Phase 4: Backend Tests
- [x] 4.1 `tests/Feature/Academic/OfertaAcademicaAccessControlTest.php` — guest rejected (redirect/login); `student`-role user rejected (403).
- [x] 4.2 `tests/Feature/Academic/MatriculaAccessControlTest.php` — same guest/wrong-role coverage for matricula routes.
- [x] 4.3 `tests/Feature/Academic/CreateOfertaAcademicaControllerTest.php` — happy path creates row + redirect; duplicate (periodo,grado,seccion) → `assertSessionHasErrors('grado_id')`.
- [x] 4.4 `tests/Feature/Academic/MatricularEstudianteControllerTest.php` — happy path creates matricula with derived `school_id`/`periodo_academico_id`; capacity full → `assertSessionHasErrors('oferta_academica_id')`.
- [x] 4.5 `tests/Feature/Academic/NoActivePeriodTest.php` — tenant with no active periodo: GET asserts `hasActivePeriodo` prop `false`; POST asserts session error, no exception thrown.

## Phase 5: Flash Sharing
- [x] 5.1 Check `app/Http/Middleware/HandleInertiaRequests.php::share()`; add `flash.success`/`flash.error` if not already shared.

## Phase 6: Vue Pages
- [x] 6.1 Create `Modules/Academic/Resources/js/Pages/OfertaCreate.vue`: `<script setup>` + `useForm`, disabled empty-state when `hasActivePeriodo` is false, flash success/error display.
- [x] 6.2 Create `Modules/Academic/Resources/js/Pages/MatriculaCreate.vue`: `<script setup>` + `useForm`, oferta + student selects, flash display.

## Phase 7: Demo Seeder
- [x] 7.1 Create `Modules/Academic/Infrastructure/Database/Seeders/AcademicDatabaseSeeder.php`: create `School` (subdomain `demo`) → bind `TenantContext::set($school)` BEFORE any catalog/period row → active `PeriodoAcademico` → `NivelAcademico` + 2 `Grado` + 2 `Seccion` → `staff/admin` user, students, teacher (all `school_id`-scoped, roles assigned).
- [x] 7.2 Modify `database/seeders/DatabaseSeeder.php`: add `AcademicDatabaseSeeder::class` to the `call([...])` array.

## Phase 8: Verification
- [x] 8.1 Run full Pest suite; confirm new tests plus existing `academic-core-structure` tests are green.
- [x] 8.2 Manual smoke: `php artisan migrate:fresh --seed`; log in as demo `staff/admin`; complete both create-oferta and matricula flows end-to-end.
