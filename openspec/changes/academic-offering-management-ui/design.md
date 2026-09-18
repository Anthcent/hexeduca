# Design: Academic Offering Management UI

## Technical Approach
Two thin Inertia + Vue 3 pages wired to the already-built, framework-agnostic use cases `CreateOfertaAcademica` and `MatricularEstudiante`. No new domain logic: controllers only validate input, read `TenantContext`/`PeriodoContext`, build the input DTO, invoke the use case, and translate `DomainException` into an Inertia error-bag redirect. Selects are populated with direct Eloquent reads on the module's tenant/period-scoped models — global scopes already enforce isolation, so no manual `school_id`/`periodo` clauses are added. A demo seeder makes the flow reproducible from a clean clone. Placement is module-owned (`Modules/Academic/{routes,Infrastructure/Http/Controllers,Resources/js/Pages}`) resolved by the `app.js` `"Academic::PageName"` glob.

## Architecture Decisions

### Decision: Correct the access-control syntax to the REAL role name
**Choice**: Gate both routes with `->middleware(['auth', 'role:staff/admin'])`. Register spatie aliases in `bootstrap/app.php` `withMiddleware`: `$middleware->alias(['role' => \Spatie\Permission\Middleware\RoleMiddleware::class, 'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class, 'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class]);`
**Alternatives considered**: proposal's `role:staff|admin` (two roles); relying on spatie auto-registering aliases; `permission:` middleware.
**Rationale**: `RoleAndPermissionSeeder` seeds a SINGLE role literally named `staff/admin` (guard `web`) — there is no `staff` or `admin` role, so `role:staff|admin` would authorize nobody. spatie v6 does NOT auto-register the `role` string alias (verified: `PermissionServiceProvider` only registers route macros, no `aliasMiddleware`), and `bootstrap/app.php` has zero `alias()` calls today — so the alias MUST be added or the middleware string is unresolved. `permission:` is unusable because roles carry no permissions yet (seeder is scaffolding-only). The `/` in `staff/admin` is safe: spatie splits the parameter on `|`, so `role:staff/admin` matches the one real role. `super-admin` is a landlord (`school_id = null`) with no tenant/period context, so it is intentionally excluded from these tenant-scoped screens.

### Decision: Constructor/method injection of use cases (no `app()` calls)
**Choice**: Inject each use case as a typed controller-method parameter. Two focused controllers: `OfertaAcademicaController::create()/store(StoreOfertaRequest, CreateOfertaAcademica)` and `MatriculaController::create()/store(StoreMatriculaRequest, MatricularEstudiante)`.
**Alternatives considered**: `app(CreateOfertaAcademica::class)` inline; keeping the resource `AcademicController`.
**Rationale**: `AcademicServiceProvider::register()` already binds all repository interfaces to Eloquent implementations, and the use cases are concrete classes whose constructors type-hint only those interfaces — Laravel's container auto-resolves them with zero extra binding (proven: existing tests call `app(CreateOfertaAcademica::class)`). Method injection is idiomatic and keeps DI explicit. Two controllers keep each action single-purpose; the generic stub `AcademicController` is deleted along with `Route::resource(...)`.

### Decision: Item 6 — tenant-scope students, role-filter teachers
**Choice**: Student select = `User::role('student')->orderBy('name')->get(['id','name'])`; teacher select = `User::role('teacher')->orderBy('name')->get(['id','name'])`. Both inherit tenant isolation automatically.
**Rationale**: `User` uses `BelongsToTenant`, so `TenantScope` already restricts every query to the active tenant — no manual `where('school_id', ...)`. Scoping students to the tenant is mandatory (cross-tenant enrollment is a data-isolation breach). Teachers are filtered by the `teacher` role because assigning a non-teacher user to an offering is a domain nonsense; `teacher_id` stays nullable/optional per the confirmed proposal. Roles are global but tenant isolation still holds via `school_id`, so `role('teacher')` + tenant scope yields exactly "this school's teachers". Landlord/super-admin users (`school_id = null`) fall outside the tenant scope and never appear.

### Decision: "No active period" is a controller-computed prop, not an exception
**Choice**: `OfertaAcademicaController::create()` reads `app(PeriodoContext::class)->hasPeriodo()` and passes `hasActivePeriodo: bool` plus `periodoName` to the page. When false, the Vue form renders disabled with an empty-state message and `store()` aborts early with a redirect-back error.
**Rationale**: `ResolveActivePeriodo` is a silent no-op between cycles (valid state), so `PeriodoContext::current()` may be null. Detecting it in the controller keeps the page dumb and avoids a 500. `store()` re-checks server-side (defense in depth) because a client could POST with a stale form.

## Data Flow
```
GET /academic/ofertas/create
  auth + role:staff/admin  →  OfertaAcademicaController::create
     reads TenantContext (school), PeriodoContext (active periodo)
     Grado::all(), Seccion::all() [tenant-scoped]  +  User::role('teacher')
     → Inertia::render('Academic::OfertaCreate', {grados, secciones, teachers, hasActivePeriodo, periodoName})

POST /academic/ofertas  → store(StoreOfertaRequest, CreateOfertaAcademica)
     schoolId = TenantContext->current()->id ; periodoId = PeriodoContext->current()->id
     new CreateOfertaAcademicaData(schoolId, periodoId, gradoId, seccionId, teacherId, capacity)
     try handle() → redirect back with flash 'success'
     catch DomainException → throw ValidationException::withMessages(['grado_id' => $e->getMessage()])

GET /academic/matriculas/create → OfertaAcademica::with('grado','seccion')->get() [tenant+period], User::role('student')
POST /academic/matriculas → new MatricularEstudianteData(ofertaId, studentId) → handle()
     catch DomainException → ValidationException on 'oferta_academica_id' / 'student_id'
```

## File Changes
| File | Action | Description |
|------|--------|-------------|
| `bootstrap/app.php` | Modify | Register spatie `role`/`permission`/`role_or_permission` aliases in `withMiddleware` |
| `Modules/Academic/routes/web.php` | Modify | Replace `Route::resource` stub with 4 named routes under `auth`+`role:staff/admin` |
| `Modules/Academic/Infrastructure/Http/Controllers/OfertaAcademicaController.php` | Create | `create`/`store`; injects `CreateOfertaAcademica` |
| `Modules/Academic/Infrastructure/Http/Controllers/MatriculaController.php` | Create | `create`/`store`; injects `MatricularEstudiante` |
| `Modules/Academic/Infrastructure/Http/Requests/{StoreOfertaRequest,StoreMatriculaRequest}.php` | Create | FormRequest validation (ids exist, capacity min:1, nullable teacher) |
| `Modules/Academic/Infrastructure/Http/Controllers/AcademicController.php` | Delete | Generic nwidart stub no longer routed |
| `Modules/Academic/Resources/js/Pages/OfertaCreate.vue` | Create | `<script setup>` + `useForm`; disabled empty-state when no active period |
| `Modules/Academic/Resources/js/Pages/MatriculaCreate.vue` | Create | `<script setup>` + `useForm`; oferta + student selects |
| `Modules/Academic/Infrastructure/Database/Seeders/AcademicDatabaseSeeder.php` | Modify | Seed demo School+subdomain, active PeriodoAcademico, Nivel/Grados/Secciones, staff/admin + students + teacher |
| `database/seeders/DatabaseSeeder.php` | Modify | Add `AcademicDatabaseSeeder::class` to the `$this->call([...])` array |

## Route Definitions
```php
Route::middleware(['auth', 'role:staff/admin'])->prefix('academic')->name('academic.')->group(function () {
    Route::get('ofertas/create', [OfertaAcademicaController::class, 'create'])->name('ofertas.create');
    Route::post('ofertas', [OfertaAcademicaController::class, 'store'])->name('ofertas.store');
    Route::get('matriculas/create', [MatriculaController::class, 'create'])->name('matriculas.create');
    Route::post('matriculas', [MatriculaController::class, 'store'])->name('matriculas.store');
});
```

## Interfaces / Contracts
- Vue pages use `useForm` from `@inertiajs/vue3` (exported by the installed ^3.6.1; not yet used anywhere — introduced here). Per-field errors surface via `form.errors.<field>` mapped to the FormRequest keys / `ValidationException` keys. Submit via `form.post(route('academic.ofertas.store'))` (Ziggy `route()` already registered globally in `app.js`).
- Success feedback: `store()` returns `redirect()->route('academic.ofertas.create')->with('success', '...')`; the page reads the flashed message from Inertia shared props (`HandleInertiaRequests` must share `flash.success`/`flash.error` — add if absent).
- `OfertaCreate.vue` props: `grados[]`, `secciones[]`, `teachers[]`, `hasActivePeriodo`, `periodoName`. `MatriculaCreate.vue` props: `ofertas[]` (label = `grado.name + ' ' + seccion.name`), `students[]`.

## Seeder Plan
`AcademicDatabaseSeeder` MUST bind `TenantContext` to the demo school BEFORE creating catalog/period rows, otherwise `BelongsToTenant`/`BelongsToActivePeriodo` auto-stamp from an unbound context and rows land with null `school_id`. Sequence: create `School` (active, subdomain `demo`) → `app(TenantContext::class)->set($school)` → active `PeriodoAcademico` (`is_active` true) → `NivelAcademico` + 2 `Grado` + 2 `Seccion` → a `staff/admin` user, a couple `student` users, one `teacher` user (all `school_id = $school->id`, roles assigned). Registered by adding it to root `DatabaseSeeder::run()`'s `call([...])` array (module seeders are NOT auto-invoked — only migrations auto-load via `AcademicServiceProvider::boot()`; mirroring that for seeders is not supported, so explicit registration is the correct pattern).

## Testing Strategy (Pest Feature, tenant subdomain host + `actingAs`)
| Case | Approach |
|------|----------|
| Guest rejected | GET/POST without auth → assert redirect to `login` / 403 |
| Wrong role rejected | `actingAs` a `student` user → assert 403 |
| Oferta happy path | staff/admin + active periodo bound; POST valid grado/seccion/capacity → assert redirect + `ofertas_academicas` row |
| Duplicate oferta | seed existing (periodo,grado,seccion); POST same → assert `assertSessionHasErrors('grado_id')` (DomainException translated) |
| Matricula happy path | POST valid oferta+student → assert `matriculas` row, `school_id`/`periodo` derived |
| Capacity full | oferta capacity 1, one active matricula; POST second student → `assertSessionHasErrors('oferta_academica_id')` |
| No active period | tenant with no `is_active` periodo; GET create → assert `hasActivePeriodo` prop false; POST → session error |

Tests set the tenant via Host header `demo.app.com` (base_domain `app.com`) so `ResolveTenant`/`ResolveActivePeriodo` populate contexts through the real `web` pipeline, mirroring the existing Academic feature-test setup (`School`/`PeriodoAcademico` factories + context binding). Use `RefreshDatabase` and seed roles.

## Migration / Rollout
No schema changes. Rollback = revert `bootstrap/app.php` alias block and `routes/web.php` to the resource stub, delete the two controllers + requests + Vue pages, restore/keep `AcademicController`, and remove `AcademicDatabaseSeeder` from `DatabaseSeeder`. Seed data is additive and removed by a fresh migrate. Low risk — the verified core backend is untouched.

## Open Questions
- [ ] Confirm `HandleInertiaRequests::share()` currently exposes `flash` + `auth.user`; if not, add flash sharing for success/error feedback (small, in-scope).
- [ ] Login screen: there is only a POST `/login` JSON endpoint and no GET login page, so `auth` middleware redirect target `login` (named route exists) renders nothing. Acceptable for this change (tests use `actingAs`), but note it as a UX follow-up.
