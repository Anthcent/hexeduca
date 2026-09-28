# Using other languages and stacks

The final module is PHP 8.3 + Vue 3 + Nuxt UI 4, so PHP and Vue port with the least effort. Other languages are allowed, but the repo session **ports** their code instead of copying it. These rules keep that port mechanical.

## The rule that always applies

**The source of truth is `CONTRACT.md` and `scenarios.md`, never the code.** They must be complete and understandable without reading any code. If the code and the contract disagree, the contract wins.

## Domain logic in another language

The languages you can use are TypeScript/JavaScript, Python, Java, C#, Go, Kotlin, Dart, Ruby, and anything similar.

**Required**
- Write plain classes or functions, with no framework: no NestJS, Django, Spring, Express, or FastAPI.
- Use no ORM or database: no Prisma, TypeORM, SQLAlchemy, Hibernate, Mongoose, or Entity Framework.
- Use no external libraries except a test runner. Implement validation by hand; don't use Zod, Pydantic, Joi, or class-validator.
- Keep one class or type per concept, with the same names as `CONTRACT.md`: entities, value objects, and errors.
- Model each failure as a named error or exception type, never a bare string or a magic number.
- Model dates as `YYYY-MM-DD` strings or plain date values, and pass "today" in as a parameter.
- Model money and grades as integers or decimal strings, never floats, and state the precision in `CONTRACT.md`.
- Use no global state, singletons, or hidden I/O.

**Avoid**, because none of these ports well to PHP:
- decorators, annotations, and reflection-driven magic;
- metaprogramming;
- operator overloading;
- union types with more than a few members;
- generics-heavy designs;
- functional pipelines that hide the rule.

Write the rules as plain, readable conditions.

**Tests.** If you write tests in your language, keep them. They help, but they don't replace `scenarios.md`.

**Say what you used.** In `NOTES.md`, name the language and version.

## Screens in another stack

| Stack | Allowed? | Conditions |
|---|---|---|
| Vue 3 + Nuxt UI 4 | Preferred | See `03-DELIVERABLE.md` |
| Vue 3 with another UI library, or plain Tailwind | Yes | Map each component to its Nuxt UI equivalent in `NOTES.md` |
| React, Svelte, Angular, Solid | Yes | Same props-and-actions rules, and the component mapping |
| Plain HTML + CSS/Tailwind | Yes | Put the sample data in a `<script>` block, and write the actions as named functions |
| Figma, images, hand sketches | Yes, for the look only | You still owe the per-screen data shape and the list of actions in `CONTRACT.md` |

**Required in any stack**
- The page receives its data from outside, as props or one data object. It never fetches, calls an API, or reads storage.
- Actions are functions named after the use cases, with stub bodies.
- Use no router, global state store (Redux, Pinia, Zustand, NgRx), or i18n library.
- Don't build a layout, sidebar, top bar, or login.
- Use only Lucide icons, named by their Lucide name.
- UI copy is in neutral Spanish.

**Component mapping.** When you don't use Nuxt UI, list the equivalents in `NOTES.md`:

| Your component | Nuxt UI |
|---|---|
| Button | `UButton` |
| Text input | `UInput` |
| Select | `USelect` |
| Table / data grid | `UTable` |
| Dialog / modal | `UModal` |
| Card / panel | `UCard` |
| Badge / tag / chip | `UBadge` |
| Alert / toast | `UAlert` |
| Tabs | `UTabs` |
| Menu | `UDropdownMenu` |
| Form field with label and error | `UFormField` |

## Data models in other formats

You may sketch the data as SQL, a Prisma schema, Django models, JSON Schema, or TypeScript types, but only as **documentation**. It must match the data model table in `CONTRACT.md` exactly, and it must follow the data rules:
- no `school_id`, `created_at`, or `updated_at`;
- other modules referenced by ID;
- no copied fields.

The repo session writes the real migrations.

## Language of text

| Text | Language |
|---|---|
| Identifiers, code, and comments | English |
| `CONTRACT.md` and `scenarios.md` | English |
| `BRIEF.md` | English or Spanish |
| UI copy | Neutral Spanish, with no regional slang |
| Error messages shown to users | Neutral Spanish |
| Error type names | English |

Whatever language you use, the parts must stay consistent. A use case called `CloseAttendanceSheet` in the contract is `CloseAttendanceSheet` (or `closeAttendanceSheet` / `close_attendance_sheet`, per the language's convention) in the code and the screens.
