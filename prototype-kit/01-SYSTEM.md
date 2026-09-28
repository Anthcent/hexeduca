# The system

## hexeduca in one minute

- **Product:** a school management system. Each school is a tenant on its own subdomain (`demo.hexeduca...`), and no school can see another school's data. A separate landlord area manages schools.
- **Stack:** Laravel 12 (PHP 8.3), Inertia + Vue 3, Nuxt UI 4, Tailwind 4, and PostgreSQL.
- **Modules:** each feature is a module. A module is switched on per school, and the routes of a switched-off module don't exist for that school.
- **Architecture:** each module has four layers: Domain (plain entities and rules), Application (one use case per action), Infrastructure (database, HTTP, and framework code), and Public (the only part other modules may use). A module never touches another module's internals.
- **Roles:**

  | Role | Who |
  |---|---|
  | `staff/admin` | school administration |
  | `teacher` | teachers |
  | `student` | students |
  | `super-admin` | landlord only; never acts inside a school |

  If your module needs another role (for example, guardians), list it as an open question.
- **Stage:** in development. Simple beats clever.

## School vocabulary

| Term | Meaning |
|---|---|
| Academic period | The school year, for example "2026". A school has one active period at a time. |
| Academic level | A stage, for example "Primaria" or "Secundaria". |
| Grade level | A year within a level, for example "1er grado". It has an order. |
| Section | Only a name, for example "A" or "B". |
| Academic offer | Period + grade level + section: one real class group, for example "2026 · 1er grado · A". It has a capacity and an optional homeroom teacher. When people say "course" or "class", they usually mean this. |
| Enrollment | A student enrolled in an academic offer for a period, with a status. |
| Study plan | An official curriculum (with a code) listing the subjects per grade level, assigned per period to the whole school, a grade level, or an offer. |

## Data that already exists

Your prototype may **read** these fields. Reference them by ID; never copy them into your own tables.

| Concept | Readable fields |
|---|---|
| Academic period | `id`, `name`, `isActive`, `startsOn`, `endsOn` |
| Academic level | `id`, `name` |
| Grade level | `id`, `name`, `order` |
| Section | `id`, `name` |
| Academic offer | `id`, `academicPeriodId`, `gradeLevelId`, `sectionId`, `teacherId` (nullable), `capacity`, `gradeLevelName`, `sectionName` |
| Enrollment | `id`, `academicPeriodId`, `academicOfferId`, `studentId`, `status`, `enrolledAt` |
| Student | `id`, `name`, `email` |
| Teacher | `id`, `name`, `email` |
| Study plan / subject | Plans with an official code, and their subjects per grade level. No public read access exists yet; if you need it, list it as a dependency. |

If you need a field that isn't listed, **don't invent it**. Put it under "Open questions" in the brief.

## Modules that already exist

- Academic (legacy)
- AcademicLevels, AcademicMoments (evaluation moments within a period), AcademicOffers, and AcademicPeriods
- Enrollments
- Files (a per-school file repository)
- GradeLevels and Sections
- Grades (marks)
- Notifications (announcements)
- Schedule (not built yet)
- Subjects (study plans)
- Users

**Overlap is allowed.** Your prototype may cover something one of these already does, or improve it. Don't limit the design because of it. Just record it in the brief, in the "Overlap with existing modules" section, and the repo session will analyze how to implement it (see `06-HANDOFF.md`).
