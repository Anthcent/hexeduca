# Proposal: Academic Offering Management UI

## Intent
The `academic-core-structure` backend (7 entities, 2 use cases, 88 tests) is archived and verified, but has ZERO UI, routes, or controllers — it cannot be exercised or demoed from a browser. This change ships the FIRST visible screens of the school-management system: two thin Inertia + Vue 3 forms that wire directly to the already-built `CreateOfertaAcademica` and `MatricularEstudiante` use cases, proving the core works end-to-end.

**Why now**: a verified backend with no reachable surface delivers no observable value; the fastest way to validate the domain model is a minimal real screen.

**Success**: from a clean clone + seed, a staff/admin user logs in, creates an Oferta Académica (catalog Grado + Sección bound to the active Periodo, with capacity and optional teacher), and enrolls a student into it — all through the existing use cases, with capacity/uniqueness rules enforced by the domain, not the controller.

## Scope
### In Scope
- **Create Oferta screen**: Vue form → controller → `CreateOfertaAcademica::handle`. Selects catalog Grado + Sección, capacity, optional teacher; Periodo derived from active `PeriodoContext`.
- **Matricular Estudiante screen**: Vue form → controller → `MatricularEstudiante::handle`. Selects an existing Oferta + a student; `schoolId`/`periodoAcademicoId` derived from the Oferta, never asked.
- **Minimal read queries** only to populate select inputs (catalog rows, existing ofertas, students) — not full index views.
- **Access control**: both routes gated by `auth` + `role:staff|admin` middleware.
- **Demo seeder**: one School with an `is_active` PeriodoAcademico + a few NivelAcademico/Grado/Sección rows so screens work from a fresh clone.
- **Explicit empty state**: "no active period" handled as a valid state (form disabled/message), not a crash — `ResolveActivePeriodo` is a silent no-op.
- Module-owned placement in `Modules/Academic` (routes, controllers, Vue pages).

### Out of Scope
- Catalog CRUD screens (Nivel/Grado/Sección/PeriodoAcademico management).
- Combined dashboard; index/listing views beyond populating selects.
- Notas/Materias functionality.
- Granular spatie permissions (role-check only; permission-level granularity deferred).
- A GET login screen (none exists; auth flow is a separate concern).

## Capabilities
### New Capabilities
- `academic-offering-ui`: browser-facing create-offering and enroll-student flows wired to the academic-core use cases, with role-gated routes, demo seed data, and explicit no-active-period empty state.
### Modified Capabilities
- None. (`academic-structure` / `period-scoping` requirements unchanged; this only consumes them.)

## Approach
Two thin Inertia pages (Approach 1 from exploration), one controller action per use case, no new domain abstractions. Controllers inject the existing use cases via container bindings (`AcademicServiceProvider`) and translate `DomainException` (capacity full / duplicate / not found) into validation-friendly responses; they do NOT re-implement business rules. Vue 3 `<script setup>` SFCs live in `Modules/Academic/Resources/js/Pages/`, resolved by `app.js`'s existing `"ModuleName::PageName"` glob and picked up by Tailwind's module content glob. Real named routes replace the generic `Route::resource('academic', ...)` stub. Tenant + period context arrive automatically via existing `web`-group middleware. A demo seeder makes the flow reproducible.

## Affected Areas
| Area | Impact | Description |
|------|--------|-------------|
| `Modules/Academic/routes/web.php` | Modified | Replace stub resource with named oferta/matricula routes under `auth` + `role:staff|admin` |
| `Modules/Academic/Infrastructure/Http/Controllers` | Modified/New | Real controller action(s) injecting the two use cases, returning `Inertia::render` |
| `Modules/Academic/Resources/js/Pages` | New | `OfertaCreate.vue`, `MatriculaCreate.vue` (Vue 3 SFCs) |
| `Modules/Academic/.../Database/Seeders/AcademicDatabaseSeeder.php` | New | Demo School + active Periodo + catalog rows |
| `resources/js/Layouts/AppLayout.vue` | Reused | Shell layout, no change |
| `AcademicServiceProvider` | Verify | Confirm repository bindings resolve use cases in a controller |

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Original brief said React; stack is Vue 3 | High if uncorrected | Lock Vue 3 + Inertia in spec/design; app.js resolver only loads `.vue` |
| Fresh env has no demo data → empty selects | High | Ship demo seeder in this change |
| First-ever protected route; no pattern to copy | Med | Design decides `auth` + `role:staff\|admin` explicitly; test middleware |
| Roles carry no permissions yet (spatie) | Med | Use `role:` check, not `permission:`; defer granularity |
| `ResolveActivePeriodo` silent no-op | Med | Explicit no-active-period empty state in controller + Vue |
| Use-case DI not verified in controller | Low-Med | Spot-check `AcademicServiceProvider` bindings before apply |

## Rollback Plan
Revert `Modules/Academic/routes/web.php` to the stub resource, delete the new controller action(s) and Vue pages, remove the demo seeder registration. No schema changes; demo seed data is additive and removable via fresh migrate. Low-risk — no core backend touched.

## Dependencies
- Archived, verified `academic-core-structure` (use cases, repositories, entities, migrations).
- Existing stack: Laravel 12, nwidart modules, Inertia + Vue 3, `spatie/permission` (roles seeded), Tailwind + `@tailwindcss/forms`. No new packages.
- Existing `web`-group middleware (`ResolveTenant`, `ResolveActivePeriodo`) and `app.js` module page resolver.

## Success Criteria
- [ ] Authenticated `staff|admin` user can create an Oferta via the UI; capacity/duplicate rules enforced by the use case.
- [ ] Same user can enroll a student into an existing Oferta; school/period derived from the Oferta.
- [ ] Non-authenticated or non-`staff|admin` requests are rejected by middleware.
- [ ] Flow is reproducible from a clean clone via the demo seeder.
- [ ] "No active period" renders a clear empty state, not an error.
- [ ] Controllers contain no business logic beyond DTO mapping + exception translation.
- [ ] Catalog CRUD, dashboard, Notas/Materias, and granular permissions confirmed out of scope.

## Proposal question round (for user review before spec/design)
Decisions below were pre-aligned in direct conversation and are formalized, not reopened. Flagging the assumptions that most change design if wrong:
1. **Two separate thin pages** (OfertaCreate + MatriculaCreate), not a combined dashboard. Confirm.
2. **Access control = `auth` + `role:staff|admin`** now; permission-level granularity deferred. Confirm.
3. **Demo seeder is in-scope** (one School + active Periodo + a few catalog rows), not full catalog CRUD. Confirm.
4. **Stack is Vue 3 + Inertia** (original brief's "React" was a factual error). Confirmed via exploration.
5. **Teacher selection is optional** on Oferta; student + oferta are the only inputs for enrollment (school/period derived). Confirm.
6. Open question: should the student select list be scoped to students of the active tenant only (assumed yes via TenantScope), and should teachers be filtered by a `teacher` role? Design to decide.
