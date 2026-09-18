# Verify Report: Academic Offering Management UI

**Verdict: PASS**

## Independently Re-Run Evidence (not copied from apply-progress.md)

| Check | Command | Result |
|---|---|---|
| Full Pest suite | `docker compose exec -T app php artisan test` | **101 passed, 0 failed, 296 assertions**, ~327s. Matches claimed numbers exactly. |
| Pint style | `docker compose exec -T app ./vendor/bin/pint --test` | **PASS, 215 files**, zero violations. Matches claim. |
| Vite build | `docker compose exec -T app npm run build` | **PASS** — `OfertaCreate-CAYzXWH6.js` (4.78kB) and `MatriculaCreate-BLbojYix.js` (2.98kB) each compiled to their own chunk. |
| `migrate:fresh --seed` (bare, no env workaround) | `docker compose exec -T app php artisan migrate:fresh --seed` | **Fails exactly as apply-progress described**: `RuntimeException: Invalid super-admin bootstrap configuration: The email field is required.` at `SuperAdminUserSeeder.php:45`. Confirmed pre-existing, unrelated to this change (predates Academic module). |
| `migrate:fresh --seed` (with inline `SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` workaround) | `docker compose exec -T -e SUPER_ADMIN_EMAIL=... -e SUPER_ADMIN_PASSWORD=... app php artisan migrate:fresh --seed` | **SUCCESS** — all seeders ran including `AcademicDatabaseSeeder`. |
| Post-seed data check | `artisan tinker` | `test@example.com` → `school_id=NULL`, super-admin → `school_id=NULL`; `staff@demo.test`/`teacher@demo.test`/`student1@demo.test`/`student2@demo.test` → `school_id=1` with correct roles (`staff/admin`, `teacher`, `student`, `student`). School "Demo School"/`demo`/active=1, 1 active Periodo, 2 Grados, 2 Secciones. **Batch-3 `TenantContext::forget()` bugfix confirmed NOT regressed.** |

## Source-Level Verification

1. **Role name / middleware / route syntax** — `database/seeders/RoleAndPermissionSeeder.php:30` seeds the literal single role `'staff/admin'` (confirmed no separate `staff`/`admin` roles exist). `bootstrap/app.php:44-48` registers `role`/`permission`/`role_or_permission` aliases to the real Spatie middleware classes. `Modules/Academic/routes/web.php:18` uses `role:staff/admin` (correct — matches the real seeded role; `role:staff|admin` from the original proposal would have been wrong and authorized nobody).

2. **Controllers use method injection, no `app()` calls, no reimplemented business logic** — `OfertaAcademicaController::store()` and `MatriculaController::store()` both type-hint the use case (`CreateOfertaAcademica`, `MatricularEstudiante`) as a method parameter. Both `catch (DomainException $e)` and rethrow `ValidationException::withMessages(...)`. Neither controller contains any capacity/uniqueness check — they only build DTOs and delegate to `handle()`.

3. **`hasActivePeriodo` is a valid rendered state, not an exception path** — `OfertaAcademicaController::create()` reads `$periodoContext->hasPeriodo()` unconditionally and always returns `Inertia::render(...)`; no exception is thrown in `create()`. Confirmed via `NoActivePeriodTest`: `assertOk()` + `component('Academic::OfertaCreate')` + `where('hasActivePeriodo', false)` — a real runtime-passing test, not just source inspection. `store()` re-checks server-side and throws `ValidationException` (not an unhandled exception) when submitted without an active period — confirmed by the second `NoActivePeriodTest` case asserting `assertSessionHasErrors('grado_id')`.

4. **Vue pages exist and use `useForm`** — `Modules/Academic/Resources/js/Pages/{OfertaCreate,MatriculaCreate}.vue` both exist and both match `useForm` via grep. `npm run build` genuinely succeeds and both pages compile to independent chunks.

5. **`FileViewFinder` namespace registration deviation is real and necessary** — `AcademicServiceProvider::registerInertiaPages()` hooks `afterResolving('inertia.view-finder', ...)` to call `$finder->addNamespace('Academic', ...)`. Traced into `vendor/inertiajs/inertia-laravel/src/Testing/AssertableInertia.php:108`: `component()` calls `app('inertia.view-finder')->find($value)` — this is a real Laravel `FileViewFinder::find()` call that throws `InvalidArgumentException` if the namespaced view file cannot be located. Without the namespace registration, `assertInertia()->component('Academic::OfertaCreate')` in every Academic feature test would fail. This is a legitimate infrastructure deviation from design.md, not scope creep.

6. **Seeder `TenantContext` bind/forget bugfix** — `AcademicDatabaseSeeder::run()` calls `app(TenantContext::class)->set($school)` at line 55, before any catalog/period/user row is created, and `app(TenantContext::class)->forget()` at line 132, the last statement. `database/seeders/DatabaseSeeder.php` calls seeders in order `RoleAndPermissionSeeder → SuperAdminUserSeeder → AcademicDatabaseSeeder → test@example.com firstOrCreate` — `test@example.com` runs after `AcademicDatabaseSeeder`, so the `forget()` is load-bearing to prevent tenant leakage, and re-verified live via tinker above.

7. **Spec-to-test mapping spot-check (not vacuous)** — read `CreateOfertaAcademicaControllerTest.php`, `MatricularEstudianteControllerTest.php`, `NoActivePeriodTest.php`, `OfertaAcademicaAccessControlTest.php` directly. All contain real `assertDatabaseHas`, `assertSessionHasErrors`, `assertForbidden`, `assertRedirect(route('login'))`, and Inertia prop assertions — not just HTTP-200 checks. Confirmed coverage for: guest rejected, wrong-role rejected (403), oferta happy path (DB row asserted), duplicate oferta rejection (`grado_id` error), matricula happy path (DB row with derived `school_id`/`periodo_academico_id` asserted), capacity-full rejection (`oferta_academica_id` error), no-active-period valid render state.

## Tasks / Apply-Progress Cross-Check

- 22/22 tasks marked complete in `tasks.md`; all correspond to real files/behavior verified above — no phantom completions found.
- `apply-progress.md`'s claimed test/lint numbers (101 passed / 296 assertions, Pint 215 files) match this independent re-run exactly.
- Environment gap (`SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` missing from local `.env`) reproduced exactly as documented; not part of this change's scope (predates it).

## Issues

None CRITICAL. No WARNING or SUGGESTION items found beyond what apply-progress already flagged (the pre-existing env gap, which is out of scope for this change and already documented for the user).

## Skipped Dimensions

None — proposal, spec, design, and tasks were all present; full verification performed across completeness, correctness, and design coherence.

## Final Verdict: PASS

Ready for `sdd-archive`.
