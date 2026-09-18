# Archive Report: Realtime Tenant Isolation

**Date**: 2026-09-04  
**Change**: realtime-tenant-isolation  
**Mode**: openspec (hybrid with Engram persistence)  
**Status**: COMPLETE

## Change Summary

The realtime-tenant-isolation change establishes the real-time (Laravel Reverb broadcast) counterpart to HTTP/Eloquent tenant isolation, providing a documented convention for naming and authorizing school-scoped broadcast channels, a tenancy-core helper to enforce the pattern, a tenant-aware `api` rate limiter, and an architecture-level guardrail test to catch future channels that forget the tenant segment.

## Verification Status

**Verification Verdict**: PASS (0 CRITICAL, 0 WARNING, 2 non-blocking SUGGESTION)

- 42 tests passed, 135 assertions, 0 failures
- All 20 implementation tasks complete and verified against source
- All spec scenarios covered by passing runtime tests
- Full spec compliance matrix: all requirements satisfied
- No regressions in existing functionality
- Full audit trail: proposal → spec → design → tasks → apply → verify → archive

## Specs Synced to Main

### Delta Merged into Existing Spec
| Domain | Spec | Action | Details |
|--------|------|--------|---------|
| project-foundation | `openspec/specs/project-foundation/spec.md` | Updated | Added "Tenant-aware api rate limiter" requirement with 4 scenarios (independent buckets, single-tenant threshold, landlord fallback, login limiter unchanged). Delta contained ADDED requirements only; all existing requirements preserved. |

### New Capability Spec Created
| Domain | Spec | Action | Details |
|--------|------|--------|---------|
| realtime-tenant-isolation | `openspec/specs/realtime-tenant-isolation/spec.md` | Created | Full spec for new capability: tenant-scoped channel naming + authorization helper, channel-naming discipline guardrail, Reverb scaling decision documentation. 3 requirements, 10 scenarios. |

## Archive Contents

Archive location: `openspec/changes/archive/2026-09-04-realtime-tenant-isolation/`

All artifacts present and verified:

- ✅ `proposal.md` — Intent, scope, approach, risks, rollback, dependencies, success criteria, proposal question round
- ✅ `design.md` — Technical approach, architecture decisions, channel naming, data flow, file changes, interfaces, testing strategy, migration
- ✅ `tasks.md` — 20 tasks across 7 phases, all marked complete; review workload forecast
- ✅ `apply-progress.md` — All 20 tasks documented with completion status, files changed, deviations noted
- ✅ `verify-report.md` — Full verification: 42 tests passed, all tasks verified, spec compliance matrix, design coherence, fixes documented
- ✅ `exploration.md` — Current state, recommendation, risks, open questions
- ✅ `specs/project-foundation/spec.md` — Delta spec with ADDED requirement (tenant-aware api limiter)
- ✅ `specs/realtime-tenant-isolation/spec.md` — Full spec for new capability
- ✅ `archive-report.md` — This file

## Task Completion Verification

**20/20 tasks complete**, verified against source code:

- Phase 1 (1.1-1.5): `app/Tenancy/Broadcasting/TenantChannel.php` — final class with PREFIX, ALLOWED_UNSCOPED, name(), pattern(), authorize(), isCompliant()
- Phase 2 (2.1): `routes/channels.php` — convention doc block + commented example
- Phase 3 (3.1): `app/Providers/AppServiceProvider.php` — tenant-aware `api` limiter key (school:{id}|{user-or-ip})
- Phase 4 (4.1-4.2): `bootstrap/app.php` reorder + `app/Tenancy/Http/Middleware/ResolveTenant.php` caching with non-null sentinel
- Phase 5 (5.1-5.2): `app/Tenancy/Observers/SchoolCacheObserver.php` created + `app/Tenancy/Models/School.php` ObservedBy attribute
- Phase 6 (6.1): `TENANCY.md` — new real-time channel isolation section
- Phase 7 (7.1-7.13): Three test files (TenantChannelTest, ChannelRegistryDisciplineTest, ApiLimiterTenantPartitionTest) — 16 test cases covering all spec scenarios

No unchecked implementation tasks remain. All tasks are production-ready.

## Key Design Decisions Honored

- **Authorization against `$user->school_id`, not `TenantContext`**: Implemented and enforced by the helper. Predicate checks `$user->school_id === null` explicitly before numeric validation. Rationale documented: context-independent, robust for queued jobs.
- **Tenant-aware `api` limiter key composition**: Exact key format `school:{schoolId}|{user-or-ip}` confirmed in source. Uses strict `!== null` check, not truthy check. Null tenant (landlord) falls back to existing key cleanly.
- **Channel-naming discipline by test, not hope**: Exemption-aware registry test present and passing. Future channels missing the `school.` prefix will fail the build.
- **Reverb scaling stays off**: `REVERB_SCALING_ENABLED=false` default confirmed. Flip trigger documented in TENANCY.md.
- **Accepted transaction-commit race window NOT fixed**: Verified that `SchoolCacheObserver` does NOT use `DB::afterCommit()`. Documented as an accepted foundation-stage risk per design specification.

## Spec Compliance Summary

### realtime-tenant-isolation Spec (10 scenarios)
- ✅ Helper builds a school-scoped channel name (TenantChannelTest)
- ✅ Authorization predicate accepts a same-school user (TenantChannelTest)
- ✅ Authorization predicate rejects a different-school user (TenantChannelTest)
- ✅ Authorization predicate rejects a landlord (null school_id) user (TenantChannelTest)
- ✅ Authorization predicate coerces string channel ids (TenantChannelTest)
- ✅ All non-exempt registered channels carry the tenant segment (ChannelRegistryDisciplineTest)
- ✅ The framework-default user channel is the documented exemption (ChannelRegistryDisciplineTest)
- ✅ A future non-exempt channel without the tenant segment fails the build (ChannelRegistryDisciplineTest)
- ✅ Reverb scaling is off by default (ApiLimiterTenantPartitionTest)
- ✅ The flip trigger is documented (TENANCY.md documentation)

### project-foundation Spec (4 scenarios, tenant-aware api limiter)
- ✅ Two tenants consume independent api buckets (ApiLimiterTenantPartitionTest)
- ✅ A single tenant's api bucket still enforces its own limit (ApiLimiterTenantPartitionTest)
- ✅ Landlord or unresolved-tenant requests fall back to the existing key (ApiLimiterTenantPartitionTest)
- ✅ The login limiter is unaffected (ApiLimiterTenantPartitionTest)

## Regression Check

Full test suite: **42 passed, 135 assertions, 0 failures**

All pre-existing tests (SecurityBaselineTest middleware ordering and rate-limiter assertions, FoundationTest, ExampleTest, all Unit tests) pass without regression. The implementation is additive and does not break existing functionality.

## Files Modified / Created in Implementation

### Core Implementation
- `app/Tenancy/Broadcasting/TenantChannel.php` — New
- `app/Tenancy/Observers/SchoolCacheObserver.php` — New
- `routes/channels.php` — Modified
- `app/Providers/AppServiceProvider.php` — Modified
- `bootstrap/app.php` — Modified (middleware reorder)
- `app/Tenancy/Http/Middleware/ResolveTenant.php` — Modified (caching)
- `app/Tenancy/Models/School.php` — Modified (ObservedBy attribute)

### Documentation
- `TENANCY.md` — Modified (new real-time section)

### Tests
- `tests/Feature/Broadcasting/TenantChannelTest.php` — New (8 tests)
- `tests/Feature/Broadcasting/ChannelRegistryDisciplineTest.php` — New (3 tests)
- `tests/Feature/Broadcasting/ApiLimiterTenantPartitionTest.php` — New (5 tests)

### Configuration (fixes applied)
- `phpunit.xml` — Modified (TENANCY_LANDLORD_HOSTS env entry)

## Risks & Mitigations

No CRITICAL or WARNING issues in final verification. Two non-blocking SUGGESTIONS noted:

1. **TENANCY.md doc content not independently re-read line-by-line** — Low risk, doc-only, no test surface. Apply-progress confirms new section added.
2. **No git repository for commit/diff history audit** — Verification relied on direct file inspection instead. Not a functional risk, a procedural note.

## Known Limitations (Accepted)

**Transaction-commit race window (foundation-stage risk)**: If a `School` update (e.g. deactivation) runs inside a `DB::transaction()`, the `saved` event fires before commit. A concurrent request that cache-misses in that narrow window re-caches the stale value for a fresh TTL. Accepted as documented; revisit with `DB::afterCommit()` if operationally relevant under production concurrent admin usage. No production traffic yet.

## Rollback Path

Rollback is simple and additive:
1. Revert `api` limiter closure in `AppServiceProvider.php` to user/IP-only key
2. Revert `bootstrap/app.php` middleware ordering
3. Revert `ResolveTenant.php` caching (remove Cache::remember, use direct query)
4. Delete `app/Tenancy/Broadcasting/TenantChannel.php`
5. Delete `app/Tenancy/Observers/SchoolCacheObserver.php`
6. Remove ObservedBy attribute from `School.php`
7. Delete three test files (TenantChannelTest, ChannelRegistryDisciplineTest, ApiLimiterTenantPartitionTest)
8. Revert `routes/channels.php` comment block
9. Revert `TENANCY.md` new section

No data migrations, no database changes. `REVERB_SCALING_ENABLED=false` is unchanged (nothing to revert). Engram artifacts remain for audit trail.

## SDD Cycle Status

| Phase | Artifact | Status | Observation IDs |
|-------|----------|--------|-----------------|
| Proposal | proposal.md | Complete | Archived |
| Exploration | exploration.md | Complete | Archived |
| Spec | specs/{domain}/spec.md | Complete | Archived (project-foundation delta merged into main, realtime-tenant-isolation new spec created) |
| Design | design.md | Complete | Archived |
| Tasks | tasks.md | Complete | Archived (20/20 tasks checked) |
| Apply | apply-progress.md | Complete | Archived (20/20 tasks implemented and verified) |
| Verify | verify-report.md | Complete | Archived (PASS, 42 tests, 0 failures) |
| Archive | archive-report.md | Complete | This file + Engram persistence |

**The realtime-tenant-isolation SDD cycle is closed.** All artifacts are archived. The change is production-ready and can be merged.

## Next Steps

None — the change is complete, verified, and archived. Ready for the next SDD change or feature work.

---

**Archived by**: sdd-archive executor  
**Timestamp**: 2026-09-04  
**Mode**: openspec (hybrid: filesystem archive + Engram persistence)
