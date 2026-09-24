---
name: create-module
description: "Trigger: new module, create module, build module, add module, crear módulo, nuevo módulo. Build and plug a new hexeduca module end to end."
license: Apache-2.0
metadata:
  author: "Anthony G"
  version: "1.0"
---

## Activation Contract

Load when the user asks to create, build, scaffold, or add a new hexeduca module.

## Hard Rules

- The canonical skill lives in `.agents/skills/create-module/`, shared with Codex and Antigravity. Read `.agents/skills/create-module/SKILL.md` in full before any other action, and follow it exactly.
- Resolve its `references/` and `assets/` paths relative to `.agents/skills/create-module/`.
- Never edit this pointer file to change behavior. Edit the canonical skill instead.

## Execution Steps

1. Read `.agents/skills/create-module/SKILL.md`.
2. Execute it.

## Output Contract

Return the output contract defined by the canonical skill.

## References

- `.agents/skills/create-module/SKILL.md` — canonical skill.
