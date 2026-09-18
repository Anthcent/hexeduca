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

3. **`hasActivePeriodo` is a valid rendered state, not an exception path** — `OfertaAcademicaController::create()` reads `$periodoContext->hasPeriodo()` unconditionally and always returns `Inertia::render(...)`; no exception is thrown in `create()`. Confirmed via `NoActivePeriodTest`: `assertOk()` + `component('Academic::OfertaCreate')` + `where('hasActivePeriodo', false)` — a real runtime-passing test, not just source inspection.

4. **Vue pages exist and use `useForm`** — `Modules/Academic/Resources/js/Pages/{OfertaCreate,MatriculaCreate}.vue` both exist and both use `useForm` via `@inertiajs/vue3`. Both pages compile to independent chunks.

5. **Seeder `TenantContext` bind/forget bugfix** — `AcademicDatabaseSeeder::run()` calls `app(TenantContext::class)->set($school)` before catalog/period/user rows and `app(TenantContext::class)->forget()` at the last statement. This prevents tenant context leakage to subsequent seeders in the same database seeding process.

6. **Spec-to-test mapping** — All requirements have real passing tests with database assertions and form validation coverage.

## Tasks / Apply-Progress Cross-Check

- 22/22 tasks marked complete in `tasks.md`; all correspond to real files/behavior verified above.
- Test/lint numbers match independent re-run exactly.

## Issues

None CRITICAL. Pre-existing environment gap documented but out of scope.

## Final Verdict: PASS

Change is complete, verified, and ready for archiving.
