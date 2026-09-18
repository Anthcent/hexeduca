# Historial de ciclos SDD

Todos los cambios siguen el ciclo completo: `explore → propose → spec → design → tasks → apply → verify → archive`. Modo híbrido: artefactos en `openspec/` (filesystem) + persistencia espejada en Engram. Todos los cambios listados están **archivados y con verificación PASS**.

## 1. `school-management-foundation` (2026-07-11 → 2026-07-14)

Scaffold inicial del proyecto: Laravel (empezó en 11, se corrigió a 12.64.0 durante remediación), PHP ^8.3, `nwidart/laravel-modules`, 7 módulos con capas Domain/Application/Infrastructure, Sail, PostgreSQL 16, Redis, MinIO, Pest, Horizon, Reverb, Inertia+Vue3+Pinia+Tailwind+Ziggy.

- 34/34 tareas, 15/15 escenarios normativos, 0 CRITICAL/WARNING en verificación final.
- Remediación correctiva incluyó: reemplazar trust de proxy wildcard por CIDRs configurables, eliminar throttling duplicado de API, agregar flujo CSRF/sesión real, pruebas HSTS/cookies, prueba runtime con Playwright, alinear adaptador Inertia server v3 con client v3.
- Deuda no bloqueante preservada: 29 issues de Pint repo-wide (fuera del scope corregido), seeder de `Test User` no idempotente en reseed completo, operaciones de producción diferidas (CI/CD, Sentry/APM, backups, supervisión de Reverb/Horizon en prod).
- Archivado: `openspec/changes/archive/2026-07-14-school-management-foundation/`.

## 2. Auditoría SaaS (2026-09-02, sin ciclo SDD propio — discovery)

Auditoría técnica pedida por el usuario para evaluar si el sistema cumplía requisitos de un SaaS real. Conclusión: **scaffold/fundación sólida pero NO era un SaaS todavía**.

- Gaps críticos: sin multi-tenancy (sin `tenant_id`/`school_id`, sin global scopes/middleware), sin billing (sin Cashier/Stripe, sin tablas de suscripciones).
- Lo que sí estaba bien: seguridad HTTP testeada (`SecurityBaselineTest` cubre CSRF, HSTS, rate limiting, proxies, cookies), Sanctum + Spatie Permission con 4 roles seedeados, Horizon+Reverb configurados.
- Recomendación que se siguió: decidir la estrategia de multi-tenancy (columna + global scope, no DB-per-tenant) **antes** de escribir lógica en Academic/Grades/Schedule, porque los módulos estaban vacíos y era el momento más barato.

## 3. `multi-tenancy-foundation` (2026-09-02)

Convierte el proyecto single-institution en SaaS multi-tenant. Ver [01-arquitectura.md](./01-arquitectura.md) para el diseño técnico completo.

- 30 tareas en 7 fases, forecast de alto riesgo (700-1000 líneas), PRs encadenados recomendados (4 unidades de trabajo).
- Apply completado en batches (tenancy core, etc.).

## 4. `realtime-tenant-isolation` (2026-09-02 → 2026-09-04)

Contraparte en tiempo real (Reverb/broadcasting) del aislamiento HTTP/Eloquent de `multi-tenancy-foundation`.

- 20/20 tareas en 7 fases. Verificación: 42 tests, 135 assertions, 0 fallos.
- Durante la implementación real (no la simulada de `sdd-apply`, que había corrido en PHP 8.0 local sin ejecutar tests) se encontraron y corrigieron 3 bugs reales al correr en Docker/PHP 8.3 — ver [02-decisiones-tecnicas.md](./02-decisiones-tecnicas.md).
- Specs sincronizadas: nueva capability `realtime-tenant-isolation` (3 requisitos, 10 escenarios) + delta en `project-foundation` (rate limiter tenant-aware, 4 escenarios).
- Archivado: `openspec/changes/archive/2026-09-04-realtime-tenant-isolation/`.

## 5. `resolve-foundation-technical-debt` (2026-09-04)

Resuelve 4 de 5 deudas técnicas aceptadas por el mantenedor (DEBT-001, 002, 004, 005; DEBT-003 fuera de alcance). Ver detalle en [02-decisiones-tecnicas.md](./02-decisiones-tecnicas.md).

- 25/25 tareas, verificación PASS (0 CRITICAL, 2 WARNING no bloqueantes, 1 SUGGESTION).
- Archivado: `openspec/changes/archive/2026-09-04-resolve-foundation-technical-debt/`.

## 6. `academic-core-structure` (2026-09-06)

Primer dominio de negocio real: núcleo académico (7 entidades + period-scoping). Ver diseño completo en [01-arquitectura.md](./01-arquitectura.md).

- 49/49 tareas en 10 fases, 88 tests / 231 assertions, verificación PASS (0 CRITICAL/WARNING/SUGGESTION).
- Specs nuevas: `academic-structure` (6 requisitos, 14 escenarios), `period-scoping` (5 requisitos, 16 escenarios).
- Fuera de alcance explícito: Materias, Notas, certificados, promoción, transcripts cross-período, activación automática por fecha, UI/controllers/rutas.
- Archivado: `openspec/changes/archive/2026-09-06-academic-core-structure/`.

## 7. `academic-offering-management-ui` (2026-09-06 → 2026-09-07)

Primeras pantallas visibles del sistema, sobre el backend ya archivado de `academic-core-structure`. Ver diseño en [01-arquitectura.md](./01-arquitectura.md).

- **Corrección importante durante exploración**: el brief de la tarea decía "Inertia + React" — **falso**, se verificó que el stack real es Inertia + **Vue 3**.
- 22/22 tareas en 3 batches (8 fases), 101 tests / 296 assertions (101 = 88 previos + 13 nuevos... nota: el archive-report dice 101 total con 296 assertions, incluye smoke test).
- Corrección crítica de bug: `TenantContext::forget()` faltante en el seeder de demo.
- Spec nueva: `academic-offering-ui` (6 requisitos, 18 escenarios).
- Seeder de demo creado con credenciales reutilizables — ver [04-entorno-local.md](./04-entorno-local.md).
- Archivado: `openspec/changes/archive/2026-09-07-academic-offering-management-ui/`.
- **Última sesión de trabajo real del proyecto** (después de esto solo hubo sesión de verificación de que las pantallas cargaban en el navegador real del usuario — ver [06-estado-actual.md](./06-estado-actual.md)).

## Línea de tiempo resumida

```
2026-07-11 ─ school-management-foundation: explore → tasks
2026-07-12 ─ school-management-foundation: apply batch 4, reviews R1-R4
2026-07-14 ─ school-management-foundation: verify PASS → ARCHIVE
2026-09-02 ─ Auditoría SaaS → multi-tenancy-foundation: explore → tasks/apply
2026-09-02 ─ realtime-tenant-isolation: explore → design (judgment-day aprobado)
2026-09-04 ─ realtime-tenant-isolation: tasks → apply (3 bugs reales) → verify → ARCHIVE
2026-09-04 ─ resolve-foundation-technical-debt: completo → ARCHIVE
2026-09-06 ─ academic-core-structure: ciclo completo → ARCHIVE
2026-09-06/07 ─ academic-offering-management-ui: ciclo completo → ARCHIVE
2026-09-07 ─ Verificación visual en navegador real (hosts file, demo.app.com:8000)
```
