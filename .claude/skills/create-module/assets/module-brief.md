# Module brief

Answer every item before any domain code is written. "Unknown" is a valid answer only if it is explicitly accepted as out of scope.

1. **Name and purpose** — Module name (PascalCase) and the problem it solves, in one sentence.
2. **Users** — Which roles use it, and what each role can do (view / create / edit / delete / approve).
3. **Entities** — The main things it stores, with their key fields and required vs optional status.
4. **Business rules** — Validations, states and allowed transitions, limits, uniqueness, calculations. One rule per line.
5. **Scope** — Per school (tenant)? Tied to the active academic period?
6. **Dependencies** — Data it reads from other modules (for example, students from Users or sections from Sections).
7. **Events** — What other modules must learn when something happens here, and which other modules' events this module reacts to.
8. **Screens** — The pages needed and what each one shows or does.
9. **Navigation** — Sidebar label, and who sees it.
10. **Out of scope** — What this module explicitly will NOT do in this version.
