# Design: Academic Core Structure

## Technical Approach
Build 7 academic aggregates inside the pre-scaffolded `Modules/Academic`, mirroring the `Modules/Users` hexagonal layering exactly (`Domain/{Entities,ValueObjects,Repositories,Events}`, `Application/{UseCases,DTOs}`, `Infrastructure/{Models,Persistence,Database/Migrations}`, repo interfaces bound in `AcademicServiceProvider::register()`). On top of the shipped tenancy foundation we add a **period-scoping triad** that is a structural clone of `App\Tenancy`'s (`TenantContext`/`TenantScope`/`BelongsToTenant`), but lives INSIDE the module because the active period is a business aggregate the module owns — not app-boot infrastructure. The two global scopes stack as independent keyed scopes on `OfertaAcademica` and `Matricula`. Persisted `periodo_academico_id`/`school_id` columns are the source of truth for every queue/broadcast path, never the request-scoped context objects (the reusable lesson proven by `TenantChannel`).

## Architecture Decisions

### Decision: Period-scoping triad lives in `Modules/Academic/Infrastructure/Period/`, not app-level
**Choice**: `Modules/Academic/Infrastructure/Period/{PeriodoContext, Scopes/PeriodoScope, Concerns/BelongsToActivePeriodo, Broadcasting/PeriodoChannel}`. Scoped singleton bound in `AcademicServiceProvider::register()` via `$this->app->scoped(PeriodoContext::class)`.
**Alternatives considered**: app-level `app/AcademicPeriod/*` adjacent to `App\Tenancy` (the tenancy precedent).
**Rationale**: `App\Tenancy` is at app level ONLY because `School` is infrastructure the app itself owns and `bootstrap/app.php` middleware consumes it before any module boots. Period is different: `PeriodoAcademico` is an Academic business aggregate (proposal decision), so `PeriodoContext` must reference it. Putting the triad in `app/` would force **app-core to depend on a module** — a backwards dependency the tenancy design deliberately avoided. Academic is the declared shared academic core other modules depend on, so `Modules\* → Modules\Academic` for the trait is consistent with "modules depend on shared core, never each other." Period resolution is not bootstrap middleware, so it does not need app-level placement. Tradeoff accepted: future period-scoped models in Grades/Schedule `use Modules\Academic\Infrastructure\Period\Concerns\BelongsToActivePeriodo`, coupling them to Academic — which is intended.

### Decision: Two independent keyed global scopes, never `withoutGlobalScopes()`
**Choice**: `BelongsToTenant` registers `TenantScope` (key `TenantScope::class`); `BelongsToActivePeriodo` registers `PeriodoScope` (key `PeriodoScope::class`). `OfertaAcademica`/`Matricula` `use` BOTH traits. Bypasses are class-named: `scopeWithoutTenantScope()` → `withoutGlobalScope(TenantScope::class)`, `scopeWithoutActivePeriodoScope()` → `withoutGlobalScope(PeriodoScope::class)`.
**Alternatives considered**: a single combined `TenantPeriodScope`; using bare `withoutGlobalScopes()` to cross periods.
**Rationale**: Eloquent stores global scopes in a class-keyed array and runs each `apply()` in trait-boot order, each appending its own `where`. Because SQL `AND` is commutative, boot order is irrelevant to correctness and the two scopes cannot clobber each other — they compose. Distinct keys give **independent opt-outs**: dropping the period scope leaves the tenant `where` intact and vice-versa. A combined scope would force an all-or-nothing bypass, re-opening the exact cross-tenant leak tenancy closed. Hard rule (enforced by test): never call `withoutGlobalScopes()` (no args) as a "show all periods" shortcut — it also strips `TenantScope`. Cross-period reads MUST name `PeriodoScope::class` only.

### Decision: 3-tier per-entity scoping matrix
| Entity | `school_id` (TenantScope) | `periodo_academico_id` (PeriodoScope) | Tier | Why |
|--------|:---:|:---:|------|-----|
| NivelAcademico | yes | no | tenant-only | Fixed catalog reused every cycle; no cycle FK |
| Año/Grado | yes | no | tenant-only | Catalog; belongs to a Nivel, reused per cycle |
| Sección | yes | no | tenant-only | Catalog label (A/B/…); reused per cycle |
| PeriodoAcademico | yes | no | tenant-only | Defines the periods — cannot period-scope against itself |
| MomentoAcademico | no | yes | **global-within-period** | Grading-cut subdivision shared across the whole cycle; tenant isolation inherited transitively via `periodo_academico_id → periodos_academicos.school_id` (confirmed business decision) |
| OfertaAcademica | yes | yes | tenant+period | Per-cycle instance of Año+Sección |
| Matricula | yes | yes | tenant+period | Enrollment into one cycle's Oferta |
**Rationale**: Catalog entities carry no period FK so a single row is reused every cycle (the catalog/instance split). `MomentoAcademico` is the reason the matrix is 3-tier: it is period-scoped but NOT directly tenant-scoped — it has no `school_id`, deriving tenant safety only through its period. Documented caveat: an unbound-period console context could read momentos across schools; momentos MUST always be reached via their period relationship, and cross-period/cross-tenant reads require the explicit `withoutActivePeriodoScope()` opt-out.

### Decision: Queue/broadcast keys off persisted columns, never the context singletons
**Choice**: `PeriodoChannel` helper mirrors `TenantChannel` — composes wire name `school.{schoolId}.periodo.{periodoId}.{resource}.{id}` and authorizes against the persisted `$user->school_id` plus the **row's** `periodo_academico_id`, with the same explicit-null + `ctype_digit` guards. Queued jobs receive the model and query related data under `withoutActivePeriodoScope()->where('periodo_academico_id', $model->periodo_academico_id)`, or explicitly re-bind `PeriodoContext` from the row before scoped queries.
**Rationale**: Reverb and queue workers have no HTTP kernel, so `PeriodoContext` (scoped singleton) is unbound there — identical to the `TenantContext` gotcha `TenantChannel` already solved. Reading the row's own `periodo_academico_id` is context-independent and cannot silently misbehave when work is later queued.

## Data Flow
```
HTTP request ─ ResolveTenant → TenantContext.set(school)
             ─ ResolveActivePeriodo → PeriodoContext.set(periodo)   [school's is_active period]
                    │
   OfertaAcademica::query()
                    │  TenantScope.apply()  → where school_id = ctx.school.id
                    │  PeriodoScope.apply()  → where periodo_academico_id = ctx.periodo.id
                    ▼
        create() → BelongsToTenant stamps school_id, BelongsToActivePeriodo stamps periodo_academico_id
                    ▼
   Queue/Reverb (no context) → read row.periodo_academico_id / user.school_id directly (PeriodoChannel)
```

## File Changes
| File | Action | Description |
|------|--------|-------------|
| `Modules/Academic/Domain/Entities/{NivelAcademico,Grado,Seccion,PeriodoAcademico,MomentoAcademico,OfertaAcademica,Matricula}.php` | Create | Framework-agnostic aggregates (mirror `User` entity) |
| `Modules/Academic/Domain/ValueObjects/{DateRange,Capacity}.php` | Create | Period start/end range; oferta capacity guard |
| `Modules/Academic/Domain/Repositories/*RepositoryInterface.php` | Create | One interface per aggregate (mirror `UserRepositoryInterface`) |
| `Modules/Academic/Domain/Events/{OfertaAcademicaCreated,EstudianteMatriculado}.php` | Create | Domain events emitted by use cases |
| `Modules/Academic/Application/DTOs/*Data.php` | Create | `final readonly` input DTOs (mirror `UserData`) |
| `Modules/Academic/Application/UseCases/{CreateOfertaAcademica,MatricularEstudiante}.php` | Create | Key orchestration use cases (mirror `RegisterUser`) |
| `Modules/Academic/Infrastructure/Models/*.php` | Create | Eloquent models; scoping traits per matrix; explicit `newFactory()` |
| `Modules/Academic/Infrastructure/Persistence/Eloquent*Repository.php` | Create | Repo implementations (mirror `EloquentUserRepository`) |
| `Modules/Academic/Infrastructure/Period/PeriodoContext.php` | Create | Scoped singleton; `set/current/hasPeriodo/forget` (clone `TenantContext`) |
| `Modules/Academic/Infrastructure/Period/Scopes/PeriodoScope.php` | Create | No-ops when no period bound (clone `TenantScope`) |
| `Modules/Academic/Infrastructure/Period/Concerns/BelongsToActivePeriodo.php` | Create | Adds scope + `creating` auto-stamp + `scopeWithoutActivePeriodoScope()` |
| `Modules/Academic/Infrastructure/Period/Broadcasting/PeriodoChannel.php` | Create | Channel name/pattern/authorize against persisted columns (clone `TenantChannel`) |
| `Modules/Academic/Infrastructure/Database/Migrations/*` | Create | 7 tables (see below) |
| `Modules/Academic/Infrastructure/Providers/AcademicServiceProvider.php` | Modify | `scoped(PeriodoContext::class)` + bind 7 repo interfaces |

## Interfaces / Contracts
```php
namespace Modules\Academic\Infrastructure\Period;

final class PeriodoContext // scoped singleton — clone of TenantContext
{
    public function set(PeriodoAcademico $periodo): void;
    public function current(): ?PeriodoAcademico;
    public function hasPeriodo(): bool;   // false in console/queue → PeriodoScope no-ops
    public function forget(): void;
}

trait BelongsToActivePeriodo // clone of BelongsToTenant
{
    public static function bootBelongsToActivePeriodo(): void; // addGlobalScope(new PeriodoScope) + creating hook stamping periodo_academico_id
    public function scopeWithoutActivePeriodoScope(Builder $q): Builder; // withoutGlobalScope(PeriodoScope::class) ONLY
}
```

## Migrations Plan
| Table | Key columns | FKs / indexes |
|-------|-------------|---------------|
| `niveles_academicos` | id, school_id, name, timestamps | FK school_id; index school_id |
| `grados` | id, school_id, nivel_academico_id, name, order | FKs; index (school_id, nivel_academico_id) |
| `secciones` | id, school_id, name, timestamps | FK school_id; index school_id |
| `periodos_academicos` | id, school_id, name, starts_on, ends_on, is_active | FK school_id; index (school_id, is_active) |
| `momentos_academicos` | id, periodo_academico_id, name, order, starts_on, ends_on | FK periodo_academico_id (NO school_id); index periodo_academico_id |
| `ofertas_academicas` | id, school_id, periodo_academico_id, grado_id, seccion_id, teacher_id (nullable→users), capacity | FKs; **unique(periodo_academico_id, grado_id, seccion_id)**; index (school_id, periodo_academico_id) |
| `matriculas` | id, school_id, periodo_academico_id, oferta_academica_id, student_id (→users), status, enrolled_at | FKs; **unique(oferta_academica_id, student_id)**; index (school_id, periodo_academico_id) |

Conventions follow `create_schools_table`: anonymous-class migration, `$table->id()`, `foreignId()->constrained()`, `timestamps()`. Migrations load via `AcademicServiceProvider::boot()` `loadMigrationsFrom(...)` (already wired). Progression is DERIVED (no promotion table): query a student's `matriculas` ordered by `periodo_academico_id`.

## Testing Strategy (Pest, mirror `tests/Feature/SecurityBaselineTest` style)
| Layer | What to Test | Approach |
|-------|-------------|----------|
| Feature | Both scopes active: two schools × two periods; query returns only current school+period rows | Bind `TenantContext`+`PeriodoContext`, seed cross-combinations, assert count/ids |
| Feature | Independent bypass: `withoutActivePeriodoScope()` still tenant-filters; `withoutTenantScope()` still period-filters | Assert each opt-out drops only its own `where` |
| Feature | Neither silently disables the other | Inspect `$query->removedScopes()`/`getQuery()->wheres`; assert the other scope key persists |
| Feature | Auto-stamp on create: `school_id` AND `periodo_academico_id` stamped from contexts | Create Oferta/Matricula with contexts bound, assert columns |
| Feature | No-op safety: no period bound → PeriodoScope adds no `where` (still tenant-filtered) | Unbind period, assert cross-period rows visible but cross-tenant not |
| Feature | MomentoAcademico period-scoped, tenant-inherited via period | Assert momentos of another school's period invisible under active period |
| Unit | Relationships: Nivel hasMany Grado; Oferta binds Grado+Sección to Periodo; Matricula → Oferta only; progression = ordered matriculas | Domain + Eloquent relation assertions |
| Unit | `PeriodoChannel::authorize()` — null school_id, non-numeric segment, match/mismatch | Case table mirroring `TenantChannelTest` |
| Architecture | Enrollment cannot target a catalog row (only OfertaAcademica) | Assert `matriculas` has no grado_id/seccion_id FK |

## Migration / Rollout
Additive; no existing academic data. Rollback = drop the 7 tables, revert `Modules/Academic` layers to `.gitkeep`, remove the period triad + `AcademicServiceProvider` bindings. Low risk (proposal).

## Open Questions
- [ ] `ResolveActivePeriodo` middleware (how the active period is resolved per request) is assumed but its resolution rule (from subdomain? from user selection?) is out of this change's scope — confirm it is a follow-up, not a blocker here.
- [ ] FK on-delete policy for catalog referenced by `ofertas_academicas` (restrict vs cascade) — default to `restrictOnDelete` for catalog, `cascadeOnDelete` for `matriculas` when an Oferta is removed; confirm in tasks.
