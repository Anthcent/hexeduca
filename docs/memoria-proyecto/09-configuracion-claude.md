# Configuración de Claude Code — cómo replicarla en otra máquina/cuenta

Esto documenta la configuración **global** de Claude Code en esta máquina (`C:\Users\DELL 3380\.claude\`), verificada leyendo los archivos reales (no de memoria) el 2026-09-16. Sirve para dejar otra instalación de Claude Code igual a esta.

**Importante**: esto es configuración de **usuario/máquina**, no del proyecto EDUCATIVO — vive en `~/.claude/`, no en el repo. Se replica una sola vez por máquina/cuenta nueva, no por proyecto.

---

## 1. Piezas que componen la configuración

| Pieza | Ubicación | Tipo |
|---|---|---|
| Reglas globales + persona | `~/.claude/CLAUDE.md` | archivo de texto, se copia tal cual |
| Estilo de salida "Gentleman" | `~/.claude/output-styles/gentleman.md` | archivo, se copia tal cual |
| Configuración general | `~/.claude/settings.json` | JSON, se fusiona (ver abajo) |
| Plugin Engram (memoria persistente) | marketplace `engram` (GitHub) | se instala con un comando |
| MCP server `context7` | declarado en `settings.json` | se instala solo (`npx`) |
| MCP server `codebase-memory-mcp` | `~/.claude/.mcp.json` | binario externo, instalación manual aparte |
| Skills personalizados (SDD, diseño, revisión, etc.) | `~/.claude/skills/` | carpeta, se copia tal cual |
| Agentes personalizados (sdd-*, review-*, jd-*) | `~/.claude/agents/` | carpeta, se copia tal cual |
| Slash commands personalizados (`/sdd-*`) | `~/.claude/commands/` | carpeta, se copia tal cual |
| Hooks personalizados (`cbm-*`) | `~/.claude/hooks/` | carpeta + scripts, se copia tal cual |

---

## 2. `CLAUDE.md` global (reglas + persona)

Archivo de ~20 KB en `~/.claude/CLAUDE.md`. Contiene, en este orden:

1. **Reglas generales**: nunca "Co-Authored-By" en commits, usar `bat/rg/fd/sd/eza` en vez de `cat/grep/find/sed/ls`, respuestas cortas por defecto, una sola pregunta a la vez y esperar respuesta, no asumir respuestas, verificar antes de afirmar.
2. **Persona "Gentleman"**: Senior Architect 15+ años, GDE & MVP, mentor apasionado — frustración que viene de querer que el usuario crezca, no de enojo.
3. **Persona Scope (regla crítica)**: la persona rige SOLO la conversación con el usuario. Código, identificadores, comentarios, UI copy, docs técnicas, PRs → **siempre en inglés por defecto**, sin modismos ni forma de hablar del asistente.
4. **Reglas de idioma**: responder en el idioma del último mensaje real del usuario; español rioplatense con voseo cuando corresponde; nunca mezclar idiomas dentro de una misma respuesta.
5. **Protocolo de memoria Engram** (obligatorio, siempre activo): guardar decisiones/bugs/descubrimientos proactivamente con `mem_save`, buscar con `mem_context`/`mem_search` al arrancar o cuando el usuario referencia trabajo previo, cerrar sesión con `mem_session_summary`.
6. **Agent Teams Lite — reglas de orquestación**: cuándo delegar a subagentes vs. hacer inline (reglas de 4 archivos, escritura multi-archivo, PR, incidente, sesión larga), selección de "review lens" (readability/reliability/resilience/risk) según el tipo de cambio.
7. **Referencia al workflow SDD** (lazy-loaded desde `~/.claude/skills/_shared/sdd-orchestrator-workflow.md`).
8. **Reglas de trigger de agentes** (recomendaciones no forzadas: readability en pre-commit/pre-push, 4R completo en pre-PR si toca auth/payments/security o supera 400 líneas, `judgment-day` sugerido post design/apply de SDD).

**Para replicar**: copiar `~/.claude/CLAUDE.md` de esta máquina a la máquina nueva, en la misma ruta. Si el usuario quiere ajustar algo (ej. cambiar el idioma por defecto o la persona), editarlo ahí — es un archivo plano, no requiere ningún comando.

## 3. Output style "Gentleman"

`~/.claude/output-styles/gentleman.md` — define el estilo de respuesta (Core Principle: ayudar primero, no interrogar cada mensaje; personalidad de mentor; contrato de longitud de respuesta corta por defecto; cuándo hacer una sola pregunta y parar). Se activa con `"outputStyle": "Gentleman"` en `settings.json`.

**Para replicar**: copiar el archivo, y en `settings.json` de la máquina nueva agregar `"outputStyle": "Gentleman"`.

---

## 4. `settings.json` — qué copiar/fusionar

Este archivo **no se copia entero** si la máquina nueva ya tiene configuración propia — hay que fusionar las claves relevantes. Contenido real de esta máquina:

```json
{
  "permissions": {
    "deny": [
      "Bash(rm -rf /)", "Bash(sudo rm -rf /)", "Bash(rm -rf ~)", "Bash(sudo rm -rf ~)",
      "Read(.env)", "Read(.env.*)", "Edit(.env)", "Edit(.env.*)",
      "Read(.ssh/*)", "Edit(.ssh/*)",
      "Read(.credentials/*)", "Edit(.credentials/*)",
      "Read(Library/Keychains/*)", "Edit(Library/Keychains/*)",
      "Read(.aws/credentials)", "Edit(.aws/credentials)",
      "Read(.config/gh/hosts.yml)", "Edit(.config/gh/hosts.yml)",
      "Read(**/*.pem)", "Edit(**/*.pem)",
      "Read(**/*.key)", "Edit(**/*.key)",
      "Read(**/secrets/*)", "Edit(**/secrets/*)"
    ],
    "defaultMode": "bypassPermissions"
  },
  "hooks": {
    "PreToolUse": [
      { "matcher": "Grep|Glob", "hooks": [{ "type": "command", "command": "~/.claude/hooks/cbm-code-discovery-gate", "timeout": 5 }] }
    ],
    "SessionStart": [
      { "matcher": "startup", "hooks": [{ "type": "command", "command": "~/.claude/hooks/cbm-session-reminder" }] },
      { "matcher": "resume",  "hooks": [{ "type": "command", "command": "~/.claude/hooks/cbm-session-reminder" }] },
      { "matcher": "clear",   "hooks": [{ "type": "command", "command": "~/.claude/hooks/cbm-session-reminder" }] },
      { "matcher": "compact", "hooks": [{ "type": "command", "command": "~/.claude/hooks/cbm-session-reminder" }] }
    ],
    "UserPromptSubmit": [
      { "matcher": "", "hooks": [{ "type": "command", "command": "gentle-ai skill-registry refresh --quiet --no-gitignore --cwd \"${CLAUDE_PROJECT_DIR:-$PWD}\" || true" }] }
    ]
  },
  "enabledPlugins": { "engram@engram": true },
  "extraKnownMarketplaces": {
    "claude-plugins-official": { "source": { "source": "git", "url": "https://github.com/anthropics/claude-plugins-official.git" } },
    "engram": { "source": { "source": "github", "repo": "Gentleman-Programming/engram" } }
  },
  "outputStyle": "Gentleman",
  "effortLevel": "medium",
  "autoUpdatesChannel": "latest",
  "theme": "dark",
  "mcpServers": {
    "context7": { "command": "npx", "args": ["-y", "--package=@upstash/context7-mcp@2.2.5", "--", "context7-mcp"] }
  },
  "skipDangerousModePermissionPrompt": true
}
```

Notas sobre `defaultMode: bypassPermissions` y `skipDangerousModePermissionPrompt: true`: esta máquina está configurada para **NO pedir confirmación de permisos** en la mayoría de las acciones (modo "yolo" con excepciones explícitas en `deny`). Esto es una elección deliberada del usuario, no el default de Claude Code — replicarlo implica aceptar el mismo nivel de riesgo (herramientas corren sin preguntar, salvo los patterns bloqueados en `deny`).

---

## 5. Plugin Engram (memoria persistente) — cómo se instaló

Fuente: marketplace de GitHub `Gentleman-Programming/engram`, versión instalada `0.1.1`.

**Comandos para replicar en la máquina nueva** (dentro de Claude Code):

```
/plugin marketplace add Gentleman-Programming/engram
/plugin install engram@engram
```

Esto es lo que da acceso a las herramientas `mem_save`, `mem_search`, `mem_context`, `mem_session_summary`, `mem_get_observation`, etc., y lo que hace que el protocolo de memoria del `CLAUDE.md` (sección 5 arriba) funcione. Sin este plugin instalado, las instrucciones de "guardar en Engram" del `CLAUDE.md` no tienen ningún efecto — quedarían como texto muerto.

---

## 6. MCP server `context7`

Se instala solo la primera vez que Claude Code lee el `settings.json` con esa entrada — usa `npx` para bajar `@upstash/context7-mcp` on-demand. **Requisito**: tener Node.js/npx disponible en la máquina nueva. No requiere instalación manual aparte, solo que la entrada esté en `settings.json` (ver sección 4).

## 7. MCP server `codebase-memory-mcp`

```json
{
  "mcpServers": {
    "codebase-memory-mcp": {
      "command": "C:/Users/DELL 3380/AppData/Local/Programs/codebase-memory-mcp/codebase-memory-mcp.exe"
    }
  }
}
```
(`~/.claude/.mcp.json` en esta máquina)

Este **es un binario externo instalado por separado** (no viene con Claude Code ni con el plugin Engram) — da las herramientas `mcp__codebase-memory-mcp__*` (indexar repos, grafo de código, ADRs, etc.). Para replicarlo hay que conseguir/instalar ese ejecutable en la máquina nueva y apuntar la ruta correcta en su `.mcp.json` (la ruta va a ser distinta si el sistema operativo no es Windows).

---

## 8. Skills, agentes, comandos y hooks personalizados

Estas cuatro carpetas **no vienen de ningún plugin ni marketplace** — son contenido personal/artesanal del usuario, viven directamente en `~/.claude/` y se replican copiando la carpeta entera:

- **`~/.claude/skills/`** — skills invocables (`sdd-init`, `sdd-explore`, `sdd-propose`, `sdd-spec`, `sdd-design`, `sdd-tasks`, `sdd-apply`, `sdd-verify`, `sdd-archive`, `sdd-onboard`, `judgment-day`, `skill-registry`, `skill-creator`, `skill-improver`, `work-unit-commits`, `codebase-memory`, más un set grande de skills de diseño frontend: `impeccable`, `design-taste-frontend`, `minimalist-ui`, `industrial-brutalist-ui`, `gpt-taste`, `brandkit`, `imagegen-frontend-web/mobile`, `high-end-visual-design`, `stitch-design-taste`, `emil-design-eng`, `design-motion-principles`, etc.).
- **`~/.claude/agents/`** — subagentes personalizados: `sdd-init`, `sdd-explore`, `sdd-propose`, `sdd-spec`, `sdd-design`, `sdd-tasks`, `sdd-apply`, `sdd-verify`, `sdd-archive`, `sdd-onboard`, `review-readability`, `review-reliability`, `review-resilience`, `review-risk`, `jd-judge-a`, `jd-judge-b`, `jd-fix-agent`.
- **`~/.claude/commands/`** — slash commands: `/sdd-init`, `/sdd-new`, `/sdd-explore`, `/sdd-ff`, `/sdd-continue`, `/sdd-apply`, `/sdd-verify`, `/sdd-archive`, `/sdd-status`, `/sdd-onboard`.
- **`~/.claude/hooks/`** — scripts `cbm-code-discovery-gate` (corre antes de `Grep`/`Glob`) y `cbm-session-reminder` (corre en `SessionStart`: startup/resume/clear/compact). El `UserPromptSubmit` hook en `settings.json` invoca el binario externo `gentle-ai` (`skill-registry refresh`) — ese binario también es una herramienta externa, no parte de Claude Code.

**Para replicar**: copiar las 4 carpetas completas de `~/.claude/` de esta máquina a la máquina nueva. Si `gentle-ai` (el binario del hook de `UserPromptSubmit`) no está instalado en la máquina nueva, ese hook va a fallar silenciosamente (`|| true` lo hace no bloqueante) — instalarlo aparte si se quiere el refresh automático del skill registry.

---

## 9. Checklist de replicación en una máquina/cuenta nueva

1. Instalar Claude Code.
2. Copiar `~/.claude/CLAUDE.md` (sección 2).
3. Copiar `~/.claude/output-styles/gentleman.md` (sección 3).
4. Copiar las carpetas `~/.claude/skills/`, `~/.claude/agents/`, `~/.claude/commands/`, `~/.claude/hooks/` (sección 8).
5. Fusionar (no sobrescribir a ciegas) `~/.claude/settings.json` con las claves de la sección 4 — especialmente `hooks`, `outputStyle`, `mcpServers.context7`, `enabledPlugins`, `extraKnownMarketplaces`.
6. Instalar el plugin Engram: `/plugin marketplace add Gentleman-Programming/engram` → `/plugin install engram@engram` (sección 5).
7. Si se quiere `codebase-memory-mcp`: conseguir/instalar el binario aparte y agregarlo a `.mcp.json` (sección 7).
8. Si se quiere el hook de `UserPromptSubmit` funcionando: instalar el binario `gentle-ai` aparte.
9. Abrir Claude Code en el proyecto EDUCATIVO y confirmar que `mem_context` devuelve las sesiones históricas — eso confirma que Engram quedó bien conectado a este mismo proyecto (Engram identifica el proyecto por el nombre de carpeta, `educativo`, así que basta con abrir Claude Code parado en esta misma carpeta).

## Qué NO hace falta replicar

- **Esta carpeta `docs/memoria-proyecto/`** — es contenido del **repo del proyecto**, no de la config de usuario. Viaja sola con el repo (Git, copia de carpeta, etc.), no depende de nada de esta guía.
- La memoria histórica dentro de Engram en sí (los `mem_save` ya guardados) — esa vive en la base de datos local del plugin Engram en la máquina original, no se "copia"; por eso esta carpeta `docs/memoria-proyecto/` existe: es la forma portable de llevar ese conocimiento a una máquina/IA que no tiene esa base de datos.
