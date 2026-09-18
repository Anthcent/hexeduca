# Tasks: Academic Core Structure

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 1400-1800 (7 migrations, 7 entities, 7 models, 7 repo interfaces + 7 Eloquent repos, period triad x4, 2 use cases, DTOs/events, ~20 test files) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (catalog scaffold) → PR 2 (period-scoping triad) → PR 3 (instance entities + use cases) → PR 4 (cross-cutting tests + arch test) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units
| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Catalog entities (Nivel/Grado/Sección) + PeriodoAcademico: migrations, entities, models, repos, tests | PR 1 | Independent; no period-scoping dependency (tenant-only tier) |
| 2 | Period-scoping triad: `PeriodoContext`/`PeriodoScope`/`BelongsToActivePeriodo`/`PeriodoChannel` + `ResolveActivePeriodo` middleware + registration | PR 2 | Depends on PR 1's `PeriodoAcademico` model existing |
| 3 | MomentoAcademico, OfertaAcademica, Matricula: migrations, entities, models (scoping traits), repos, use cases | PR 3 | Depends on PR 2's triad |
| 4 | Full testing strategy (scope-stacking, bypass, auto-stamp, no-op, channel, architecture tests) | PR 4 | Depends on PR 1-3; may be folded into each PR's own phase if reviewer load allows |

## Phase 1: Catalog Migrations (tenant-only tier)
- [x] 1.1 Create migration `niveles_academicos`: id, school_id FK restrictOnDelete, name, timestamps; index school_id.
- [x] 1.2 Create migration `grados`: id, school_id FK restrictOnDelete, nivel_academico_id FK restrictOnDelete, name, order; index (school_id, nivel_academico_id).
- [x] 1.3 Create migration `secciones`: id, school_id FK restrictOnDelete, name, timestamps; index school_id.
- [x] 1.4 Create migration `periodos_academicos`: id, school_id FK restrictOnDelete, name, starts_on, ends_on, is_active (boolean); index (school_id, is_active).

## Phase 2: Catalog Domain + Infrastructure
- [x] 2.1 Create `Modules/Academic/Domain/Entities/{NivelAcademico,Grado,Seccion,PeriodoAcademico}.php` (framework-agnostic, mirror `User` entity).
- [x] 2.2 Create `Modules/Academic/Domain/ValueObjects/DateRange.php` (period start/end validation).
- [x] 2.3 Create `Modules/Academic/Domain/Repositories/{NivelAcademicoRepositoryInterface,GradoRepositoryInterface,SeccionRepositoryInterface,PeriodoAcademicoRepositoryInterface}.php`.
- [x] 2.4 Create `Modules/Academic/Infrastructure/Models/{NivelAcademico,Grado,Seccion,PeriodoAcademico}.php` — `use BelongsToTenant`; explicit `newFactory()`.
- [x] 2.5 Create `Modules/Academic/Infrastructure/Persistence/Eloquent{NivelAcademico,Grado,Seccion,PeriodoAcademico}Repository.php`.
- [x] 2.6 Modify `Modules/Academic/Infrastructure/Providers/AcademicServiceProvider.php`: bind the 4 catalog repo interfaces in `register()`.

## Phase 3: Catalog Tests
- [x] 3.1 `tests/Feature/Academic/CatalogTenantScopeTest.php` — two schools each with Nivel/Grado/Sección; query returns only current school's rows (spec: catalog entity has no period FK; tenant-only tier).
- [x] 3.2 `tests/Unit/Academic/CatalogRelationshipsTest.php` — `NivelAcademico::hasMany(Grado::class)` relationship holds (design: Unit "Relationships").
- [x] 3.3 `tests/Feature/Academic/CatalogReuseAcrossCyclesTest.php` — same catalog Grado/Sección reused across two `PeriodoAcademico` records with no duplication (spec: "Catalog reused across cycles").

## Phase 4: Period-Scoping Triad
- [x] 4.1 Create `Modules/Academic/Infrastructure/Period/PeriodoContext.php` — `set/current/hasPeriodo/forget` (clone `App\Tenancy\TenantContext`).
- [x] 4.2 Create `Modules/Academic/Infrastructure/Period/Scopes/PeriodoScope.php` — no-ops when no period bound (clone `TenantScope`).
- [x] 4.3 Create `Modules/Academic/Infrastructure/Period/Concerns/BelongsToActivePeriodo.php` — registers `PeriodoScope` keyed by `PeriodoScope::class`, `creating` hook stamps `periodo_academico_id`, `scopeWithoutActivePeriodoScope()` calls `withoutGlobalScope(PeriodoScope::class)` only.
- [x] 4.4 Create `Modules/Academic/Infrastructure/Period/Broadcasting/PeriodoChannel.php` — `name()`/`pattern()`/`authorize()` against persisted `periodo_academico_id` + `school_id`, same null/`ctype_digit` guards as `TenantChannel`.
- [x] 4.5 Modify `Modules/Academic/Infrastructure/Providers/AcademicServiceProvider.php`: `$this->app->scoped(PeriodoContext::class)`.
- [x] 4.6 Create `Modules/Academic/Infrastructure/Http/Middleware/ResolveActivePeriodo.php` — after tenant resolution, query `periodos_academicos` where `school_id = current school` AND `is_active = true`, bind result into `PeriodoContext::set()`; no-op (leave unbound) if none found.
- [x] 4.7 Modify `bootstrap/app.php`: register `ResolveActivePeriodo::class` in the same middleware group as `ResolveTenant::class`, running after it.

## Phase 5: Period-Scoping Tests
- [x] 5.1 `tests/Feature/Academic/PeriodScopeCompositionTest.php` — two schools × two periods; query on a tenant+period model returns only current school+period rows (spec: "Both scopes apply together").
- [x] 5.2 Same file — independent bypass: `withoutActivePeriodoScope()` still tenant-filters; `withoutTenantScope()` still period-filters (spec: "Opting out of one scope leaves the other active").
- [x] 5.3 Same file — inspect `$query->toBase()->wheres` to assert neither opt-out silently drops the other scope's clause (design: "two independent keyed global scopes").
- [x] 5.4 `tests/Feature/Academic/PeriodScopeNoOpTest.php` — no period bound → `PeriodoScope` adds no `where`, cross-period rows visible but cross-tenant rows still filtered (spec: "No active period yields no implicit cross-period leakage").
- [x] 5.5 `tests/Feature/Academic/ResolveActivePeriodoMiddlewareTest.php` — middleware binds the school's `is_active = true` period into `PeriodoContext`; when none is active, `PeriodoContext::hasPeriodo()` is `false` and no request failure occurs.
- [x] 5.6 `tests/Unit/Academic/PeriodoChannelTest.php` — `authorize()` case table: null school_id, non-numeric segment, match, mismatch (mirror `TenantChannelTest`; spec: "Broadcast channel authorization resolves period from the row").

## Phase 6: Instance Entities Migrations
- [x] 6.1 Create migration `momentos_academicos`: id, periodo_academico_id FK restrictOnDelete (NO school_id), name, order, starts_on, ends_on; index periodo_academico_id.
- [x] 6.2 Create migration `ofertas_academicas`: id, school_id FK restrictOnDelete, periodo_academico_id FK restrictOnDelete, grado_id FK restrictOnDelete, seccion_id FK restrictOnDelete, teacher_id nullable FK restrictOnDelete (→users), capacity; unique(periodo_academico_id, grado_id, seccion_id); index (school_id, periodo_academico_id).
- [x] 6.3 Create migration `matriculas`: id, school_id FK restrictOnDelete, periodo_academico_id FK restrictOnDelete, oferta_academica_id FK cascadeOnDelete, student_id FK restrictOnDelete (→users), status, enrolled_at; unique(oferta_academica_id, student_id); index (school_id, periodo_academico_id).

## Phase 7: Instance Entities Domain + Infrastructure
- [x] 7.1 Create `Modules/Academic/Domain/Entities/{MomentoAcademico,OfertaAcademica,Matricula}.php`.
- [x] 7.2 Create `Modules/Academic/Domain/ValueObjects/Capacity.php` — guards oferta capacity (non-negative, enrollment count vs limit).
- [x] 7.3 Create `Modules/Academic/Domain/Repositories/{MomentoAcademicoRepositoryInterface,OfertaAcademicaRepositoryInterface,MatriculaRepositoryInterface}.php`.
- [x] 7.4 Create `Modules/Academic/Domain/Events/{OfertaAcademicaCreated,EstudianteMatriculado}.php`.
- [x] 7.5 Create `Modules/Academic/Infrastructure/Models/MomentoAcademico.php` — `use BelongsToActivePeriodo` ONLY (no `BelongsToTenant`; no `school_id` column).
- [x] 7.6 Create `Modules/Academic/Infrastructure/Models/{OfertaAcademica,Matricula}.php` — `use BelongsToTenant, BelongsToActivePeriodo` (both traits, tenant+period tier).
- [x] 7.7 Create `Modules/Academic/Infrastructure/Persistence/Eloquent{MomentoAcademico,OfertaAcademica,Matricula}Repository.php`.
- [x] 7.8 Modify `Modules/Academic/Infrastructure/Providers/AcademicServiceProvider.php`: bind the 3 remaining repo interfaces in `register()`.

## Phase 8: Use Cases
- [x] 8.1 Create `Modules/Academic/Application/DTOs/{CreateOfertaAcademicaData,MatricularEstudianteData}.php` — `final readonly` input DTOs (mirror `UserData`).
- [x] 8.2 Create `Modules/Academic/Application/UseCases/CreateOfertaAcademica.php` — validates unique(periodo, grado, seccion), persists via `OfertaAcademicaRepositoryInterface`, dispatches `OfertaAcademicaCreated`.
- [x] 8.3 Create `Modules/Academic/Application/UseCases/MatricularEstudiante.php` — validates capacity via `Capacity` VO, validates unique(oferta, student), persists via `MatriculaRepositoryInterface`, dispatches `EstudianteMatriculado`.

## Phase 9: Instance Entity Tests
- [x] 9.1 `tests/Feature/Academic/OfertaAcademicaScopingTest.php` — auto-stamp: creating an `OfertaAcademica` with `TenantContext`+`PeriodoContext` bound stamps both `school_id` and `periodo_academico_id` (spec: "Query auto-filters to active period"). Also covers scope-stacking (two schools x two periods) and independent bypass on the REAL `OfertaAcademica`/`Matricula` models, plus no-op safety.
- [x] 9.2 `tests/Feature/Academic/MomentoAcademicoCrossTenantViaPeriodTest.php` — momentos of another school's period are invisible under the active period; momento has no `school_id` column, tenant isolation is transitive via `periodo_academico_id → periodos_academicos.school_id` (spec: "MomentoAcademico subdivides a period globally"; design 3-tier matrix caveat).
- [x] 9.3 `tests/Feature/Academic/MomentoAcademicoSharedAcrossOfertasTest.php` — 3 momentos in a period are the same records referenced by every `OfertaAcademica` in that period (spec: "Moments are shared across all offerings in a period").
- [x] 9.4 `tests/Feature/Academic/CreateOfertaAcademicaTest.php` — use case rejects a duplicate (periodo, grado, seccion) combination (spec: "Offering created for a cycle"); same catalog pair across two periods yields two distinct offerings (spec: "Same catalog pair reused across periods as distinct offerings").
- [x] 9.5 `tests/Feature/Academic/MatricularEstudianteTest.php` — use case rejects enrollment past `capacity`; rejects a duplicate (oferta, student) enrollment; succeeds and stamps `school_id`+`periodo_academico_id`; a withdrawn matricula does not count toward capacity (spec: "Enrollment targets an offering"; fixes `countByOfertaAcademica()` to filter `status = 'active'`).
- [x] 9.6 `tests/Unit/Academic/ProgressionDerivationTest.php` — a student's `matriculas` ordered by `periodo_academico_id` reconstructs progression across 3 cycles with no promotion table consulted (spec: "Progression reconstructed from history"). Also covers Nivel hasMany Grado, OfertaAcademica binds Grado+Seccion to Periodo, and Matricula belongs to OfertaAcademica only (design "Relationships" unit).
- [x] 9.7 `tests/Architecture/Academic/EnrollmentTargetsOfertaOnlyTest.php` — `matriculas` schema has no `grado_id`/`seccion_id` column; `Matricula` model has no direct relation to `Grado`/`Seccion` (spec: "Matricula MUST NOT reference a catalog Año/Grado or Sección directly").
- [x] 9.8 `tests/Architecture/Academic/NoPromotionTableTest.php` — no migration/table name matches `*promotion*`/`*progression*` (spec: "No promotion table exists").

## Phase 10: Documentation
- [x] 10.1 Create `Modules/Academic/README.md`: documents the catalog/instance split, the 3-tier scoping matrix, the `PeriodoContext`/`PeriodoScope` bypass rule (`withoutActivePeriodoScope()` only, never bare `withoutGlobalScopes()`), the queue/broadcast gotcha, key enforced invariants, and explicit Out of Scope for future contributors.
