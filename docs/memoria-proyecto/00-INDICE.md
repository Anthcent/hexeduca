# Memoria del proyecto EDUCATIVO

Este directorio es un volcado organizado de **todo** lo que hay guardado en la memoria persistente (Engram) sobre este proyecto: arquitectura, decisiones técnicas, historial de cambios (SDD), entorno local y convenciones de trabajo acordadas con el usuario.

Generado el 2026-09-14 a partir de ~50 observaciones de Engram (proyecto `educativo`, sesiones desde 2026-07-11 hasta 2026-09-07).

## Archivos

1. [01-arquitectura.md](./01-arquitectura.md) — Stack técnico, estructura de módulos, multi-tenancy, period-scoping, capas hexagonales.
2. [02-decisiones-tecnicas.md](./02-decisiones-tecnicas.md) — Decisiones de diseño confirmadas, con el "por qué" de cada una.
3. [03-historial-sdd.md](./03-historial-sdd.md) — Línea de tiempo de los 6 ciclos SDD completados (proposal → archive).
4. [04-entorno-local.md](./04-entorno-local.md) — Docker Compose, puertos, conflicto con XAMPP, hosts file, credenciales demo.
5. [05-convenciones-trabajo.md](./05-convenciones-trabajo.md) — Cómo se trabaja en este proyecto: idioma, modo SDD, reglas que el usuario fijó explícitamente.
6. [06-estado-actual.md](./06-estado-actual.md) — Dónde quedó el proyecto la última vez y qué decisión está pendiente.
7. [07-como-crear-un-modulo.md](./07-como-crear-un-modulo.md) — Cómo se implementa un módulo nuevo paso a paso: estructura, rutas, middleware, seguridad multi-tenant, con diagramas.
8. [08-prompt-continuidad.md](./08-prompt-continuidad.md) — Prompt listo para copiar y pegar a otra IA para que arranque con todo este contexto.
9. [09-configuracion-claude.md](./09-configuracion-claude.md) — Cómo está configurado Claude Code en esta máquina (CLAUDE.md, Engram, skills, agentes, hooks, MCP) y cómo replicarlo en otra instalación.

## Resumen de una línea

EDUCATIVO es un SaaS de gestión escolar multi-tenant construido en **Laravel 12 + PHP 8.3**, arquitectura modular hexagonal (`nwidart/laravel-modules`), frontend **Inertia + Vue 3**, corriendo en Docker Compose (PostgreSQL, Redis, MinIO). Se desarrolla con un flujo **SDD (Spec-Driven Development)** en modo híbrido (OpenSpec en filesystem + Engram como memoria persistente), trabajando en español con el usuario.

Estado: foundation + multi-tenancy + realtime tenant isolation + núcleo académico (7 entidades) + primeras 2 pantallas UI, todo **archivado y verificado (PASS)**. Próximo paso sin decidir aún: login real, CRUD de catálogos, o módulo de Notas/Materias.
