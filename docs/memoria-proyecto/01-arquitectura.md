# Arquitectura

## Stack técnico

- **Backend**: Laravel 12.64.0 (subió desde 11.54.0 por advisories de seguridad), PHP ^8.3.
- **Modularidad**: `nwidart/laravel-modules` ^12.0 — monolito modular con 7 módulos: `Users`, `Academic`, `Schedule`, `Grades`, `Files`, `Admin`, `Notifications`.
- **Frontend**: Inertia + **Vue 3** (`@inertiajs/vue3` ^3.6.1, `vue` ^3.5.39) — **no es React**, se corrigió esa suposición errónea durante la exploración de `academic-offering-management-ui`. Pinia, Tailwind, Ziggy.
- **Testing**: Pest ^3.8 (`pestphp/pest-plugin-laravel`).
- **Infra async**: Horizon + Reverb (websockets/broadcasting) configurados.
- **Auth/permisos**: Sanctum + Spatie Permission (roles: `student`, `teacher`, `staff/admin` como un único rol literal, `super-admin`).
- **Contenedores**: Docker Compose — servicios `app`, PostgreSQL 16, Redis, MinIO (S3-compatible).
- **Sin git**: el workspace **no es un repositorio git** (confirmado repetidamente). Ningún flujo de trabajo asume commits/PR.
- **Sin CI/CD**: fuera de alcance explícito de la spec del proyecto.

## Estructura de capas (hexagonal / DDD-lite)

Cada módulo de negocio sigue:

```
Modules/<Nombre>/
  Domain/{Entities,ValueObjects,Repositories,Events}
  Application/{UseCases,DTOs}
  Infrastructure/{Models,Persistence,Database/Migrations,...}
```

Los repositorios se bindean en `<Modulo>ServiceProvider::register()`. Solo `Modules/Users` tenía lógica real al inicio; el resto eran skeletons vacíos (`.gitkeep`).

## Multi-tenancy (cambio `multi-tenancy-foundation`)

- **Estrategia**: shared-DB, tenant identificado por **subdominio**, columna `school_id` + Eloquent global scope. Se descartó database-per-tenant.
- **Ubicación**: namespace app-level `App\Tenancy` (NO un módulo `Modules/Tenancy`, NO dentro de `Modules/Admin`) — porque tenancy es infraestructura transversal que ya vive en `bootstrap/app.php`.
  - `Models/School`, `Scopes/TenantScope`, `Concerns/BelongsToTenant`, `TenantContext` (singleton scoped a la request), `Http/Middleware/ResolveTenant`, `config/tenancy.php`.
- **Global scope incondicional**: no hay salto implícito para super-admin dentro del scope. El landlord cruza solo con `School::withoutTenantScope()` → `withoutGlobalScope(TenantScope::class)` explícito, grepeable y testeado.
- **`ResolveTenant` clasifica el host en 3 clases**:
  1. tenant (`<label>.base`, activo) → bindea `TenantContext`.
  2. landlord (`admin.base` / base pelado / `APP_LANDLORD_HOSTS`) → no bindea.
  3. no reconocido → **404 fail-closed**.
- **Sanctum**: no necesita override de `EnsureFrontendRequestsAreStateful`; `'stateful'` ya soporta wildcards `Str::is()`. Se agregó `'*.app.com'` + `SESSION_DOMAIN=.app.com`.
- **Windows dev**: sin wildcard en hosts file — hay que agregar entradas explícitas por subdominio (p. ej. `demo.app.com`).
- **users.school_id es nullable**: `NULL` = landlord/super-admin.

## Period-scoping (cambio `academic-core-structure`)

Clona la misma idea que `App\Tenancy` pero **dentro del módulo Academic** (no a nivel app), porque `PeriodoAcademico` es un agregado de negocio, no infraestructura cross-cutting.

- Ubicación: `Modules/Academic/Infrastructure/Period/` — `PeriodoContext` (scoped singleton, bindeado en `AcademicServiceProvider::register()`), `Scopes/PeriodoScope`, `Concerns/BelongsToActivePeriodo`, `Broadcasting/PeriodoChannel`.
- **Dos global scopes independientes y con keys distintas** (`TenantScope::class`, `PeriodoScope::class`) — nunca usar `withoutGlobalScopes()` a secas porque elimina AMBOS; siempre `withoutGlobalScope(<Clase>::class)`.
- **Matriz de scoping en 3 niveles**:
  | Entidad | Scoping |
  |---|---|
  | NivelAcademico, Grado, Sección, PeriodoAcademico | solo tenant |
  | MomentoAcademico | **global-dentro-del-período** (sin `school_id` propio; aislamiento heredado transitivamente vía `periodo_academico_id`) |
  | OfertaAcademica, Matricula | tenant + período |
- **Regla de oro para jobs/broadcast**: nunca usar el contexto request-scoped (`TenantContext`/`PeriodoContext`) dentro de colas o canales — siempre las columnas persistidas (`school_id`, `periodo_academico_id`) como fuente de verdad. Lección aprendida con `TenantChannel` y replicada en `PeriodoChannel`.

## Canales en tiempo real (cambio `realtime-tenant-isolation`)

- Reverb no tiene visibilidad del kernel HTTP ni de `TenantScope`, así que el aislamiento se logra con una **convención de nombres + helper + tests de guardrail**, no con middleware de Reverb.
- `App\Tenancy\Broadcasting\TenantChannel`: `PREFIX='school'`, nombre de canal `school.{schoolId}.{resource}.{id}`, `authorize()` compara contra `$user->school_id` (nunca `TenantContext`, porque en jobs en cola no existe).
- Rate limiter `api` ahora es tenant-aware: `school:{schoolId}|{user-or-ip}` — dos tenants no comparten cupo; un tenant ruidoso no ahoga a otro. El limiter de `login` queda sin cambios.
- `ChannelRegistryDisciplineTest` lee `routes/channels.php` por regex y falla el build si un canal futuro no lleva el segmento de tenant.
- Reverb scaling (`REVERB_SCALING_ENABLED`) permanece en `false` por defecto; flip documentado en `TENANCY.md`.

## Módulo académico (cambio `academic-core-structure`)

7 entidades en `Modules/Academic`: `NivelAcademico`, `Año/Grado`, `Sección`, `PeriodoAcademico`, `MomentoAcademico`, `OfertaAcademica`, `Matricula`. 2 casos de uso: `CreateOfertaAcademica`, `MatricularEstudiante`. 88 tests / 231 assertions al momento de archivar.

Explícitamente **fuera de alcance** (para módulos futuros): Materias, Notas, resúmenes finales, certificados, flujos de promoción, acceso cross-período (transcripts), activación automática de período por fecha, UI/controllers/rutas (eso llegó en el siguiente cambio).

## Primera UI (cambio `academic-offering-management-ui`)

Primeras pantallas visibles del sistema: `OfertaCreate.vue` y `MatriculaCreate.vue` (Inertia + Vue 3), controladores finos que solo validan/leen contexto/invocan casos de uso. Gate de acceso: `auth` + `role:staff/admin` (rol único literal, no dos roles separados por `|`). 101 tests / 296 assertions al archivar.

Fuera de alcance: pantallas CRUD de catálogos, dashboard combinado, listados/index, Notas/Materias, permisos granulares de spatie, activación automática de período, acceso cross-período, verificación de disponibilidad docente, matrícula masiva, **pantalla real de login**.
