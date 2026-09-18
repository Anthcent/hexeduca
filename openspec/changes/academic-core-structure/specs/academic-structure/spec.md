# Spec: Academic Structure

## Capability: academic-structure

### Purpose
Establish the 7 catalog/temporal/instance entities and enrollment model that every future academic module (grades, subjects, schedules, certificates) hangs off, inside `Modules/Academic`. Defines WHAT must exist and hold true — not implementation.

### Requirement: Catalog entities carry no cycle reference
`NivelAcademico`, `Año/Grado`, and `Sección` MUST be modeled as fixed catalog data with no foreign key to any academic cycle or period. They MUST be defined once and reused across every `PeriodoAcademico`.

#### Scenario: Catalog entity has no period FK
- GIVEN a `NivelAcademico`, `Año/Grado`, or `Sección` record
- WHEN its schema/attributes are inspected
- THEN no column references `PeriodoAcademico` or any cycle identifier

#### Scenario: Catalog reused across cycles
- GIVEN a catalog `Año/Grado` and `Sección` created for one cycle
- WHEN a new `PeriodoAcademico` starts
- THEN the same catalog records are reused to build that period's `OfertaAcademica` instances, with no duplication of catalog data

### Requirement: PeriodoAcademico is the temporal top-level entity
The system MUST provide `PeriodoAcademico` (e.g. "2025-2026") as the top-level temporal scope that all instance-level academic entities anchor to.

#### Scenario: Period identifies a school cycle
- GIVEN a school operating across multiple years
- WHEN a `PeriodoAcademico` is created
- THEN it uniquely identifies one academic cycle for that school

### Requirement: MomentoAcademico subdivides a period globally
`MomentoAcademico` MUST represent a grading-cut subdivision of exactly one `PeriodoAcademico` (typically 3, config/date-driven). It MUST be global within its period — it MUST NOT vary per `Año/Grado`, `Sección`, or `OfertaAcademica`.

#### Scenario: Moments are shared across all offerings in a period
- GIVEN a `PeriodoAcademico` with 3 `MomentoAcademico` records
- WHEN any `OfertaAcademica` within that period is inspected
- THEN it references the same 3 `MomentoAcademico` records as every other `OfertaAcademica` in that period

#### Scenario: Moment count is configurable, not hardcoded
- GIVEN a school configuring a period with a different number of grading cuts
- WHEN `MomentoAcademico` records are created for that period
- THEN the count is driven by configuration/dates, not a fixed literal of 3

### Requirement: OfertaAcademica instantiates a catalog Año+Sección within a period
`OfertaAcademica` MUST bind one catalog `Año/Grado` and one catalog `Sección` to exactly one `PeriodoAcademico`, carrying capacity and an assigned teacher. It MUST NOT exist without a `PeriodoAcademico`.

#### Scenario: Offering created for a cycle
- GIVEN a catalog `Año/Grado`, a catalog `Sección`, and an active `PeriodoAcademico`
- WHEN an `OfertaAcademica` is created
- THEN it references all three, plus a capacity value and an assigned teacher

#### Scenario: Same catalog pair reused across periods as distinct offerings
- GIVEN the same catalog `Año/Grado` and `Sección` used in two different `PeriodoAcademico` records
- WHEN both `OfertaAcademica` instances are inspected
- THEN they are two distinct records, each scoped to its own period, with independent capacity/teacher

### Requirement: Matricula enrolls students into OfertaAcademica only
`Matricula` MUST represent the enrollment relationship between a student and an `OfertaAcademica`. `Matricula` MUST NOT reference a catalog `Año/Grado` or `Sección` directly.

#### Scenario: Enrollment targets an offering
- GIVEN a student and an `OfertaAcademica`
- WHEN a `Matricula` is created
- THEN it references the student and that `OfertaAcademica`, and no catalog `Año/Grado` or `Sección` foreign key exists on `Matricula`

### Requirement: Progression is derived from Matricula history, never stored
Year-over-year student progression MUST be derivable purely by querying a student's `Matricula` records ordered by their `OfertaAcademica`'s `PeriodoAcademico`. The system MUST NOT persist a promotion/progression table in this capability's scope.

#### Scenario: Progression reconstructed from history
- GIVEN a student with `Matricula` records across three consecutive `PeriodoAcademico` cycles
- WHEN their progression is queried
- THEN it is computed by ordering their `Matricula` records by period, with no separate promotion/progression table consulted or required

#### Scenario: No promotion table exists
- GIVEN the full `Modules/Academic` schema for this capability
- WHEN the schema is inspected
- THEN no table stores promotion or progression state independent of `Matricula`

## Out of Scope (explicit non-requirements)
Materias/subjects, Notas/grades, resúmenes finales, certificados, and any promotion/progression workflow are explicitly out of scope for this capability (see proposal `academic-core-structure`, Scope > Out of Scope). No UI/frontend is covered.
