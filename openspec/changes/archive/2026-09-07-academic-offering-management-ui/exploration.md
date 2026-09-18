# Exploration: academic-offering-management-ui

## Correction to initial framing
The frontend stack is **Inertia + Vue 3** (`@inertiajs/vue3` ^3.6.1, `vue` ^3.5.39, Pinia, Ziggy) — NOT React. `package.json` has zero React dependencies; all existing pages (`resources/js/Pages/Welcome.vue`, `resources/js/Layouts/AppLayout.vue`) are `.vue` SFCs. Spec/design for this change must target Vue 3.

## Current State
- `resources/js/app.js` resolves module-owned pages via a `"ModuleName::PageName"` glob convention from `Modules/*/Resources/js/Pages/**/*.vue`.
- `Modules/Academic/Resources/js/Pages/` and `Modules/Users/Resources/js/Pages/` both contain only `.gitkeep` — no UI anywhere yet, including no login screen (only a JSON POST `/login` in `routes/web.php`).
- `Modules/Academic/routes/web.php` and `AcademicController.php` are still pure nwidart scaffold stubs (`return null`, empty bodies) — confirmed untouched by `academic-core-structure` (that change was backend-only, as intended).
- `CreateOfertaAcademica::handle(CreateOfertaAcademicaData $data): OfertaAcademica` and `MatricularEstudiante::handle(MatricularEstudianteData $data): Matricula` are fully built, framework-agnostic, throw `DomainException` on business rule violations, ready to be called from a new controller. `Matricula.schoolId`/`periodoAcademicoId` are derived from the offering, not user input.
- `spatie/permission` is installed; roles `student/teacher/staff/admin/super-admin` are seeded but carry zero permissions. No route anywhere in the app uses `auth`/`role:`/`permission:` middleware — there is no existing access-control pattern to mirror; this is a fresh decision for this change.
- `bootstrap/app.php` registers `ResolveTenant` then `ResolveActivePeriodo` on the `web` group, so an authenticated browser request will have both `TenantContext` and `PeriodoContext` bound automatically — but `ResolveActivePeriodo` is a silent no-op with no active periodo, which the UI must treat as a valid empty state.
- No School/PeriodoAcademico/catalog (Nivel/Grado/Seccion) rows are seeded anywhere in the repo (`database/seeders/DatabaseSeeder.php` only seeds roles + a super-admin + a test user). The `brown-hoppe-5159` school used in a prior local session exists only in that session's database, not reproducible from a clean clone — this change needs its own demo seeder.

## Affected Areas
- `Modules/Academic/routes/web.php` — replace generic resource route with real named oferta/matricula routes.
- `Modules/Academic/Infrastructure/Http/Controllers/AcademicController.php` (currently all-stub) — needs real controller(s) wiring the two use cases.
- `Modules/Academic/Resources/js/Pages/` — empty; needs new Vue SFCs.
- `resources/js/Layouts/AppLayout.vue` — reusable as-is.
- Access control — no existing pattern to copy, needs an explicit decision.
- A seeder (new, or extending `Modules/Academic/Infrastructure/Database/Seeders/AcademicDatabaseSeeder.php`) for demoable data.
- `Modules/Academic/Infrastructure/Providers/AcademicServiceProvider.php` — repository bindings not yet spot-checked for DI into a new controller; verify before implementation.

## Approaches Considered

1. **Two thin Inertia/Vue pages (Create Oferta + Matricular Estudiante), each wired directly to its existing use case.**
   - Pros: smallest slice, proves the backend end-to-end, avoids scope creep into catalog CRUD.
   - Cons: no listing UI yet, still needs seed data and an access decision.
   - Effort: Low-Medium.

2. **Single combined dashboard page** (list + create + inline matricula).
   - Pros: nicer UX in one view.
   - Cons: larger PR, higher review-budget risk for a first-ever screen.
   - Effort: Medium-High.

3. **Defer access control** (no `auth` middleware on the new routes).
   - Pros: fastest demo.
   - Cons: ships an unprotected mutation endpoint in a school system — not acceptable even for a demo.
   - Effort: Low, risk deferred (rejected).

## Recommendation
Approach 1, plus a minimal demo seeder (one School + active PeriodoAcademico + a few catalog rows), plus an explicit access-control decision (at minimum `auth` middleware on both routes; decide in the proposal whether to also add `role:staff/admin`). Do not build catalog CRUD or a full dashboard in this change.

## Risks
- No demoable seed data exists in the repo; without a seeder the screen renders empty selects with nothing to pick.
- No access-control precedent anywhere in the codebase — this is the first route in the app needing `auth`/role gating; real risk of shipping an unauthenticated mutation endpoint if rushed.
- Seeded roles have zero permissions attached — favor `role:` / `auth` middleware over `permission:` for this change's scope.
- `ResolveActivePeriodo` silently no-ops with no active periodo bound — the UI must handle "no active period" as a valid, explicit empty state, not an error.
- `AcademicServiceProvider` bindings not yet verified for controller-level DI — quick check needed before apply.

## Ready for Proposal
Yes — two things must be confirmed with the user before `sdd-propose`: (1) frontend is Vue 3, not React (informational, already corrected); (2) the access-control approach for the two new mutation routes (`auth` only vs. `auth` + `role:staff/admin`).
