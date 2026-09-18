# Archive Report: Academic Core Structure

**Date**: 2026-09-06  
**Change**: academic-core-structure  
**Mode**: hybrid (OpenSpec filesystem + Engram persistence)  
**Status**: COMPLETE

## Change Summary

The academic-core-structure change builds the foundational academic domain spine for educativo: 7 catalog/temporal/instance entities (NivelAcademico, Año/Grado, Sección, PeriodoAcademico, MomentoAcademico, OfertaAcademica, Matricula) inside `Modules/Academic`, plus a period-scoping layer (PeriodoContext/PeriodoScope/BelongsToActivePeriodo) that composes with the existing tenant scope. This enables every future academic module (grades, subjects, schedules, certificates) to attach cleanly via explicit relationships. Catalog data is reused across cycles; students enroll only into per-cycle offerings; progression is derived from enrollment history without a promotion table.

## Verification Status

**Verification Verdict**: PASS (0 CRITICAL, 0 WARNING, 0 SUGGESTION)

- 88 tests passed, 231 assertions, 0 failures
- All 49 implementation tasks complete and verified against source
- All spec scenarios covered by passing runtime tests
- Full spec compliance matrix: all requirements satisfied
- No regressions in existing functionality
- Full audit trail: proposal → spec → design → tasks → apply (4 batches) → verify → archive

## Specs Synced to Main

### New Capability Specs Created
| Domain | Spec | Action | Details |
|--------|------|--------|---------|
| academic-structure | `openspec/specs/academic-structure/spec.md` | Created | Full spec for new capability: 7 entities, catalog/instance split, enrollment model, progression derivation. 6 requirements, 14 scenarios. |
| period-scoping | `openspec/specs/period-scoping/spec.md` | Created | Full spec for new scoping layer: active-period auto-filtering, scope composition, explicit opt-out, queue/broadcast persistence, per-entity classification matrix. 5 requirements, 16 scenarios. |

## Archive Contents

Archive location: `openspec/changes/archive/2026-09-06-academic-core-structure/`

All artifacts present and verified:

- ✅ `proposal.md` — Intent, scope, approach, risks, rollback, dependencies, success criteria, proposal question round
- ✅ `design.md` — Technical approach, architecture decisions (period-scoping triad placement, keyed-scope independence, 3-tier matrix, queue/broadcast persistence), file changes, migrations, testing strategy
- ✅ `tasks.md` — 49 tasks across 10 phases, all marked complete; review workload forecast
- ✅ `apply-progress.md` — Full 4-batch implementation log with task completions, file changes, deviations, test results
- ✅ `verify-report.md` — Full verification: 88 tests passed, all tasks verified, spec compliance matrix, design coherence, fixes documented
- ✅ `exploration.md` — Current state, recommendation, risks, open questions (pre-proposal exploration)
- ✅ `specs/academic-structure/spec.md` — Full spec for academic entities
- ✅ `specs/period-scoping/spec.md` — Full spec for period-scoping layer
- ✅ `archive-report.md` — This file

## Task Completion Verification

**49/49 tasks complete**, verified against source code across 4 implementation batches:

### Batch 1 (Phases 1-3): Catalog Foundation
- Migrations: `niveles_academicos`, `grados`, `secciones`, `periodos_academicos` (4 tables, tenant-only tier)
- Domain layer: 4 entities, `DateRange` VO, 4 repository interfaces
- Infrastructure: 4 Eloquent models with `BelongsToTenant`, 4 repositories, provider bindings
- Tests: tenant-scoping, relationships, catalog reuse (7 tests, 18 assertions)

### Batch 2 (Phases 4-5): Period-Scoping Triad
- `PeriodoContext` scoped singleton, `PeriodoScope` global scope, `BelongsToActivePeriodo` trait
- `PeriodoChannel` broadcast helper (persisted-column resolution, not context-scoped)
- `ResolveActivePeriodo` middleware (manual `is_active` flag resolution)
- Provider registration, bootstrap middleware ordering
- Tests: composition, independent bypass, no-op safety, middleware, channel authorization (19 tests, 32 assertions)

### Batch 3 (Phases 6-8): Instance Entities
- Migrations: `momentos_academicos`, `ofertas_academicas`, `matriculas` (3 tables, 3-tier scoping matrix applied)
- Domain layer: 3 entities, `Capacity` VO, 3 repository interfaces, 2 domain events
- Infrastructure: 3 Eloquent models with correct trait combinations per scoping tier, 3 repositories, 3 factories
- Application: `CreateOfertaAcademica` and `MatricularEstudiante` use cases with DTOs
- Provider bindings for all 7 repository interfaces
- **Mandatory fix**: `EloquentMatriculaRepository::countByOfertaAcademica()` now filters `status = 'active'` (withdrawn matriculas free capacity)

### Batch 4 (Phases 9-10): Testing & Documentation
- Instance entity tests: 20 new tests covering scoping, capacity, enrollment, progression derivation, architecture invariants (46 new assertions)
- Architecture tests: `EnrollmentTargetsOfertaOnlyTest` (Matricula has no grado/seccion FK), `NoPromotionTableTest` (no promotion table exists)
- `Modules/Academic/README.md`: catalog/instance split, 3-tier matrix, period-scoping triad, bypass rules, queue/broadcast gotcha, out-of-scope clarifications

No unchecked implementation tasks remain. All tasks are production-ready. All 7 entities layered correctly across Domain/Application/Infrastructure per the hexagonal pattern.

## Key Architecture Decisions Honored

- **Period-scoping triad lives in `Modules/Academic/Infrastructure/Period/`, not app-level**: `PeriodoContext` references the `PeriodoAcademico` business aggregate the module owns, avoiding backwards app-core→module dependency. Future Grades/Schedule modules use the trait naturally. Tradeoff accepted.
- **Two independent keyed global scopes (Tenant + Periodo), never `withoutGlobalScopes()`**: `BelongsToTenant` + `BelongsToActivePeriodo` register as `TenantScope::class` and `PeriodoScope::class` respectively. Independent opt-outs: `scopeWithoutTenantScope()`, `scopeWithoutActivePeriodoScope()`. Hard rule (tested): never call bare `withoutGlobalScopes()` — it strips both.
- **3-tier per-entity scoping matrix**: NivelAcademico/Grado/Sección/PeriodoAcademico (tenant-only); OfertaAcademica/Matricula (tenant+period); MomentoAcademico (global-within-period — no school_id, tenant isolation inherited via period relationship). Documented explicitly in design and README.
- **Queue/broadcast keys off persisted columns, never context singletons**: `PeriodoChannel` mirrors `TenantChannel` — reads row's `periodo_academico_id`, never request-scoped `PeriodoContext`. Jobs query with `withoutActivePeriodoScope()->where('periodo_academico_id', $model->periodo_academico_id)`. Proven lesson from tenancy implementation.
- **Manual `is_active` flag-based period resolution, not date-driven**: `ResolveActivePeriodo` middleware queries `where('is_active', true)` per school. No automatic date-based period activation (future feature, out of scope).
- **Capacity excludes non-active matriculas**: `EloquentMatriculaRepository::countByOfertaAcademica()` filters `status = 'active'`. Withdrawn/inactive enrollments free capacity seats for new students.

## Spec Compliance Summary

### academic-structure Spec (6 requirements, 14 scenarios)
- ✅ Catalog entities carry no cycle reference (NivelAcademico, Año/Grado, Sección migrations confirmed schema-clean)
- ✅ PeriodoAcademico is the temporal top-level entity (confirmed as root aggregate, all instances anchor to it)
- ✅ MomentoAcademico subdivides a period globally (confirmed no school_id, shared across offerings in period)
- ✅ OfertaAcademica instantiates a catalog Año+Sección within a period (confirmed unique constraint, capacity/teacher bindings)
- ✅ Matricula enrolls students into OfertaAcademica only (no grado_id/seccion_id FK, no catalog relation on model)
- ✅ Progression is derived from Matricula history, never stored (no promotion table exists, progression reconstructed via join+order)

### period-scoping Spec (5 requirements, 16 scenarios)
- ✅ Queries default-scope to the active PeriodoAcademico (PeriodoScope applies when context bound)
- ✅ Period scope composes with tenant scope, not replaces it (both traits on OfertaAcademica/Matricula, both scopes apply)
- ✅ Explicit opt-out required for cross-period queries (scopeWithoutActivePeriodoScope() implemented, core never uses it)
- ✅ Queue/broadcast execution resolves period from persisted column (PeriodoChannel, jobs query by row's periodo_academico_id)
- ✅ Per-entity scoping classification is explicit and documented (3-tier matrix in design.md and README.md)

## Regression Check

Full test suite: **88 passed, 231 assertions, 0 failures**

All pre-existing tests (SecurityBaselineTest middleware ordering, FoundationTest, ExampleTest, all Unit tests) pass without regression. The implementation is additive (7 new tables, 49 new entities/models/repos/etc, 20 new tests) and does not break existing functionality.

## Files Modified / Created in Implementation

### Core Academic Entities (Domain Layer)
- 7 framework-agnostic entities: `NivelAcademico`, `Grado`, `Seccion`, `PeriodoAcademico`, `MomentoAcademico`, `OfertaAcademica`, `Matricula`
- 2 value objects: `DateRange`, `Capacity`
- 7 repository interfaces + 7 Eloquent repository implementations
- 2 domain events: `OfertaAcademicaCreated`, `EstudianteMatriculado`

### Period-Scoping Triad
- `PeriodoContext` — scoped singleton (mirrors `TenantContext`)
- `PeriodoScope` — global scope (mirrors `TenantScope`)
- `BelongsToActivePeriodo` — trait for period-scoped models
- `PeriodoChannel` — broadcast authorization helper
- `ResolveActivePeriodo` — middleware for active period resolution

### Infrastructure & Database
- 7 Eloquent models: `NivelAcademico`, `Grado`, `Seccion`, `PeriodoAcademico`, `MomentoAcademico`, `OfertaAcademica`, `Matricula`
- 7 migrations: `niveles_academicos`, `grados`, `secciones`, `periodos_academicos`, `momentos_academicos`, `ofertas_academicas`, `matriculas`
- 7 factories: one per entity (in `database/factories/`)
- `AcademicServiceProvider` — modified to bind all 7 repository interfaces and scoped `PeriodoContext`
- `bootstrap/app.php` — modified to register `ResolveActivePeriodo` middleware

### Application Layer
- 2 DTOs: `CreateOfertaAcademicaData`, `MatricularEstudianteData`
- 2 use cases: `CreateOfertaAcademica`, `MatricularEstudiante`

### Tests (20 new test files, 46 new assertions)
- Scoping/composition: `PeriodScopeCompositionTest`, `PeriodScopeNoOpTest`, `OfertaAcademicaScopingTest`
- Period-scoping specific: `ResolveActivePeriodoMiddlewareTest`, `PeriodoChannelTest`
- Catalog: `CatalogTenantScopeTest`, `CatalogRelationshipsTest`, `CatalogReuseAcrossCyclesTest`
- Instance entities: `MomentoAcademicoCrossTenantViaPeriodTest`, `MomentoAcademicoSharedAcrossOfertasTest`, `CreateOfertaAcademicaTest`, `MatricularEstudianteTest`
- Relationships: `ProgressionDerivationTest`
- Architecture: `EnrollmentTargetsOfertaOnlyTest`, `NoPromotionTableTest`

### Documentation
- `Modules/Academic/README.md` — module documentation covering architecture decisions, scoping matrix, bypass rules, queue/broadcast gotcha, invariants, out-of-scope items

## Risks & Mitigations

No CRITICAL or WARNING issues in final verification. The implementation is robust:

1. **Scope composition (Tenant + Periodo) tested thoroughly**: Explicit scope-stacking tests with real models prove both scopes apply independently and no bypass silent-disables the other.
2. **Queue/broadcast context isolation proven by TenantChannel precedent**: `PeriodoChannel` replicates the pattern exactly — persisted column read, not context-scoped, making it safe for jobs/queues.
3. **3-tier scoping matrix documented and enforced**: Per-entity classification explicit in design and README. MomentoAcademico's global-within-period is justified independently, not copied.
4. **Manual is_active flag-based period resolution**: Simple, explicit, no date-based auto-switching magic. Future modules can add auto-switching if operationally needed, but core delivers the simpler design.

## Known Limitations (Accepted)

**Automatic date-based period activation (out of scope)**: The `ResolveActivePeriodo` middleware reads the manual `is_active` flag. Automatic date-range-driven period switching is a future feature (rejected from this change's scope per proposal). No production impact yet (educativo has no real academic data).

## Out of Scope (Explicit Non-Requirements)

The following are explicitly deferred to future, separate changes:

- Materias/subjects, Notas/grades
- Resúmenes finales (grade summaries), certificados (certificates)
- Promotion/progression workflows (progression is derived, not stored, in this change)
- Cross-period access (transcripts, year-over-year views) — future module consuming the explicit `scopeWithoutActivePeriodoScope()` opt-out
- Automatic date-based period activation
- Any UI/frontend
- Controllers, routes, HTTP endpoints for academic entities (domain/repos/use cases only)

## Rollback Path

Rollback is simple and additive:

1. Drop 7 academic tables (niveles_academicos, grados, secciones, periodos_academicos, momentos_academicos, ofertas_academicas, matriculas)
2. Delete `Modules/Academic/Domain`, `Application`, `Infrastructure/Period`, `Infrastructure/Models`, `Infrastructure/Persistence` directories (revert to `.gitkeep`)
3. Delete period-scoping triad classes from `Infrastructure/Period/`
4. Revert `AcademicServiceProvider` bindings
5. Revert `bootstrap/app.php` middleware registration
6. Delete 7 factories
7. Delete 20 test files

No data migrations, no database side effects. Engram artifacts remain for audit trail.

## SDD Cycle Status

| Phase | Artifact | Status | Observation IDs |
|-------|----------|--------|-----------------|
| Proposal | proposal.md | Complete | #1564 |
| Exploration | exploration.md | Complete | (included in archive) |
| Spec | specs/{domain}/spec.md | Complete | #1565 (academic-structure + period-scoping specs merged from Engram, synced to main openspec/specs/) |
| Design | design.md | Complete | #1566 |
| Tasks | tasks.md | Complete | #1567 (49/49 tasks checked) |
| Apply | apply-progress.md | Complete | (4-batch implementation, all phases delivered) |
| Verify | verify-report.md | Complete | #1569 (PASS, 88 tests, 0 failures) |
| Archive | archive-report.md | Complete | This file + Engram persistence |

**The academic-core-structure SDD cycle is closed.** All artifacts are archived. The change is production-ready and can be merged.

## Next Steps

None — the change is complete, verified, and archived. The academic domain foundation is ready for:
- Grades/Notas module (consumes OfertaAcademica/Matricula for grade entry)
- Subjects/Materias module (consumes Grado/Sección/OfertaAcademica for curriculum binding)
- Schedules/Horarios module (consumes OfertaAcademica for timetable assignment)
- Certificates/Certificados module (consumes Matricula history for degree audit)
- Future cross-period modules (transcripts, year-over-year reports) using explicit `scopeWithoutActivePeriodoScope()` opt-out

---

**Archived by**: sdd-archive executor  
**Timestamp**: 2026-09-06  
**Mode**: hybrid (filesystem archive + Engram persistence)  
**Engram Observation ID**: `sdd/academic-core-structure/archive-report`  
**Engram Topic Key**: `sdd/academic-core-structure/archive-report`
