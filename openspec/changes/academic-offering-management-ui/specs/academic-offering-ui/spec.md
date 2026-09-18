# Spec: Academic Offering UI

## Capability: academic-offering-ui

### Purpose
Provide the first browser-facing screens over the `academic-structure` backend: a Create Oferta Académica form and a Matricular Estudiante form, both role-gated, wired directly to existing use cases (`CreateOfertaAcademica`, `MatricularEstudiante`) with no reimplemented business rules.

### Requirement: Staff/admin can create an Oferta Académica via a form
The system MUST provide a screen where an authenticated `staff` or `admin` user submits catalog `Año/Grado`, `Sección`, capacity, and an optional teacher; the active `PeriodoAcademico` MUST be derived from context, never from user input, and the submission MUST be executed through `CreateOfertaAcademica`.

#### Scenario: Valid submission creates the offering
- GIVEN an authenticated `staff` user and an active `PeriodoAcademico`
- WHEN they submit a valid `Año/Grado` + `Sección` + capacity (teacher optional)
- THEN the system creates an `OfertaAcademica` for the active period via `CreateOfertaAcademica` and confirms success

#### Scenario: Duplicate Periodo+Grado+Sección surfaces as a form error
- GIVEN an `OfertaAcademica` already exists for the active period, that `Año/Grado`, and that `Sección`
- WHEN the same combination is submitted again
- THEN the `DomainException` from the use case is translated into a user-facing form validation error, not a raw exception page

#### Scenario: Other validation failures surface as form errors
- GIVEN invalid or missing required fields (e.g. missing capacity, invalid catalog reference)
- WHEN the form is submitted
- THEN the system returns field-level validation errors without invoking the use case, or translates a `DomainException` into field-level errors

### Requirement: Staff/admin can enroll a student via a form
The system MUST provide a screen where an authenticated `staff` or `admin` user selects an existing `OfertaAcademica` and a student; `school_id` and `periodo_academico_id` MUST be derived from the selected `OfertaAcademica`, never from user input, and enrollment MUST be executed through `MatricularEstudiante`.

#### Scenario: Valid submission enrolls the student
- GIVEN an existing `OfertaAcademica` with available capacity and a student not yet enrolled in it
- WHEN a `staff` user selects that offering and student and submits
- THEN a `Matricula` is created via `MatricularEstudiante`, with `school_id`/`periodo_academico_id` taken from the `OfertaAcademica`

#### Scenario: Capacity-full surfaces as a form error
- GIVEN an `OfertaAcademica` at full capacity
- WHEN a student is submitted for enrollment into it
- THEN the `DomainException` is translated into a user-facing error, not a raw exception page

#### Scenario: Duplicate enrollment surfaces as a form error
- GIVEN a student already enrolled in the selected `OfertaAcademica`
- WHEN the same student+oferta pair is submitted again
- THEN the `DomainException` is translated into a user-facing error

### Requirement: Both screens are role-gated
Both routes MUST reject unauthenticated requests and MUST reject authenticated requests from users without the `staff` or `admin` role.

#### Scenario: Unauthenticated request is rejected
- GIVEN no authenticated session
- WHEN either route is requested
- THEN the request is rejected per this app's existing auth convention (redirect to login or 401/403)

#### Scenario: Authenticated non-staff/admin request is rejected
- GIVEN an authenticated user without the `staff` or `admin` role
- WHEN either route is requested
- THEN the system responds with 403

### Requirement: No-active-period is an explicit valid state
When no `PeriodoAcademico` is active for the tenant, the Create Oferta screen MUST show an explicit "no active period" state and MUST NOT allow form submission.

#### Scenario: No active period blocks submission
- GIVEN no `PeriodoAcademico` is active for the resolved tenant
- WHEN a `staff` user opens the Create Oferta screen
- THEN the screen renders an explicit no-active-period message and the submit action is unavailable, with no exception raised

### Requirement: Demo data makes both screens usable from a fresh clone
A fresh clone plus seed MUST produce at least one `School` with an active `PeriodoAcademico` and enough catalog rows (`NivelAcademico`/`Año-Grado`/`Sección`) that both screens are immediately usable without manual database setup.

#### Scenario: Fresh seed enables both flows
- GIVEN a freshly migrated and seeded database
- WHEN a `staff` user opens either screen
- THEN catalog/offering selects are already populated and both the create-offering and enroll-student flows can be completed without manual data entry

### Requirement: Controllers only map DTOs and translate exceptions
Controllers backing both screens MUST limit their responsibility to mapping request input into use-case DTOs, invoking the use case, and translating `DomainException` into validation-friendly responses. Controllers MUST NOT re-implement capacity, uniqueness, or any other business rule already enforced by `CreateOfertaAcademica`/`MatricularEstudiante` per `academic-structure`.

#### Scenario: Controller delegates rule enforcement to the use case
- GIVEN a submission that violates capacity or uniqueness rules
- WHEN the controller processes it
- THEN the controller performs no independent capacity/uniqueness check — the violation is detected and reported exclusively by the use case's `DomainException`

#### Scenario: Controller only maps and translates
- GIVEN the controller code for either screen
- WHEN it is inspected
- THEN it contains DTO construction, use-case invocation, and `DomainException`-to-validation-error translation, with no duplicated business logic

## Out of Scope (explicit non-requirements)
Catalog CRUD screens, a combined dashboard, index/listing views beyond selects, Notas/Materias, and granular spatie permissions (role-check only) are explicitly out of scope for this capability (see proposal `academic-offering-management-ui`). Scoping of student/teacher selects to tenant/role is left to design.
