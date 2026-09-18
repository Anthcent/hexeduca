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
- [x] 7.1 Implemented `Modules/Academic/Infrastructure/Database/Seeders/AcademicDatabaseSeeder.php` exactly per design's Seeder Plan sequence with TenantContext::forget() bugfix
- [x] 7.2 `database/seeders/DatabaseSeeder.php` — added `AcademicDatabaseSeeder::class` to the `call([...])` array.

### Phase 8: Verification (Batch 3 — NEW)
- [x] 8.1 Full Pest suite: 101 passed, 296 assertions, zero regressions.
- [x] 8.2 Manual smoke verification with real `migrate:fresh --seed` and demo credentials confirmed.

## Test Results
- 101 tests passed, 0 failed, 296 assertions
- Pint style check: PASS, 215 files, zero violations
- Vite build: PASS, both Vue pages compiled independently
- Demo seeder end-to-end verified

## Demo Credentials
- Email: staff@demo.test / teacher@demo.test / student1@demo.test / student2@demo.test
- Password: password
- Subdomain: demo

## Status
22/22 tasks complete. Ready for `sdd-archive`.
