# Proposal: Academic Core Structure

## Intent
educativo has multi-tenancy but zero academic domain: no way to express a school's levels, grades, sections, cycles, or enrollments. Every future module (grades, subjects, schedules, certificates) needs a stable spine to hang off. This change builds that spine — 7 catalog/temporal/instance entities plus a period-scoping layer — inside `Modules/Academic`.

**Why now**: no academic tables exist yet, so establishing the catalog-vs-instance model and period-scoping convention while schema is empty is cheap. Retrofitting after grades/subjects ship real migrations would be costly and coupling-prone.

**Success**: a student enrolls into a per-cycle `OfertaAcademica` (never the catalog); catalog data (`NivelAcademico`, `Año/Grado`, `Sección`) is defined once and reused every cycle; core queries default-scope to the active `PeriodoAcademico`; year-over-year progression is derivable from `Matricula` history without a promotion table; future modules attach only through explicit, minimal relationships.

## Scope
### In Scope
- **7 entities** in `Modules/Academic`: `NivelAcademico`, `Año/Grado`, `Sección` (fixed catalog), `PeriodoAcademico` (temporal top scope), `MomentoAcademico` (grading-cut subdivision, global within a period), `OfertaAcademica` (per-cycle Año+Sección instance: capacity, assigned teacher), `Matricula` (student ↔ OfertaAcademica enrollment).
- Their attributes, relationships, migrations, and hexagonal layering mirroring `Modules/Users`.
- **Period-scoping layer**: `PeriodoContext` / `PeriodoScope` / `BelongsToActivePeriodo`, composed on top of (not replacing) the existing `TenantScope`.
- **Per-entity 3-tier scoping matrix** (tenant-only vs tenant+period vs global) to be nailed in design.

### Out of Scope (future separate changes)
- Materias/subjects, Notas/grades, resúmenes finales, certificados.
- Promotion/progression workflows (progression is derived, not stored).
- Cross-period access paths (transcripts, year-over-year) — explicit future module, never core default.
- Any UI/frontend.

## Capabilities
### New Capabilities
- `academic-structure`: the 7 catalog/temporal/instance entities, relationships, and enrollment model.
- `period-scoping`: active-period context + global scope stacked on tenant scope, with explicit opt-out.
### Modified Capabilities
- None.

## Approach
Build under `Modules/Academic` (existing empty scaffold), mirroring `Modules/Users` hexagonal layout (Domain/Application/Infrastructure). Chosen over an app-level shared kernel like `App\Tenancy`: these are business aggregates with real behavior (enrollment, capacity, period transitions), not cross-cutting infra. Future modules declare a Composer dependency on Academic.

Catalog/instance split: catalog entities carry no cycle FK; `OfertaAcademica` binds a catalog `Año`+`Sección` to one `PeriodoAcademico`; `Matricula` points only at `OfertaAcademica`. Progression = querying a student's Matriculas ordered by period.

Period-scoping reuses the proven tenancy triad: a scoped `PeriodoContext` singleton, a `PeriodoScope` that no-ops when no active period is bound (cross-period opts out explicitly, like `withoutTenantScope()`), and a `BelongsToActivePeriodo` trait auto-stamping `periodo_academico_id`. `MomentoAcademico`'s "global within period" makes scoping 3-tier, resolved per-entity in design.

## Affected Areas
| Area | Impact | Description |
|------|--------|-------------|
| `Modules/Academic/Domain` | New | Entities, value objects, repository interfaces, events for 7 aggregates |
| `Modules/Academic/Application` | New | Enrollment/period use cases + DTOs |
| `Modules/Academic/Infrastructure/Models` | New | Eloquent models using `BelongsToTenant` + `BelongsToActivePeriodo` |
| `Modules/Academic/Infrastructure/Database/Migrations` | New | 7 tables with `school_id` and (where applicable) `periodo_academico_id` |
| `app/` or `Modules/Academic` period-scope triad | New | `PeriodoContext`, `PeriodoScope`, `BelongsToActivePeriodo` (final home is a design call) |

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Two stacked global scopes (Tenant + Periodo) misorder or bypass | Med-High | Explicit scope-stacking tests, independent opt-outs, design-phase matrix |
| Queue/broadcast contexts can't see `PeriodoContext` (proven by `TenantChannel`) | High if ignored | Persisted `periodo_academico_id` column read directly in jobs/broadcasts, never the request-scoped singleton |
| `MomentoAcademico` "global within period" scoped inconsistently vs other entities | Med | Per-entity 3-tier scoping matrix decided before migrations |
| Future module coupling creeps into core | Med | Only explicit minimal relationships; grades/subjects stay out of scope |
| Establishing pattern with no prior period-scope art in-repo | Med | Elevated design review; judgment-day after design per repo policy |

## Rollback Plan
No git and no academic data yet. Rollback = drop the 7 academic tables, remove `Modules/Academic/Domain|Application|Infrastructure` files back to `.gitkeep` scaffold, and remove the period-scope triad. Engram artifacts remain. Low-risk precisely because no real data exists.

## Dependencies
- Existing stack only (Laravel 12, nwidart modules, spatie/permission). No new Composer package.
- Depends on the shipped multi-tenancy foundation (`school_id` + `TenantScope`) to compose against.

## Success Criteria
- [ ] 7 entities exist in `Modules/Academic` with hexagonal layering matching `Modules/Users`.
- [ ] Catalog entities carry no cycle FK; `OfertaAcademica` binds Año+Sección to a `PeriodoAcademico`; `Matricula` references only `OfertaAcademica`.
- [ ] `PeriodoScope` default-scopes core queries to the active period; cross-period requires explicit opt-out.
- [ ] Scoping composes with `TenantScope`; per-entity 3-tier matrix documented.
- [ ] `MomentoAcademico` is global within a period, not varying per Año/Sección/Oferta.
- [ ] Progression derivable from Matricula history with no promotion table.
- [ ] Grades/subjects/certificates/promotion/UI confirmed out of scope; core exposes only explicit relationships.

## Proposal question round (for user review before spec/design)
Business decisions below were pre-aligned in direct conversation and are formalized here, not reopened. Flagging only the assumptions that most change the design if wrong:
1. **Module placement** = `Modules/Academic` (business aggregates), not an app-level shared kernel. Confirmed.
2. **`MomentoAcademico` is global within a period** (typically 3, config/date-driven), never per Año/Sección/Oferta. Confirm.
3. **Enrollment targets `OfertaAcademica` only**, never catalog directly. Confirm.
4. **Progression is derived** from Matricula history — no promotion/progression table in this change. Confirm.
5. **Cross-period access is a future module**, never a core default. Confirm.
