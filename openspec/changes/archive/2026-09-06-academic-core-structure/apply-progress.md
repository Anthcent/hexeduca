# Apply Progress: Academic Core Structure

## Batch 4 of 4 — Phases 9-10 COMPLETE — ALL BATCHES DONE

**Scope this batch**: Mandatory capacity-status fix, the full Phase 9 instance-entity Pest suite against the REAL `OfertaAcademica`/`Matricula`/`MomentoAcademico` models (not the batch-2 fixture), and Phase 10 documentation (`Modules/Academic/README.md`).

### Mandatory fix (done before writing new tests)

`EloquentMatriculaRepository::countByOfertaAcademica()` was counting ALL `matriculas` rows regardless of `status`, so a withdrawn/inactive matricula never freed its capacity seat. Confirmed by the user: capacity MUST only count ACTIVE matriculas. Fixed to `where('status', 'active')->count()` (the `status` convention already established in batch 3: a plain string column, `'active'` is the literal default used by `MatricularEstudianteData`/`MatriculaFactory` — no enum/constant exists, so no new convention was invented). Updated the `MatriculaRepositoryInterface::countByOfertaAcademica()` docblock to state the `status = 'active'` filter explicitly. Added a dedicated test (`MatricularEstudianteTest`) asserting a withdrawn matricula frees the seat for another student.

### Completed Tasks (Batch 4)

- [x] 9.1 `tests/Feature/Academic/OfertaAcademicaScopingTest.php` — scope-stacking on REAL models (two schools × two periods), independent bypass (`withoutActivePeriodoScope()` still tenant-filters; `withoutTenantScope()` still period-filters) on real `OfertaAcademica`/`Matricula`, no-op safety (no period bound → cross-period visible, cross-tenant hidden), auto-stamp on create for both `OfertaAcademica` and `Matricula`
- [x] 9.2 `tests/Feature/Academic/MomentoAcademicoCrossTenantViaPeriodTest.php` — asserts `momentos_academicos` has no `school_id` column; momentos of another school's period are invisible under the active period; explicit period-scope bypass proves isolation is transitive via the period relationship, not a direct column
- [x] 9.3 `tests/Feature/Academic/MomentoAcademicoSharedAcrossOfertasTest.php` — the same 3 `MomentoAcademico` records are referenced (by shared `periodo_academico_id`) by every `OfertaAcademica` in that period
- [x] 9.4 `tests/Feature/Academic/CreateOfertaAcademicaTest.php` — rejects duplicate (periodo, grado, seccion); same catalog pair across two periods yields two distinct offerings with independent capacity
- [x] 9.5 `tests/Feature/Academic/MatricularEstudianteTest.php` — rejects enrollment past capacity; rejects duplicate (oferta, student); succeeds and stamps school_id+periodo_academico_id from the offering; withdrawn matricula frees its capacity seat (mandatory fix verification)
- [x] 9.6 `tests/Unit/Academic/ProgressionDerivationTest.php` — relationship assertions (Nivel hasMany Grado, OfertaAcademica binds Grado+Seccion+Periodo, Matricula belongsTo OfertaAcademica only with no grado()/seccion() methods) plus a concrete 3-cycle progression-derivation test (3ro/A → 4to/B → 5to/A, inserted out of chronological order, reconstructed purely via a `Matricula` → `OfertaAcademica` → `PeriodoAcademico`/`Grado` join ordered by `periodos_academicos.starts_on`)
- [x] 9.7 `tests/Architecture/Academic/EnrollmentTargetsOfertaOnlyTest.php` — `matriculas` schema has no `grado_id`/`seccion_id` column; `Matricula` model has no `grado()`/`seccion()` methods; `matriculas` has a real FK to `ofertas_academicas`
- [x] 9.8 `tests/Architecture/Academic/NoPromotionTableTest.php` — no migration file creates a `*promo*`/`*progres*`-named table; no such table exists in the migrated schema; the 7 declared Academic tables exist and `promociones`/`progressions` do not
- [x] 10.1 `Modules/Academic/README.md` — created (module had no README before): catalog/instance split, 3-tier scoping matrix (reused verbatim from design.md), period-scoping triad description, the `withoutGlobalScopes()` danger / class-keyed bypass rule, the queue/broadcast persisted-column gotcha, key enforced invariants (with test file pointers), and explicit Out of Scope for future contributors

### Summary

**49/49 tasks complete (Phases 1-10 of 10). All batches done. Ready for `sdd-verify`.**

88 passed tests (231 assertions) | 206 files clean via Pint | Zero failures, zero regressions.

---

*Full batch-by-batch history preserved in the original apply-progress.md in the change folder. This archive-report summary reflects the FINAL state: all implementation complete, all tests passing, all tasks marked complete.*
