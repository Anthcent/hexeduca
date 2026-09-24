---
name: create-module
description: "Trigger: new module, create module, build module, add module, crear módulo, nuevo módulo. Build and plug a new hexeduca module end to end."
license: Apache-2.0
metadata:
  author: "Anthony G"
  version: "1.0"
---

## Activation Contract

Load when the user asks to create, build, scaffold, or add a new module to hexeduca, or to extend an existing module with a new bounded feature.

## Hard Rules

- NEVER invent business rules. Get a completed `assets/module-brief.md` before writing domain code. Ask for missing answers one at a time.
- Scaffold only with `php artisan make:project-module {Name}`. Never hand-create the module tree.
- Never edit core files to plug in a module (`DashboardLayout.vue`, `bootstrap/app.php`, `modules_statuses.json`, seeders). Use `module.json` `permissions` and `navigation`.
- Sibling modules may import only `Modules\{Other}\Public\...`, and each one must be listed in `dependencies`. `app/` never imports `Modules\`.
- Every route keeps `auth` + `module:{alias}`. Add `role:` or `can:` per the brief.
- Tenant-owned models use `App\Tenancy\Concerns\BelongsToTenant` and a `school_id` column.
- Cross-module side effects go through a `Public/Events` integration event recorded with `OutboxEventRecorder` inside the same `DB::transaction`. No direct calls into another module's internals.
- Put tests in `tests/Feature/{Name}/` and `tests/Unit/{Name}/`. `Modules/*/Tests` is NOT run by any suite.
- Do not modify `tests/Architecture/**` to make a module pass. Fix the module.

## Decision Gates

| Need | Do |
|---|---|
| Read another module's data | Inject its `Public/Contracts/*Reader`. If missing, ask before adding one to that module. |
| React to another module's change | Listener in `Infrastructure/Listeners`, registered in the module `EventServiceProvider` |
| Data scoped to the active period | Also use `App\AcademicPeriod\Concerns\BelongsToActivePeriod` |
| Module required for the app to run | `core: true` — confirm with the user first |

## Execution Steps

1. Collect `assets/module-brief.md` and confirm it back in 3–5 bullets.
2. Run `make:project-module {Name}`. Fill `module.json` (description, dependencies, permissions with roles, navigation).
3. Build inward-out per `references/conventions.md`: Domain → Application → Infrastructure (migration, model, repository, controller, request, routes) → Public → Vue page.
4. Write Feature tests: happy path, permission denial, other-school isolation (404), plus a Unit test for each domain rule.
5. Run the quality gates in `references/conventions.md`. Fix every failure.
6. Run `php artisan modules:enable {alias} --promote`, then walk through `assets/manual-test-checklist.md`.

## Output Contract

Return: the brief summary, files created, `module.json` final content, gate results (real numbers), enable command output, and any open question. Log every failure using `assets/failure-report.md`.

## References

- `references/conventions.md` — layers, reference module, patterns, commands.
- `assets/module-brief.md` — business questions to answer before coding.
- `assets/manual-test-checklist.md` — verification steps after enabling.
- `assets/failure-report.md` — failure log format.
- `stubs/modules/module-md.stub` — generated MODULE.md with the manifest spec.
