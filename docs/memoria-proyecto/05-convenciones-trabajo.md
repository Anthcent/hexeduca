# Convenciones de trabajo en este proyecto

Reglas y preferencias que el usuario fijó explícitamente durante las sesiones de EDUCATIVO (además de las reglas globales del `CLAUDE.md` del usuario, que aplican siempre).

## Idioma y sesión

- El usuario trabaja **en español** en este proyecto — las sesiones y resúmenes se registran en español cuando el usuario escribe en español.
- Instrucción recurrente al reanudar sesión: "busca en engram dónde nos quedamos" antes de continuar.

## Modo SDD

- **Persistencia**: inicialmente `sdd init` se configuró en modo **Engram-only** (sin `openspec/`), pero desde `school-management-foundation` en adelante el proyecto pasó a modo **híbrido** (OpenSpec en filesystem + Engram como espejo/memoria). Preferencia confirmada explícitamente: *"User prefers OpenSpec as the artifact store"*.
- **Ejecución automática** (automatic SDD execution) confirmada como preflight preference — no se pausa a pedir confirmación en cada fase salvo que haya una decisión de negocio real pendiente.
- **PR forecasting automático** activado.
- **Presupuesto de revisión**: 1600 líneas cambiadas (`review budget`) — cambios que lo superan disparan recomendación de PRs encadenados.
- **Sin git**: nunca se ejecutan `git`, `commit`, `push` ni se crean PRs reales en este proyecto — el workspace no es un repositorio git.

## Reglas operativas explícitas del usuario

- **No editar código fuente durante revisiones "read-only"** — varias sesiones fueron auditorías explícitamente sin permiso de edición, delegación, git ni migraciones destructivas.
- **No usar seeders/migraciones destructivas** en verificaciones operativas — solo levantar/verificar Docker Compose sin tocar datos.
- **Confirmar antes de cambios de sistema riesgosos**: se pidió y obtuvo confirmación explícita del usuario antes de editar el hosts file de Windows (requiere permisos de administrador).
- **Verificación independiente, no confiar en lo reportado**: en cada `sdd-verify`, se re-ejecutó la suite de tests de forma independiente en vez de confiar en los números de `apply-progress.md` — esto es lo que reveló los 3 bugs reales de `realtime-tenant-isolation` (ver [02-decisiones-tecnicas.md](./02-decisiones-tecnicas.md)).
- **Honestidad sobre deuda técnica**: preferencia repetida por documentar deuda aceptada explícitamente (con causa raíz) en vez de ocultarla o forzar un fix cosmético — ejemplo: DEBT-005 (contención I/O de Docker en Windows) se documentó como "infraestructura, no código" en vez de intentar un parche de código.

## Flujo de revisión

- Reviews R1 (riesgo/seguridad), R2 (legibilidad), R3 (confiabilidad), R4 (resiliencia) se corrieron **fresh / read-only**, sin delegación, antes de dar por buena una remediación correctiva.
- `judgment-day` (revisión dual adversarial) se usó para aprobar al menos un `design.md` (realtime-tenant-isolation) antes de pasar a tasks.

## Patrón recurrente de continuidad entre sesiones

Cada sesión nueva empieza revisando Engram (`mem_context` / `mem_search`) para recuperar el punto exacto donde quedó el trabajo, en vez de asumir estado. El cierre de sesión guarda siempre un `session_summary` con Goal/Instructions/Discoveries/Accomplished/Next Steps/Relevant Files.
