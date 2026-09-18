# Exploration: academic-core-structure

## Current State

Laravel 12.61.1 / PHP ^8.3, nwidart/laravel-modules ^12.0, Pest ^3.8. Only `Modules/Users` has real Domain/Application/Infrastructure code (confirmed, unchanged from prior sessions) — full hexagonal layering: `Domain/Entities`, `Domain/ValueObjects`, `Domain/Repositories`, `Domain/Events`, `Application/UseCases`, `Application/DTOs`, `Infrastructure/Models`, `Infrastructure/Persistence`.

`Modules/Academic` is a scaffold one notch ahead of the other 5 empty modules (has wired providers/routes/controllers already) but its `Domain/`, `Application/`, `Infrastructure/Models`, `Infrastructure/Persistence`, `Infrastructure/Database/Migrations` folders are all still empty `.gitkeep`.

No academic tables/migrations exist anywhere (grepped all migrations for grado/seccion/periodo/nivel/academic_year — zero hits) — no conflicts to reconcile.

Multi-tenancy lives at app level, `App\Tenancy\*`, not inside `Modules/`, by explicit prior architecture decision (tenancy is cross-cutting infra consumed by `bootstrap/app.php`, not a business aggregate). Pattern:
- `TenantContext` — scoped singleton, request-lifetime, null-safe
- `TenantScope` — Eloquent global scope, no-ops when no tenant bound
- `BelongsToTenant` trait — adds scope + auto-stamps `school_id` on create + `scopeWithoutTenantScope()` bypass
- `TenantChannel` — proof that queue/broadcast contexts have NO access to the request-scoped `TenantContext`, so those code paths must read the persisted `school_id` column directly instead

## Domain Model (agreed with user, to be formalized in sdd-propose)

- `NivelAcademico` (catalog, e.g. "Bachillerato") — fixed reference data.
- `Año/Grado` (catalog, e.g. 1°..5°) belongs to a NivelAcademico — fixed reference data, reused every cycle.
- `Sección` (catalog, e.g. A, B, C) belongs to an Año/Grado — fixed reference data, reused every cycle.
- `PeriodoAcademico` (e.g. "2025-2026") — top-level temporal "world"/scope. Everything downstream defaults to being scoped to the active PeriodoAcademico; cross-period access (progression, historical/certified records) is explicit and lives in its own future module, never baked into the core.
- `MomentoAcademico` — subdivision of a PeriodoAcademico for grading cuts, normally 3, global for the whole system (not per Año/Sección). Config- or date-driven.
- `OfertaAcademica` — the actual per-cycle INSTANCE of an Año+Sección within a specific PeriodoAcademico (carries capacity, assigned teacher, etc). This is what gets enrolled into, not the catalog Año/Sección directly.
- `Matricula` — Estudiante ↔ OfertaAcademica (enrollment for that period).
- Student progression across periods (e.g. 3°A in 2025-2026 → 4°B in 2026-2027) is derived by querying a student's Matriculas ordered by PeriodoAcademico — no separate "progression" table at this layer.
- Downstream modules (Notas/grades, resúmenes finales, certificadas) are explicitly OUT OF SCOPE for this change — future independent modules will consume this core.

## Affected Areas

- `Modules/Academic/Domain/*`, `Application/*`, `Infrastructure/Models`, `Infrastructure/Persistence`, `Infrastructure/Database/Migrations` — currently empty; natural landing zone for all 7 new entities.
- `app/Tenancy/Scopes/TenantScope.php`, `app/Tenancy/Concerns/BelongsToTenant.php`, `app/Tenancy/TenantContext.php` — the exact pattern to replicate one level down for period-scoping (a `PeriodoContext` + `PeriodoScope` + trait, composing with, not replacing, `TenantScope`).
- `app/Tenancy/Broadcasting/TenantChannel.php` — hard evidence that any period-scoped broadcast/queued-job logic must key off a persisted `periodo_academico_id` column, never a request-scoped context object.
- `app/Providers/AppServiceProvider.php:19` — where `TenantContext` is bound `scoped`; a new `PeriodoContext` would need the same registration.
- `database/migrations/` — currently zero academic tables; new entities' migrations will land under `Modules/Academic/Infrastructure/Database/Migrations`.

## Approaches Considered

**1. House the new core inside `Modules/Academic`** (existing pre-scaffolded module), mirroring the Users module's hexagonal layering.
- Pros: scaffold already exists and is wired (providers/routes/controllers registered); matches nwidart modular-monolith convention; these are real business aggregates (enrollment rules, period transitions), so hexagonal per-module layering is appropriate; future modules (Grades) declare a normal module dependency on Academic.
- Cons: none found — no discovery/registration risk (composer merge-plugin already includes `Modules/*/composer.json`).
- Effort: Medium

**2. App-level shared kernel** (`app/Academic/...`), analogous to `App\Tenancy`.
- Pros: matches the "everything hangs off this" framing structurally.
- Cons: the `App\Tenancy` precedent was justified specifically because tenancy is cross-cutting infra with zero business logic (School has no domain rules) — these 7 entities have real business behavior (enrollment, capacity, period rollover), so the rationale for app-level placement does not transfer; would fight the existing modular-monolith convention instead of reusing the module already scaffolded for exactly this purpose.
- Effort: Medium-High

## Recommendation

Approach 1 — build inside `Modules/Academic`, following the Users module's Domain/Application/Infrastructure layering, and replicate the `TenantContext`/`TenantScope`/`BelongsToTenant` pattern one level down as `PeriodoContext`/`PeriodoScope`/`BelongsToActivePeriodo`, composed with (not replacing) the existing tenant scope. This should be an explicit confirmed decision in `sdd-propose`, not assumed silently, since it commits every future module's dependency direction.

## Risks

1. Composing two independent global scopes (tenant + period) on the same models (`OfertaAcademica`, `Matricula`) has no precedent in this codebase yet — needs explicit test coverage for scope-stacking and independent bypass.
2. The queue/broadcast gotcha proven by `TenantChannel` will recur identically for period-scoping if not designed in from the start — any queued job or broadcast touching these models must read `periodo_academico_id` from the row, never from a request-scoped `PeriodoContext`.
3. `MomentoAcademico` being "global for the whole system" introduces a third scoping tier — before `sdd-design`, a per-entity scoping matrix (tenant-only / tenant+period / fully global) should be nailed down explicitly for all 7 entities, or the scope design will be inconsistent.
4. Module-placement (`Modules/Academic` vs app-level) is architecturally significant for future module coupling — must be a stated decision, not an assumption.

## Ready for Proposal

Yes — no schema/module conflicts found, tenancy pattern is well-understood and directly reusable. Two things to nail down explicitly during `sdd-propose`: (1) confirm `Modules/Academic` placement, (2) confirm the per-entity scoping matrix before design work starts.
