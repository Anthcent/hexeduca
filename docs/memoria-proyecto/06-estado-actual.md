# Estado actual (última sesión registrada: 2026-09-07)

## Dónde quedó el proyecto

Todos los cambios SDD hasta la fecha están **completos, verificados (PASS) y archivados**:

1. `school-management-foundation`
2. `multi-tenancy-foundation`
3. `realtime-tenant-isolation`
4. `resolve-foundation-technical-debt`
5. `academic-core-structure`
6. `academic-offering-management-ui`

La última acción real fue **verificar visualmente** en el navegador real de Windows del usuario (no headless) que las pantallas nuevas (`/academic/ofertas/create`) cargan correctamente:

- Se agregó una entrada permanente al hosts file: `127.0.0.1 demo.app.com`.
- Se abrió `http://demo.app.com:8000/academic/ofertas/create` con el comando `start` de Windows.
- Como no hay pantalla de login real, se autenticó manualmente vía `fetch()` en DevTools (ver [04-entorno-local.md](./04-entorno-local.md)).

El usuario dijo *"recuerda todo esto, continuamos después"* — la sesión quedó pausada sin nuevo trabajo iniciado.

## Decisión abierta (sin resolver)

El próximo paso lógico no fue elegido. Tres opciones sobre la mesa, ofrecidas por el asistente:

1. **Login real** (`GET /login`) — elimina el workaround de DevTools/fetch.
2. **CRUD de catálogos** — pantallas de gestión (niveles, grados, secciones, etc.), ya que hoy solo existen las 2 pantallas de creación de oferta/matrícula.
3. **Módulo de Notas/Materias** — arrancar un nuevo dominio de negocio con su propio ciclo SDD completo.

Ninguna de las tres tiene trabajo empezado todavía.

## Cosas que la próxima sesión puede asumir como ciertas

- El contenedor `app` sigue esperado en el puerto **8000** (no 80) por el conflicto con XAMPP, salvo que XAMPP se haya desinstalado.
- La entrada del hosts file (`demo.app.com` → `127.0.0.1`) sigue vigente.
- Las credenciales demo (`staff@demo.test` / `password`, etc.) siguen sembradas y funcionando.
- El proyecto sigue sin ser un repositorio git.
