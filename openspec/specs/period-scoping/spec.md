# Spec: Period Scoping

## Capability: period-scoping

### Purpose
Define the active-period auto-scoping layer that composes with the existing tenant scope, so period-scoped academic entities default-filter to the active `PeriodoAcademico` the same way `TenantScope` default-filters by `school_id`.

### Requirement: Queries default-scope to the active PeriodoAcademico
The system MUST automatically filter queries against period-scoped entities to the currently-active `PeriodoAcademico`, mirroring how `TenantScope` auto-filters by `school_id`.

#### Scenario: Query auto-filters to active period
- GIVEN an active `PeriodoAcademico` is resolved for the current request
- WHEN a query is run against a period-scoped model (e.g. `OfertaAcademica`)
- THEN only rows belonging to the active `PeriodoAcademico` are returned, with no explicit period filter written by the caller

#### Scenario: No active period yields no implicit cross-period leakage
- GIVEN no active `PeriodoAcademico` is bound for the current context
- WHEN a query is run against a period-scoped model without an explicit opt-out
- THEN the scope MUST NOT silently return rows across multiple periods as if unscoped-by-period were the same as opted-out

### Requirement: Period scope composes with tenant scope, not replaces it
The period scope MUST stack on top of the existing tenant scope so both apply simultaneously on the same models (e.g. `OfertaAcademica`, `Matricula`). Neither scope MUST suppress or bypass the other.

#### Scenario: Both scopes apply together
- GIVEN a request resolved to school A with active period "2025-2026"
- WHEN a query runs against `OfertaAcademica`
- THEN only rows for school A AND period "2025-2026" are returned

#### Scenario: Opting out of one scope leaves the other active
- GIVEN a query that explicitly opts out of period scoping only
- WHEN it executes against `Matricula`
- THEN it still returns only rows for the resolved tenant, across all periods

### Requirement: Explicit opt-out required for cross-period queries
The system MUST provide an explicit opt-out (mirroring `scopeWithoutTenantScope()`) for cross-period queries. This opt-out MUST be used only by future cross-period modules (e.g. transcripts, year-over-year views) and MUST NOT be invoked as core default behavior.

#### Scenario: Explicit opt-out exposes all periods for a tenant
- GIVEN a future cross-period module needs a student's full enrollment history
- WHEN it invokes the explicit period-scope opt-out
- THEN the query returns matching rows across all `PeriodoAcademico` records for the resolved tenant

#### Scenario: Core code paths never invoke the opt-out
- GIVEN the core `Modules/Academic` code paths delivered by this change
- WHEN their queries against period-scoped models are inspected
- THEN none of them invoke the cross-period opt-out as their default behavior

### Requirement: Queue and broadcast execution resolves period from a persisted column
Any queued job or broadcast channel touching period-scoped models MUST resolve the relevant `PeriodoAcademico` from a persisted `periodo_academico_id` column on the row being processed. It MUST NOT rely on a request-scoped context object to resolve the period.

#### Scenario: Queued job resolves period from the row
- GIVEN a queued job processing an `OfertaAcademica` or `Matricula` record
- WHEN the job needs the record's `PeriodoAcademico`
- THEN it reads the persisted `periodo_academico_id` column on that row, not a request-scoped context singleton

#### Scenario: Broadcast channel authorization resolves period from the row
- GIVEN a broadcast channel scoped to a period-scoped model
- WHEN channel authorization runs outside an HTTP request lifecycle
- THEN it resolves the period from the model's persisted `periodo_academico_id`, not from any request-scoped context object

#### Scenario: Request-scoped context absence does not break queue/broadcast execution
- GIVEN queue or broadcast execution where no request-scoped period context is bound (proven precedent: `TenantChannel` cannot see request-scoped tenant context)
- WHEN a period-scoped model is processed
- THEN period resolution still succeeds via the persisted column, with no failure or fallback attributable to a missing request-scoped context

### Requirement: Per-entity scoping classification is explicit and documented
The design phase MUST produce and document an explicit per-entity scoping classification — tenant-only, tenant+period, or fully global — covering all 7 entities defined in the `academic-structure` capability. `MomentoAcademico`'s "global within period" classification MUST NOT be assumed to apply uniformly to the other 6 entities; each MUST be classified independently.

#### Scenario: All 7 entities have a documented classification
- GIVEN the design artifact for this change
- WHEN it is inspected
- THEN `NivelAcademico`, `Año/Grado`, `Sección`, `PeriodoAcademico`, `MomentoAcademico`, `OfertaAcademica`, and `Matricula` each have one of tenant-only / tenant+period / fully global explicitly assigned

#### Scenario: Classification is not copied uniformly from MomentoAcademico
- GIVEN `MomentoAcademico` is classified as global-within-period
- WHEN `OfertaAcademica` and `Matricula` are classified
- THEN their classification is justified independently and is not assumed identical to `MomentoAcademico`'s solely because both exist "within a period"

## Out of Scope (explicit non-requirements)
This capability governs scoping behavior only. It does not define the 7 entities themselves (see `academic-structure`), any UI, or cross-period feature modules (transcripts, year-over-year reporting) — those consume the opt-out but are built in a future, separate change.
