# Academic Module

`Modules/Academic` is the shared academic core that every future academic
capability (grades/subjects, schedules, certificates, promotion workflows)
hangs off. It defines the 7 catalog/temporal/instance entities and the
enrollment model, following the same hexagonal layering as `Modules/Users`
(`Domain/{Entities,ValueObjects,Repositories,Events}`,
`Application/{UseCases,DTOs}`,
`Infrastructure/{Models,Persistence,Database/Migrations,Period,Providers}`).

## Catalog vs Instance split

Two families of entities exist, and the distinction drives every scoping
and modeling decision in this module:

- **Catalog** (`NivelAcademico`, `Grado`/Año, `Sección`): fixed, tenant-owned
  data with **no** foreign key to any academic cycle. A catalog record is
  created once and reused across every `PeriodoAcademico` a school runs.
  There is deliberately no cycle FK on these tables — this is enforced by
  `tests/Feature/Academic/CatalogTenantScopeTest.php`.
- **Instance** (`PeriodoAcademico`, `MomentoAcademico`, `OfertaAcademica`,
  `Matricula`): per-cycle data. `OfertaAcademica` is the instantiation of one
  catalog `Grado` + `Sección` pair within exactly one `PeriodoAcademico`.
  `Matricula` is the enrollment of a student into one `OfertaAcademica` —
  it never references a catalog row directly (see "Enrollment targets an
  offering, never a catalog row" below).

Progression (a student's grade-to-grade advancement year over year) is
**derived only**, never stored: query a student's `Matricula` records,
join to their `OfertaAcademica` → `PeriodoAcademico`, and order by the
period's `starts_on`. No promotion/progression table exists in this module
— see `tests/Architecture/Academic/NoPromotionTableTest.php`.

## The 3-tier scoping matrix

| Entity | `school_id` (TenantScope) | `periodo_academico_id` (PeriodoScope) | Tier | Why |
|--------|:---:|:---:|------|-----|
| NivelAcademico | yes | no | tenant-only | Fixed catalog reused every cycle; no cycle FK |
| Grado (Año) | yes | no | tenant-only | Catalog; belongs to a Nivel, reused per cycle |
| Sección | yes | no | tenant-only | Catalog label (A/B/…); reused per cycle |
| PeriodoAcademico | yes | no | tenant-only | Defines the periods — cannot period-scope against itself |
| MomentoAcademico | no | yes | **global-within-period** | Grading-cut subdivision shared across the whole cycle; tenant isolation is inherited **transitively** via `periodo_academico_id → periodos_academicos.school_id` |
| OfertaAcademica | yes | yes | tenant+period | Per-cycle instance of Grado+Sección |
| Matricula | yes | yes | tenant+period | Enrollment into one cycle's Oferta |

`MomentoAcademico` is the reason the matrix has 3 tiers instead of 2: it
carries **no `school_id` column at all**. Its tenant safety comes purely
from `PeriodoScope` plus the fact that its `periodo_academico_id` FK points
to a row that is itself tenant-scoped. This means momentos must always be
reached through their period relationship — an unbound-period console
context could otherwise read momentos across schools. This is intentional
and is exercised by
`tests/Feature/Academic/MomentoAcademicoCrossTenantViaPeriodTest.php`.

Models that are both tenant- and period-scoped (`OfertaAcademica`,
`Matricula`) `use` **both** `App\Tenancy\Concerns\BelongsToTenant` and
`Modules\Academic\Infrastructure\Period\Concerns\BelongsToActivePeriodo`.
Each trait registers its own global scope under its own class key
(`TenantScope::class` / `PeriodoScope::class`), so they compose via SQL
`AND` and neither one can be silently dropped by opting out of the other.

## The period-scoping triad

`Modules/Academic/Infrastructure/Period/` is a structural clone of
`App\Tenancy` (`TenantContext`/`TenantScope`/`BelongsToTenant`), scoped to
the currently active `PeriodoAcademico` instead of the current `School`:

- `PeriodoContext` — scoped singleton (`set/current/hasPeriodo/forget`),
  bound via `$this->app->scoped(PeriodoContext::class)` in
  `AcademicServiceProvider::register()`.
- `Scopes/PeriodoScope` — no-ops when no period is bound; otherwise filters
  `periodo_academico_id` to the active period.
- `Concerns/BelongsToActivePeriodo` — registers `PeriodoScope`, auto-stamps
  `periodo_academico_id` on `creating`, and exposes
  `scopeWithoutActivePeriodoScope()`.
- `Broadcasting/PeriodoChannel` — composes `school.{id}.periodo.{id}.{resource}.{id}`
  channel names and authorizes against **persisted** columns.
- `Http/Middleware/ResolveActivePeriodo` — runs immediately after
  `ResolveTenant` in both the `api` and `web` middleware groups; resolves
  the current school's `is_active = true` `PeriodoAcademico` and binds it
  into `PeriodoContext`. No-ops (leaves the context unbound) if no tenant is
  resolved yet, or if the school has no active period.

### The bypass rule — read this before writing a cross-period query

There are two independent, class-keyed opt-outs:

- `scopeWithoutTenantScope()` → drops **only** `TenantScope`.
- `scopeWithoutActivePeriodoScope()` → drops **only** `PeriodoScope`.

**Never call bare `withoutGlobalScopes()`** (no arguments) as a "show
everything" shortcut. That strips *every* registered global scope,
including `TenantScope` — silently reopening the cross-tenant leak the
tenancy layer exists to close. Cross-period reads (e.g. a future
transcripts/year-over-year module) MUST name `PeriodoScope::class`
explicitly, either via `scopeWithoutActivePeriodoScope()` or
`withoutGlobalScope(PeriodoScope::class)`.

### The queue/broadcast gotcha

`PeriodoContext` is a scoped container singleton tied to the HTTP request
lifecycle. Queue workers and Reverb broadcast authorization have **no**
HTTP kernel, so `PeriodoContext` is always unbound there — identical to the
`TenantContext` gotcha `TenantChannel` already had to solve. Any code that
runs outside a request (queued jobs, `PeriodoChannel::authorize()`) MUST
resolve the period from the **persisted `periodo_academico_id` column on
the row being processed**, never from `PeriodoContext`. This is why
`MatricularEstudiante` derives `school_id`/`periodo_academico_id` from the
target `OfertaAcademica` entity itself rather than from ambient context —
a `Matricula` must always inherit the exact tenant+period of the offering
it targets, regardless of what context happens to be bound when the code
runs (e.g. a queued enrollment job).

## Key invariants (enforced by tests)

- **Enrollment targets an offering, never a catalog row.** `Matricula` has
  no `grado_id`/`seccion_id` column and no relation method to `Grado` or
  `Sección` — see `tests/Architecture/Academic/EnrollmentTargetsOfertaOnlyTest.php`.
- **No promotion/progression table exists.** Progression is always derived
  by ordering a student's `Matricula` rows by their offering's period — see
  `tests/Architecture/Academic/NoPromotionTableTest.php` and
  `tests/Unit/Academic/ProgressionDerivationTest.php`.
- **Capacity only counts active enrollments.** `MatriculaRepositoryInterface::countByOfertaAcademica()`
  filters `status = 'active'` — a withdrawn/inactive `Matricula` frees its
  seat for another student.

## Out of scope

This module intentionally does **not** cover: Materias/subjects,
Notas/grades, resúmenes finales, certificados, or any promotion/progression
*workflow* (only derived progression *reads* are supported). No UI or
frontend is included. Future contributors extending this module into any
of those areas should treat `Modules/Academic`'s catalog/instance split and
3-tier scoping matrix above as the foundation to build on top of, not
something to duplicate or reinterpret per-feature.
