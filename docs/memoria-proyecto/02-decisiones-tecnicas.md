# Decisiones técnicas confirmadas

Cada entrada es una decisión que ya se tomó y está implementada/verificada — no hace falta volver a discutirla salvo que cambien los requisitos.

## Multi-tenancy

- **Shared DB + `school_id` + global scope**, no database-per-tenant. Momento ideal para decidirlo: cuando los módulos de negocio todavía estaban vacíos (retrofit después habría implicado migrar todas las tablas y auditar cada query por fuga entre tenants).
- **Identificación por subdominio**, host de landlord separado (`admin.app.com` / base pelada).
- **Roles globales** (no por-tenant), `users.school_id` nullable como marca de landlord.
- **Provisioning manual** de tenants (no self-service todavía).
- **`App\Tenancy` a nivel app**, no módulo — es infraestructura transversal que vive en el bootstrap, no lógica de negocio.
- **Scope incondicional sin atajo para super-admin** dentro del scope mismo — el bypass siempre es explícito (`withoutTenantScope()`), nunca implícito. Así queda grepeable y testeado.

## Period-scoping académico

- **Triad de período vive dentro de `Modules/Academic`**, no a nivel app — porque `PeriodoAcademico` es un agregado de negocio y ponerlo a nivel app forzaría una dependencia app-core→module hacia atrás.
- **Nunca `withoutGlobalScopes()` a secas** — elimina tenant scope y period scope juntos. Siempre `withoutGlobalScope(<Clase>::class)` puntual.
- **Resolución de período activo es manual** (`is_active` flag), sin auto-switch por fecha — se dejó como feature futura explícita.
- **`MomentoAcademico` es la excepción de la matriz de scoping**: no lleva `school_id` propio, hereda aislamiento transitivamente vía `periodo_academico_id`. No asumir que todas las entidades siguen el mismo patrón tenant+período.
- **Conteo de capacidad de `OfertaAcademica` excluye matrículas no activas**: solo cuenta `status = 'activa'`; retirar una matrícula libera el cupo. (Decisión tomada durante batch 3 de `academic-core-structure` porque quedaba ambigua en la spec original.)

## Canales en tiempo real / broadcasting

- **Autorización de canal contra `$user->school_id`, nunca contra `TenantContext`** — el contexto es request-scoped y está ausente en jobs en cola / broadcasts de consola; la columna persistida es independiente del contexto.
- **Rate limiter combinado** (`school:{id}|{user-or-ip}`), no solo por tenant — evita que un usuario agote el cupo de su escuela Y que una escuela ruidosa ahogue a otras.
- **Reverb scaling se mantiene apagado** por defecto; es un cambio de env, no de código, cuando haga falta.
- **Ventana de carrera en el commit de transacción aceptada como riesgo de etapa de fundación**: si un `School` se actualiza dentro de una transacción, el evento `saved` dispara antes del commit — una request concurrente puede cachear el valor stale por un TTL entero. Documentado, no arreglado (no hay tráfico de producción aún); revisar con `DB::afterCommit()` si se vuelve relevante operacionalmente.

## Acceso y roles en la primera UI

- **Un único rol literal `staff/admin`** (con la barra incluida en el nombre), no dos roles separados `staff` y `admin` unidos por `|` — el seeder solo crea ese rol; usar `role:staff|admin` no habría autorizado a nadie.
- **spatie v6 no auto-registra el alias `role`** — hubo que agregarlo explícitamente en `bootstrap/app.php` (`$middleware->alias([...])`), porque el bootstrap no tenía ningún alias antes de este cambio.
- **Inyección de casos de uso por método/constructor**, nunca `app()` manual dentro de los controladores.
- **Sin período activo es un estado válido, no una excepción** — el controlador calcula `hasActivePeriodo` y lo pasa a Vue; el formulario se deshabilita con mensaje, y el `store()` server-side vuelve a chequear como defensa en profundidad.
- **Estudiantes se filtran por tenant automáticamente** (vía `BelongsToTenant`), docentes se filtran por rol `teacher` — asignar un no-docente como profesor sería un sinsentido de dominio, aunque `teacher_id` queda opcional.
- **Bug crítico corregido**: el seeder de demo debía llamar `TenantContext::forget()` entre invocaciones — el singleton scoped al contenedor persistía entre seeders y filtraba el contexto de tenant al siguiente seeder.

## Deuda técnica resuelta (`resolve-foundation-technical-debt`)

| Deuda | Resolución |
|---|---|
| DEBT-001 | Pint: 30 issues de estilo corregidos, repo-wide clean |
| DEBT-002 | Seeding idempotente vía `firstOrCreate` |
| DEBT-003 | Fuera de alcance, diferida (no tocada) |
| DEBT-004 | Disco S3 funcional, bootstrap idempotente, probe con credenciales sin fuga |
| DEBT-005 | Causa raíz documentada: contención de I/O por bind-mount de Docker en Windows — **mitigación de infraestructura, no de código** |

## Bugs reales encontrados corriendo la suite completa por primera vez en PHP 8.3

1. `App\Tenancy\Models\School` necesitaba override de `newFactory()` — Laravel adivina el namespace del factory por el sub-namespace del modelo (`Database\Factories\Tenancy\Models\SchoolFactory`), pero el archivo real está plano en `database/factories/SchoolFactory.php`. Bug preexistente de `multi-tenancy-foundation`, nunca detectado porque la suite nunca había corrido en PHP 8.3 real.
2. Un test de rate-limiter de login pegaba a una ruta `web` en el host de test por defecto, sin landlord/tenant host — `ResolveTenant` (ya en el grupo `web`) devolvía 404 fail-closed. Se corrigió apuntando al host landlord.
3. Mismo root cause afectaba a 6 tests preexistentes (`ExampleTest`, `FoundationTest`, `SecurityBaselineTest`) que pegaban a `http://localhost`, ni landlord ni subdominio válido. Fix de solo config: `<env name="TENANCY_LANDLORD_HOSTS" value="localhost"/>` en `phpunit.xml`, sin tocar el comportamiento fail-closed de `ResolveTenant`.

**Lección general**: no confiar en que una suite "pasó" si nunca corrió de verdad en el entorno objetivo (PHP local 8.0 vs contenedor 8.3) — siempre re-ejecutar dentro de Docker antes de verificar/archivar.
