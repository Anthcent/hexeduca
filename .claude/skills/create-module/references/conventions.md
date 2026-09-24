# hexeduca module conventions

Reference module: `Modules/Sections` (small, mature, tenant-scoped, publishes an event).

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
| `Resources/js/Pages/*.vue` | Inertia pages rendered with `DashboardLayout` and the `Ui*` components | — |

## Patterns (copy from these files)

- Tenant model: `Modules/Sections/Infrastructure/Models/Section.php`
- Public reader: `Modules/Sections/Public/Contracts/SectionReader.php` + `Infrastructure/Persistence/EloquentSectionReader.php`
- Integration event: `Modules/Sections/Public/Events/SectionCreated.php` (implements `IntegrationEvent`, uses `HasIntegrationEventEnvelope`, stable `eventName()` like `section.created`)
- Outbox inside a transaction: `Modules/AcademicOffers/Application/UseCases/CreateAcademicOffer.php`
- Consuming another module's event: `Modules/Grades/Infrastructure/Listeners/ProjectEnrollmentListener.php` + `Modules/Grades/Infrastructure/Providers/EventServiceProvider.php`
- Routes: `Modules/Sections/routes/web.php` → `Route::middleware(['auth', 'role:...', 'module:{alias}'])->prefix(...)->name('{alias}.')`

## module.json

Full field spec in `stubs/modules/module-md.stub` (copied to each module's `MODULE.md`). Minimum for a working module:

```json
"maturity": "mature",
"dependencies": ["users"],
"permissions": [{"name": "billing.view", "roles": ["staff/admin"]}],
"navigation": [{"label": "Billing", "route": "billing.index", "icon": "Receipt", "permission": "billing.view"}]
```

`icon` is a `lucide-vue-next` name. Role names must already exist (see `database/seeders`).

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

## Access model

A request passes only if: the module is active AND (core OR the school is entitled) AND the user has the route's role or permission. An unavailable module returns 404 (not 403) by design.
