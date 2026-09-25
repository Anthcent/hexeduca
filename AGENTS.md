# Agent instructions

## Project skills

Skills live in `.agents/skills/` (read natively by Codex and Antigravity). Claude Code loads them through pointer files in `.claude/skills/`.

| Skill | Trigger | Path |
|---|---|---|
| create-module | Creating, building, or adding a new module | `.agents/skills/create-module/SKILL.md` |

When asked to create a module or finish a skeleton, read `NEW-MODULE.md` first. It sets the context, the reading order, and the brief → build → close workflow. Then read that `SKILL.md` in full before doing anything else, and follow it exactly.
