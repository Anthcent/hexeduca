# Entorno local (Windows)

## Docker Compose

- Servicios: `app` (Laravel), PostgreSQL 16, Redis, MinIO (S3-compatible).
- Verificado funcionando: HTTP root y `/up` devuelven 200, Laravel 12.64.0, 7 módulos habilitados.
- Comandos Docker se ejecutan como `sail` en el flujo de trabajo habitual.

## Conflicto de puerto 80 con XAMPP (¡importante, recurrente!)

Esta máquina Windows tiene un **XAMPP standalone instalado** (`httpd.exe`, sin relación con este proyecto) que bindea `0.0.0.0:80` y **gana la carrera** por las requests HTTP a nivel de host contra el mapeo de puerto 80 de Docker — aunque `docker compose ps` muestre el puerto como bindeado correctamente.

- Navegar a `localhost` o al subdominio del tenant en el puerto 80 silenciosamente sirve la propia app PHP de XAMPP (redirige a `/dashboard/`) en vez de Laravel.
- **Workaround usado**: `APP_PORT=8000 docker compose up -d app` — rebindea el contenedor `app` al puerto 8000 del host sin tocar XAMPP ni `.env`.
- Para volver al puerto 80 habría que detener el servicio Apache de XAMPP primero.
- **Asumir para futuras sesiones**: el contenedor de la app está en el puerto **8000**, no 80, mientras XAMPP siga instalado en esta máquina.

## Resolución de subdominios de tenant en Windows

`ResolveTenant` rechaza (`404 fail-closed`) cualquier host que no sea landlord reconocido ni subdominio de tenant válido — `localhost` puro no sirve.

Dos técnicas usadas, según el contexto:

1. **Para pruebas automatizadas/headless (Playwright)**: launch arg de Chromium `--host-resolver-rules="MAP <subdominio> 127.0.0.1"` — no requiere tocar el sistema, pero solo aplica a ese navegador headless.
2. **Para navegar desde el navegador real del usuario**: edición permanente del hosts file de Windows (requiere admin), confirmada explícitamente por el usuario antes de aplicarla:
   ```
   powershell -Command "Add-Content -Path C:\Windows\System32\drivers\etc\hosts -Value '127.0.0.1 demo.app.com'"
   ```
   Esta entrada **ya quedó permanente** en esta máquina — las próximas sesiones pueden asumir que `demo.app.com` resuelve a `127.0.0.1`.

## Cómo abrir la app en el navegador real

```
start "" "http://demo.app.com:8000/academic/ofertas/create"
```
(comando `start` de Windows vía Bash/git-bash)

## Autenticación manual sin pantalla de login

**No existe una pantalla `GET /login`** en la app — solo un endpoint `POST` que devuelve JSON. Para autenticarse manualmente desde el navegador real (sin usar `actingAs` de los tests), hay que loguearse vía `fetch()` en la consola de DevTools usando la técnica de cookie XSRF (misma que se usa para los screenshots de Playwright), y luego recargar la página.

**Pendiente / propuesta no decidida**: construir una pantalla real de login (`GET /login`) para eliminar este workaround.

## Credenciales / datos de demo (seeder `academic-offering-management-ui`)

Escuela demo: subdominio **`demo`** (→ `demo.app.com`).

| Rol | Email | Password |
|---|---|---|
| Staff/Admin | `staff@demo.test` | `password` |
| Teacher | `teacher@demo.test` | `password` |
| Student 1 | `student1@demo.test` | `password` |
| Student 2 | `student2@demo.test` | `password` |

Catálogo sembrado: 1 `NivelAcademico` (Primaria), 2 `Grado` (1ro, 2do), 2 `Sección` (A, B), 1 `PeriodoAcademico` activo (2026-2027).

## Otras notas de entorno

- No hay repositorio git en este workspace — ningún flujo asume `git`, commits, push ni PR.
- Permisos de archivos en 0777 detectados como debt menor en una revisión de resiliencia (no bloqueante).
- Sin CI/CD, sin Dockerfile propio del proyecto (fuera de alcance explícito de la spec de foundation).
