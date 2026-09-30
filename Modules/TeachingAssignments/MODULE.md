# TeachingAssignments module

Generated with `php artisan make:project-module TeachingAssignments`.

## Layout

- `Domain/` — entities, repository interfaces, value objects. No framework code.
- `Application/` — use cases and DTOs orchestrating the domain.
- `Infrastructure/` — Eloquent models, persistence, HTTP controllers, providers,
  migrations. The only layer allowed to depend on Laravel and on other modules'
  `Public/` layer.
- `Public/` — the module's public contract. Sibling modules may only depend on
  classes under this namespace (`Modules\TeachingAssignments\Public\...`). Enforced by
  `tests/Architecture/Support/ModuleArchitectureValidator.php`.
- `routes/web.php` and `routes/api.php` — guarded with `auth` (or
  `auth:sanctum`) plus the `module:teachingassignments` middleware alias.
- `Tests/Unit` and `Tests/Feature` — module-scoped tests.

## Manifest (`module.json`)

- `core` — `true` only for modules the app cannot run without (Users, Admin).
- `maturity` — `skeleton` until the module has real behavior, then `mature`.
- `dependencies` — sibling module keys this module's `Public\` imports are
  allowed to reference.
- `permissions` — either Spatie permission strings this module owns, or
  objects `{"name": "...", "roles": ["..."]}` to auto-assign the permission
  to existing roles when `modules:sync` runs (manual grants are never
  revoked). Example:
  ```json
  "permissions": [
      "teachingassignments.manage",
      {"name": "teachingassignments.view", "roles": ["admin", "teacher"]}
  ]
  ```
- `navigation` — optional sidebar entries added to `DashboardLayout.vue`.
  Each entry is `{"label": "...", "route": "...", "icon": "...", "group": "...", "permission": "..."}`
  (`icon` is a lucide icon name in PascalCase, resolved offline through the
  map in `resources/js/Layouts/navigation.js`, where unknown names fall back
  to `Blocks`; `group` is optional and one of `inicio`, `academico`,
  `comunicacion`, `documentos`, `administracion`, defaulting to a "Más"
  group; `permission` is optional — omit it to show the entry to every user
  who can access the module). Starts
  empty (`[]`); JSON has no comments, so this file is the reference. Example:
  ```json
  "navigation": [
      {"label": "TeachingAssignments", "route": "teachingassignments.index", "icon": "Blocks", "permission": "teachingassignments.view"}
  ]
  ```

## How to enable this module

1. Write the module's code and set `"maturity": "mature"` in `module.json`
   once it's ready for real use (or run
   `php artisan modules:enable teachingassignments --promote`, which does it for you).
2. `php artisan modules:sync` — upserts the `modules` table row and syncs
   any declared `permissions` (creating them and, for the `{name, roles}`
   form, assigning them to the listed roles).
3. `php artisan modules:enable teachingassignments` (add `--all-schools` to
   entitle every existing school immediately; schools created afterwards
   are auto-entitled regardless of this flag).
4. That's it — no core file needs hand-editing. Routes are already gated by
   `module:teachingassignments`, the sidebar picks up `navigation` automatically,
   and schools without entitlement get a 404, not a broken link.
