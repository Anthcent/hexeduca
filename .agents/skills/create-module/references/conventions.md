# hexeduca module conventions

Reference modules (built with this skill, reviewed, CI green):

- `Modules/Notifications`: reads another module through its `Public` readers, fans out rows in one transaction, and shares an Inertia prop (the header bell).
- `Modules/Files`: rich domain (value objects and rules with Unit tests), disk storage, atomic failure handling, and per-owner authorization.

`Modules/Sections` is still the smallest example of a Public reader and an integration event. Don't copy its page (no layout) or its use case (plain `event()`, no outbox).

## Layers

| Layer | Contains | May depend on |
|---|---|---|
| `Domain/Entities`, `Domain/Repositories` | Plain PHP entities and repository interfaces | Nothing framework-specific |
| `Application/UseCases`, `Application/DTOs` | One use case per action; input DTOs | Domain, `OutboxEventRecorder`, other modules' `Public` |
| `Infrastructure/Models` | Eloquent models (`BelongsToTenant`) | Laravel |
| `Infrastructure/Persistence` | `Eloquent*Repository`, `Eloquent*Reader` (implements the Public contract) | Models, Domain |
| `Infrastructure/Http/Controllers`, `Http/Requests` | Thin controllers calling use cases; FormRequests with `authorize()` | Application |
| `Infrastructure/Providers` | Service, Route, and Event providers; bind interfaces here | Everything in the module |
| `Infrastructure/Database/Migrations` | Additive migrations with a `school_id` FK for tenant data | Laravel |
| `Infrastructure/Listeners` | Handlers for other modules' integration events | Application, other `Public` |
| `Public/Contracts`, `Public/DTOs`, `Public/Events` | The ONLY surface other modules may use | Nothing internal leaks out |
| `Resources/js/Pages/*.vue` | Inertia pages inside `DashboardLayout`, opening with `PageHeader` (eyebrow, title, description, `#actions`) and built with Nuxt UI components (`UButton`, `UInput`, `UCard` + `PanelHeader`, `UModal`, ...) | — |

## Patterns (copy from these files)

- Tenant model: `Modules/Sections/Infrastructure/Models/Section.php`
- Public reader: `Modules/Sections/Public/Contracts/SectionReader.php` + `Infrastructure/Persistence/EloquentSectionReader.php`
- Integration event: `Modules/Sections/Public/Events/SectionCreated.php` (implements `IntegrationEvent`, uses `HasIntegrationEventEnvelope`, stable `eventName()` like `section.created`)
- Outbox inside a transaction: `Modules/AcademicOffers/Application/UseCases/CreateAcademicOffer.php`
- Consuming another module's event: `Modules/Grades/Infrastructure/Listeners/ProjectEnrollmentListener.php` + `Modules/Grades/Infrastructure/Providers/EventServiceProvider.php`
- Routes: `Modules/Notifications/routes/web.php`. Order the middleware as `auth`, then `module:{alias}`, then `permission:{alias}.*`, so an unavailable module returns 404 before any 403.
- Page with layout and Nuxt UI: `Modules/Notifications/Resources/js/Pages/Index.vue`, `Modules/Files/Resources/js/Pages/Index.vue`
- Shared Inertia prop owned by a module: `Modules/Notifications/Infrastructure/Providers/NotificationsServiceProvider.php`. It uses a lazy `Inertia::share` and returns null when the module is unavailable. `app/` never imports `Modules\`.
- Value objects and domain rules with Unit tests: `Modules/Files/Domain/ValueObjects/*`, `tests/Unit/Files/*`
- Feature tests (seed the registry, entitle the school, check 403 and 404): `tests/Feature/Notifications/NotificationsTest.php`, `tests/Feature/Files/FilesTest.php`

## Gotchas already paid for

- **Nav icons and groups:** `resources/js/Layouts/navigation.js` maps manifest icon names (Bell, Blocks, BookOpen, Calendar, CalendarRange, ClipboardCheck, FileText, FolderOpen, GraduationCap, LayoutDashboard, ListChecks, Megaphone, MessageSquare, Receipt, School, Search, Settings, Users) to bundled `i-lucide-*` icons. Any other `icon` falls back to Blocks; adding one means editing that core file, so ask the user first. Set the optional `group` (`academico`, `comunicacion`, `documentos`, `administracion`) to place the entry in the sidebar.
- **Nuxt UI icons:** use static names only (`icon="i-lucide-send"`). Vite bundles the lucide collection; a dynamic `:icon` binding triggers runtime calls to `api.iconify.design`.
- **Theme:** never override `--ui-radius`, because it changes every `rounded-*` class in the app. Nuxt UI colors are already mapped to the app palette in `resources/css/app.css`.
- **Page props:** never name a page prop after a shared prop (for example `notifications`); the page prop hides the shared one.
- **Promote:** `modules:enable --promote` rewrites `module.json`: it escapes `/` as `\/` and drops the trailing newline. Restore the hand formatting afterwards.
- **Skeleton tests:** `tests/Feature/SkeletonModulesRouteExposureTest.php`, `tests/Unit/ModulePlatform/ModuleManifestTest.php` and the `tests/Feature/ModulePlatform/*` tests name a skeleton module as their example. When you promote one, switch them to a remaining skeleton; don't delete them.
- **Drivers:** PostgreSQL returns `false` for booleans (SQLite returns `0`) and enforces `uuid` columns. Assert booleans as booleans.
- **Queued listeners:** with Redis, queued listeners don't run inline. Tests that need their effect call `$this->runQueuedJobs()`.
- **Uploads:** PHP defaults to 2 MB. The dev loop raises it through `scripts/dev/php-dev.ini`; production needs the same ini values.

## module.json

Full field spec in `stubs/modules/module-md.stub` (copied to each module's `MODULE.md`). Minimum for a working module:

```json
"maturity": "mature",
"dependencies": ["users"],
"permissions": [
    {"name": "billing.view", "label": "Ver", "kind": "view", "roles": ["staff/admin"]},
    {"name": "billing.manage", "label": "Gestionar", "kind": "manage", "roles": ["staff/admin"]}
],
"navigation": [{"label": "Billing", "route": "billing.index", "icon": "Receipt", "group": "administracion", "permission": "billing.view"}]
```

Permissions, not roles. Routes check `permission:{alias}.*` and never `role:`, so schools can build their own roles later.

- Every module declares `{alias}.view` (read routes) and `{alias}.manage` (write routes). A role that manages also gets `view`.
- `kind` is one of:
  - `view` or `manage`: the baseline;
  - `function`: a screen or action inside the module, such as `grades.correction`;
  - `scope`: which records the user sees, such as `grades.scope.all` versus only the user's own sections.
- `label` is the Spanish UI copy shown in the role editor. `ModuleRegistry::sync()` rejects an object entry without a `label` or a valid `kind`.
- The super-admin passes every permission check through `Gate::before`. Policies keep their own rules.

`icon` is a lucide name in PascalCase, but only the names mapped in `resources/js/Layouts/navigation.js` render (see Gotchas). `group` is optional. Role names must already exist (see `database/seeders`): `staff/admin`, `teacher`, `student`, `super-admin`.

## Commands

PHP on PATH may be 8.0 — use the 8.3 binary:

```bash
PHP="$LOCALAPPDATA/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe"
"$PHP" artisan make:project-module Billing
"$PHP" artisan modules:sync
"$PHP" artisan modules:enable billing --promote        # add --all-schools to entitle existing schools
"$PHP" artisan modules:entitle billing {subdomain}     # --revoke to remove
"$PHP" artisan modules:list
```

## Quality gates (all must pass)

```bash
"$PHP" vendor/bin/pest --testsuite=Unit,Feature,Architecture
"$PHP" vendor/bin/pint --test
npm run build
```

Feature tests that hit module routes must seed the registry first: `$this->seed(\Database\Seeders\ModulePlatformSeeder::class)`, then entitle the test school.

Then run the Feature suite on PostgreSQL, because SQLite hides PostgreSQL-only bugs. With Docker running and the `pgsql` service up (`docker compose up -d pgsql`), use a separate database so the dev data survives:

```bash
docker compose exec -T pgsql sh -c 'createdb -U "$POSTGRES_USER" testing 2>/dev/null || true'
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=testing CACHE_STORE=array QUEUE_CONNECTION=sync \
  "$PHP" artisan test --testsuite=Feature
```

`phpunit.xml` doesn't force its env values, so these variables override them.

Finally push and confirm CI is green: `quality`, `production-like` (PostgreSQL + Redis) and `secrets`.

## Access model

A request passes only if: the module is active AND (core OR the school is entitled) AND the user has the route's role or permission. An unavailable module returns 404 (not 403) by design.
