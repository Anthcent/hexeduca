# Prompt de continuidad para otra IA

Copiá y pegá el bloque de abajo como primer mensaje al iniciar una sesión nueva con otra IA (u otra sesión de Claude Code sin memoria previa) para que arranque con el contexto completo del proyecto EDUCATIVO.

---

```
Vas a continuar el desarrollo del proyecto EDUCATIVO, un SaaS de gestión escolar
multi-tenant (Laravel 12 + PHP 8.3, arquitectura modular hexagonal con
nwidart/laravel-modules, frontend Inertia + Vue 3, Docker Compose con
PostgreSQL/Redis/MinIO). Se trabaja en español y con metodología SDD
(Spec-Driven Development).

Antes de responder o proponer nada, leé TODO el contenido de la carpeta
docs/memoria-proyecto/ en este repositorio, en este orden:

1. 00-INDICE.md               — resumen general
2. 01-arquitectura.md         — stack, módulos, multi-tenancy, period-scoping
3. 02-decisiones-tecnicas.md  — decisiones ya tomadas y su porqué (no las
                                 vuelvas a discutir salvo que cambien los
                                 requisitos)
4. 03-historial-sdd.md        — los cambios SDD ya completados y archivados
5. 04-entorno-local.md        — Docker, conflicto de puerto con XAMPP,
                                 hosts file, credenciales demo
6. 05-convenciones-trabajo.md — cómo trabajamos: idioma, modo SDD híbrido,
                                 reglas explícitas del usuario
7. 06-estado-actual.md        — dónde quedó el proyecto la última vez y la
                                 decisión pendiente
8. 07-como-crear-un-modulo.md — patrón real (con diagramas) para implementar
                                 un módulo nuevo: rutas, middleware, seguridad
                                 multi-tenant, relaciones

Estos archivos son la memoria persistente del proyecto (originalmente en
Engram) volcada a texto plano. Tratalos como fuente de verdad sobre decisiones
ya tomadas, arquitectura existente y convenciones de trabajo — no las
reinventes ni las cuestiones sin evidencia nueva.

Una vez leído todo, confirmame en 3-4 líneas que entendiste el estado actual
del proyecto y cuál es la decisión pendiente descrita en 06-estado-actual.md,
y esperá mi instrucción sobre qué hacer a continuación.
```

---

## Notas de uso

- Si la carpeta `docs/memoria-proyecto/` quedó desactualizada (nuevos cambios SDD, nuevas decisiones), actualizala antes de reutilizar este prompt — es un volcado estático, no se sincroniza sola con Engram.
- Si la IA destino no tiene acceso al filesystem del proyecto (por ejemplo, un chat sin herramientas), pegale el contenido de los `.md` directamente en el mensaje en vez de pedirle que los lea.
- Este prompt asume que la otra IA va a operar en este mismo repositorio (`C:\Users\DELL 3380\Desktop\EDUCATIVO`). Si es una sesión de puro research/consulta sin acceso al código, aclaraselo.
