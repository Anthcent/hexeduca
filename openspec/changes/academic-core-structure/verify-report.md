# Verify Report: Academic Core Structure

## Verdict: PASS

## Test evidence (independently re-run by verifier, not copied from apply-progress)
- `docker compose exec -T app php artisan test` (full suite, no filter): **88 passed (231 assertions)**, duration 276.88s. Matches apply-progress's claimed 88/231 exactly.
- `docker compose exec -T app vendor/bin/pint --test`: **206 files, all clean**. Matches claimed count exactly.

## Task completeness
`tasks.md` grep for `[ ]` (unchecked) returned zero matches — genuinely 49/49 complete, not just claimed.

## Entity/file existence (spot-checked source, not just filenames)
All 7 entities exist across every hexagonal layer (Domain/Entities, Domain/Repositories, Infrastructure/Models, Infrastructure/Persistence, Infrastructure/Database/Migrations). Read full source for: `NivelAcademico`, `Seccion`, `PeriodoAcademico`, `MomentoAcademico`, `OfertaAcademica`, `Matricula` models plus the `Matricula` domain entity.

## 3-tier scoping matrix — verified in code
| Entity | Traits used (verified) |
|---|---|
| NivelAcademico / Grado / Seccion / PeriodoAcademico | `BelongsToTenant` only |
| MomentoAcademico | `BelongsToActivePeriodo` only — migration confirmed **no `school_id` column** |
| OfertaAcademica / Matricula | Both `BelongsToTenant` AND `BelongsToActivePeriodo` |

## Scope composition — real mechanism, not just claimed
`PeriodoScope::apply()` no-ops when `PeriodoContext::hasPeriodo()` is false. `BelongsToActivePeriodo` registers its scope keyed by `PeriodoScope::class`, independent from `TenantScope::class`; bypass method calls `withoutGlobalScope(PeriodoScope::class)` only. `tests/Feature/Academic/PeriodScopeCompositionTest.php` genuinely inspects `$query->removedScopes()` and `$query->toBase()->wheres` to prove each opt-out drops only its own clause — not a vacuous assertion.

## Middleware — confirmed registered, `is_active`-flag-based
`bootstrap/app.php` registers `ResolveActivePeriodo::class` in both `api` and `web` middleware groups, immediately after `ResolveTenant::class`. The middleware itself queries `PeriodoAcademico::where('is_active', true)->first()` — a manual flag, not date-range-derived. No-ops (leaves context unbound) if no tenant or no active period, rather than failing the request.

## Matricula cannot reference catalog directly
`matriculas` migration columns: `school_id`, `periodo_academico_id`, `oferta_academica_id`, `student_id`, `status`, `enrolled_at` — no `grado_id`/`seccion_id`. `tests/Architecture/Academic/EnrollmentTargetsOfertaOnlyTest.php` is a real, non-vacuous test asserting column absence via `Schema::getColumnListing()`, method absence via `method_exists()`, and a genuine FK to `ofertas_academicas` via `Schema::getForeignKeys()`.

## No promotion/progression table
`tests/Architecture/Academic/NoPromotionTableTest.php` scans migration file text and `Schema::getTables()` for `promo`/`progres` name fragments and asserts explicit non-existence, plus asserts all 7 real Academic tables exist. Confirmed no promotion table anywhere in the schema.

## Capacity counting fix — confirmed present
`EloquentMatriculaRepository::countByOfertaAcademica()`:
```php
return MatriculaModel::where('oferta_academica_id', $ofertaAcademicaId)
    ->where('status', 'active')
    ->count();
```
Matches Engram decision (obs #1563): capacity counting excludes non-active matriculas.

## PeriodoChannel — reads persisted columns only
`PeriodoChannel::authorize()` reads `$user->school_id` and validates route segments via `ctype_digit`, with an explicit doc comment stating it "Deliberately never reads PeriodoContext." Channel names are built from the row's own persisted `periodo_academico_id`, passed explicitly by the caller — never from request-scoped context singletons. Satisfies the core queue/broadcast safety requirement.

## Engram decisions cross-checked against code
1. Module placement `Modules/Academic` — confirmed.
2. Active-period resolution via manual `is_active` flag, not date-based — confirmed.
3. Capacity counting excludes non-active matriculas — confirmed.

## Issues
- CRITICAL: 0
- WARNING: 0
- SUGGESTION: 0 (informational: `pestphp/pest-plugin-arch` not installed; architecture tests use plain Pest `test()` + native `Schema` introspection instead of the `arch()` DSL — functionally equivalent, verified non-vacuous, already self-flagged in apply-progress as a deviation)

## Next recommended phase
`sdd-archive`
