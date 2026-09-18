# Prompt: Resolve Technical Debt Before Feature Development

Copy this prompt into a new agent session from the EDUCATIVO project root.

```text
Continue work on the EDUCATIVO school management system.

Goal: resolve all maintainer-accepted technical debt before starting new product features. Use Spec-Driven Development (SDD). Do not implement feature modules during this change.

Project context:
- Root: C:\Users\DELL 3380\Desktop\EDUCATIVO
- Stack: Laravel 12, PHP 8.3, Vue 3, Inertia, PostgreSQL, Redis, MinIO, Docker Compose.
- Canonical foundation spec: openspec/specs/project-foundation/spec.md
- Debt register: docs/technical-debt/README.md
- Foundation archive: openspec/changes/archive/2026-07-14-school-management-foundation/
- Workspace currently has no Git repository. Do not commit, push, or open a PR unless Git is intentionally initialized and I explicitly approve it.

Required workflow:
1. Read the canonical foundation spec, debt register, archived verification report, and current implementation evidence.
2. Verify every accepted debt against current code before planning work. Do not assume old findings remain valid.
3. Start a new SDD change named `resolve-foundation-technical-debt`.
4. Complete SDD session preflight before any phase. Ask for execution mode, artifact store, PR strategy, and review budget if this session has no cached preflight.
5. Produce proposal, delta specifications, design, and implementation tasks before editing code.
6. Separate work into independently verifiable units:
   - DEBT-001: make the full Pint baseline pass without behavior changes.
   - DEBT-002: make repeated database seeding deterministic and idempotent.
   - DEBT-003: define and implement only explicitly approved production-operations slices. Split observability, backup/restore, deployment supervision, and readiness into separate work units; do not hide their size inside one task.
   - DEBT-004: add idempotent MinIO bucket bootstrap and verify S3 storage with a real upload/read/delete probe that does not expose credentials.
   - DEBT-005: establish a repeatable post-warm-up local latency baseline, identify the root cause, obtain maintainer approval for a reasonable threshold during the SDD proposal, and keep the health endpoint lightweight.
7. Before applying DEBT-003, present scope, operational dependencies, security implications, infrastructure cost, and acceptance criteria. Do not install external services or require paid vendors without approval.
8. Preserve existing architecture and security contracts. Do not weaken tests, remove assertions, suppress audits, add blanket ignores, or mark debt resolved without evidence.
9. Use Docker Compose for PHP, Composer, Artisan, Pest, Pint, and production-like checks. Keep commands reproducible.
10. Run fresh risk, resilience, readability, and reliability reviews for security-sensitive or operational changes.
11. Update docs/technical-debt/README.md only after verification:
    - Change an item to `In progress` when implementation begins.
    - Change it to `Resolved` only when its completion condition passes.
    - Keep evidence links and command results.
    - Do not register new review suggestions automatically; ask for maintainer approval first.
12. Run SDD verification against every requirement and scenario. Archive the change only when no blocking finding remains.

Mandatory completion evidence:
- `vendor/bin/pint --test` passes repository-wide for DEBT-001.
- Running the approved seeding command repeatedly succeeds and produces the same intended records for DEBT-002.
- Each approved DEBT-003 slice has behavior-first tests or operational probes, failure-path evidence, rollback instructions, and an explicit verification result.
- Repeated MinIO bootstrap proves bucket creation is idempotent, and a real upload/read/delete storage probe passes without exposing credentials for DEBT-004.
- Repeatable post-warm-up latency evidence records the baseline, identifies the root cause, verifies the maintainer-approved threshold, and confirms the health endpoint remains lightweight for DEBT-005.
- Composer validation and audit pass.
- Full Pest suite passes.
- Production frontend build passes.
- Clean CI-mode Playwright execution passes.
- Docker services return to a healthy normal state after testing.
- SDD artifacts and the debt register match actual implementation state.

Guardrails:
- Do not start Academic, Schedule, Grades, Files, Admin, Notifications, or other product features yet.
- Do not expand DEBT-003 automatically. Ask before selecting its first production-operations slice.
- Do not treat pre-existing behavior as correct without verification.
- Do not perform destructive database operations against non-development data.
- Do not expose secrets, use predictable privileged credentials, or add insecure development fallbacks.
- Stop and report evidence if a dependency, environment, or scope decision blocks safe progress.

Return after each phase:
- Status
- Executive summary
- Artifacts created or updated
- Verification evidence
- Risks and blockers
- Next recommended phase
```

## Expected outcome

No new feature development begins until accepted debt has either been verified as resolved or explicitly reclassified by the maintainer with documented reasoning.
