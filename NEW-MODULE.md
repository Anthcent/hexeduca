# Start here: building a hexeduca module

Open this file at the start of any session that creates a new module or finishes a skeleton, whatever AI tool you use. It says what the project is, what to read and in which order, and how the work runs from brief to green CI.

## 1. Context in one minute

hexeduca is a multi-tenant school management system: one school per subdomain (`demo.localhost`), plus a landlord at `admin.localhost`.

- **Stack:** Laravel 12 with nwidart modules, Spatie Permission, Inertia v3 + Vue 3, Tailwind v4 + Nuxt UI 4.
- **Modules:** every feature is a module in `Modules/{Name}` with `Domain/`, `Application/`, `Infrastructure/` and `Public/` layers. Tests in `tests/Architecture` enforce the module boundaries.
- **Access:** a module is switched on per school (entitlement). An unavailable module returns 404, a missing permission returns 403.
- **Stage:** the app is in development. Keep it simple and avoid speculative abstractions.

## 2. Read these, in this order, before writing anything

1. `CLAUDE.md`: commands, architecture rules, known gaps, and how to run locally.
2. `AGENTS.md`: where the project skills live.
3. `.agents/skills/create-module/SKILL.md`: the hard rules and steps. Follow it exactly.
4. `.agents/skills/create-module/references/conventions.md`: layers, reference modules, patterns, gotchas and quality gates.
5. `.agents/skills/create-module/assets/module-brief.md`: the questions to answer before any domain code.
6. The reference modules, as needed:
   - `Modules/Notifications`, when the module reads another module's data or shares an Inertia prop.
   - `Modules/Files`, for a rich domain, disk storage, or atomic failure handling.

## 3. How the work runs

Close each module before starting the next. Don't batch tests at the end of several modules: a tenant-isolation bug would spread to all of them.

### Phase A: brief (no code yet)

- Fill `module-brief.md` with the user. Ask one question at a time and never invent business rules.
- Confirm the brief back in 3–5 bullets and wait for an explicit yes.
- Anything not in the brief goes under "Out of scope".

### Phase B: build fast, with the app running

- Start the dev loop with `composer run dev:windows` on this Windows machine, or `composer run dev` on Linux/macOS.
- Then open `http://demo.localhost:8000`. The seeded users are `staff@demo.test`, `teacher@demo.test` and `student1@demo.test`, all with password `password`.
- Vite reloads `.vue` changes live. Iterate on the UI and the flow with the user here.
- Build inward-out: Domain, then Application, then Infrastructure, then Public, then the Vue page.
- New modules: scaffold with `php artisan make:project-module {Name}`. Skeletons (`maturity: skeleton`): fill the existing tree, don't re-scaffold.

### Phase C: close the module (required before the next one)

1. Tests in `tests/Feature/{Name}` and `tests/Unit/{Name}`:
   - the happy path;
   - 403 for the wrong role;
   - 404 for another school's record and for a non-entitled school;
   - validation boundaries;
   - one Unit test per domain rule.
2. Quality gates from `conventions.md`: Pest (Unit, Feature, Architecture), Pint and `npm run build`.
3. The Feature suite on PostgreSQL. SQLite hides PostgreSQL-only bugs, and CI will run it anyway.
4. Run `modules:enable {alias} --promote`, then walk through `assets/manual-test-checklist.md` in the browser.
5. Get a fresh-context review:
   - risk (security, tenant isolation) and reliability (behavior, tests) for any module;
   - resilience as well when the module touches shell processes or external services.
6. Commit with conventional commits and no AI attribution, push, and confirm CI is green: `quality`, `production-like` and `secrets`.

## 4. What passing the gates does and doesn't prove

- **Structure:** the rules and `tests/Architecture` guarantee it — layers, module boundaries, and routes guarded by `module:`.
- **Behavior:** only behavior tests guarantee it — tenant isolation, permissions, and the business rules.
- **Real environment:** only CI guarantees it — PostgreSQL, Redis, and a Linux case-sensitive filesystem.

Following the skill makes the work predictable; it doesn't make it correct on its own. Treat a module as done only when all three hold.

## 5. Prompt to start a session

> Read `NEW-MODULE.md` and follow it. I want to build the `{Name}` module: {one-sentence purpose}. Start with Phase A: ask me the brief questions one at a time.
