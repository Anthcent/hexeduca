# Apply Progress: Realtime Tenant Isolation

## Mode
Standard (no strict TDD certified for this project — tests written alongside implementation).

## Batch
Batch 1 (single batch — all 7 phases, 20 tasks implemented in one PR per the tasks.md forecast: 320-400 lines, Medium risk, no chaining).

## Completed Tasks (20/20)

### Phase 1: Tenant-Scoped Broadcasting Helper
- [x] 1.1 `app/Tenancy/Broadcasting/TenantChannel.php` created (`final class`, `PREFIX`, `ALLOWED_UNSCOPED`).
- [x] 1.2 `TenantChannel::name()` implemented.
- [x] 1.3 `TenantChannel::pattern()` implemented.
- [x] 1.4 `TenantChannel::authorize()` implemented (null check → ctype_digit check → int comparison).
- [x] 1.5 `TenantChannel::isCompliant()` implemented (allow-list + `school.` prefix, both concrete and `{schoolId}` pattern forms).

### Phase 2: Channel Registration Convention
- [x] 2.1 `routes/channels.php` updated with convention doc block + commented `TenantChannel::pattern('grades')` example.

### Phase 3: Tenant-Aware API Rate Limiter
- [x] 3.1 `app/Providers/AppServiceProvider.php` `api` limiter now keys by `school:{schoolId}|{key}` when a tenant is resolved, falling back to `{key}` otherwise. `login` limiter untouched.

### Phase 4: Middleware Ordering & Resolution Caching
- [x] 4.1 `bootstrap/app.php` `api` group reordered to `[EnsureFrontendRequestsAreStateful, ResolveTenant, throttle:api, SubstituteBindings]` — `ResolveTenant` now runs before `throttle:api`. Relative order of `stateful < throttle:api < SubstituteBindings` preserved (existing `SecurityBaselineTest` ordering assertion unaffected).
- [x] 4.2 `app/Tenancy/Http/Middleware/ResolveTenant.php` caches the School-by-subdomain lookup via `Cache::remember("tenant:school:{$label}", 5 min, ... ?? false)` with the `false` sentinel, coerced back to `null` after retrieval, so both positive and negative lookups are cached.

### Phase 5: Cache Invalidation
- [x] 5.1 `app/Tenancy/Observers/SchoolCacheObserver.php` created — `saved()` forgets the current (and, if changed, original) subdomain cache key; `deleted()` forgets the current key. Documented the accepted transaction-commit race window exactly as specified in design.md — not "fixed".
- [x] 5.2 `app/Tenancy/Models/School.php` carries `#[ObservedBy(SchoolCacheObserver::class)]`.

### Phase 6: Documentation & Scaling Decision
- [x] 6.1 `TENANCY.md` — new "§6 Real-time (Reverb broadcast) channel isolation" section: naming convention, `$user->school_id`-only authorization rule, Reverb scaling default + flip trigger + Redis DB-collision check.

### Phase 7: Tests
- [x] 7.1–7.5, 7.8(partial identity test) `tests/Feature/Broadcasting/TenantChannelTest.php` — `name()`/`pattern()` shape, `authorize()` match/mismatch/landlord-null/malformed/string-coercion cases (8 test cases, including an extra malformed-vs-tenant-user case beyond the minimum).
- [x] 7.6–7.8 `tests/Feature/Broadcasting/ChannelRegistryDisciplineTest.php` — reads `routes/channels.php` source via regex (not Reverb reflection), asserts every registered channel is `isCompliant()`; asserts the default exemption passes; asserts a hypothetical `grades.{id}` fails `isCompliant()` directly (no live registration needed to prove the guardrail logic).
- [x] 7.9–7.13 `tests/Feature/Broadcasting/ApiLimiterTenantPartitionTest.php` — two-tenant bucket independence, single-tenant threshold enforcement, landlord/null-tenant fallback (via two distinct `REMOTE_ADDR` values on the landlord host), login-limiter non-interference, and Reverb scaling-off-by-default. All four rate-limiter tests exercise the real `bootstrap/app.php` `api` middleware group end-to-end via full-URL host-based requests (`http://{subdomain}.{base_domain}/...`), not a hand-wired `TenantContext` bind — per the design's explicit requirement that the `ResolveTenant`-before-`throttle:api` ordering be proven, not assumed.

## Files Changed

| File | Action | What Was Done |
|------|--------|----------------|
| `app/Tenancy/Broadcasting/TenantChannel.php` | Created | Name builder, pattern builder, authorization predicate, discipline predicate |
| `routes/channels.php` | Modified | Convention doc comment + commented example |
| `app/Providers/AppServiceProvider.php` | Modified | Tenant-aware `api` limiter key |
| `bootstrap/app.php` | Modified | `api` middleware group reordered |
| `app/Tenancy/Http/Middleware/ResolveTenant.php` | Modified | Cached School-by-subdomain lookup with non-null sentinel |
| `app/Tenancy/Observers/SchoolCacheObserver.php` | Created | Cache invalidation on `School` `saved`/`deleted` |
| `app/Tenancy/Models/School.php` | Modified | `#[ObservedBy(SchoolCacheObserver::class)]` attribute |
| `TENANCY.md` | Modified | New realtime channel isolation section |
| `tests/Feature/Broadcasting/TenantChannelTest.php` | Created | Helper + authorization predicate tests |
| `tests/Feature/Broadcasting/ChannelRegistryDisciplineTest.php` | Created | Channel registry discipline architecture test |
| `tests/Feature/Broadcasting/ApiLimiterTenantPartitionTest.php` | Created | Tenant-aware limiter partition + fallback + Reverb-scaling-default tests |
| `openspec/changes/realtime-tenant-isolation/tasks.md` | Modified | All 20 tasks marked `[x]` |

## Deviations from Design
- **Task 7.13 config path corrected.** The spec/tasks placeholder path `config('reverb.apps.apps.0.options.scaling.enabled')` does not exist in the actual `config/reverb.php` — the per-app `options` array only holds `host`/`port`/`scheme`/`useTLS`; Reverb's scaling config lives at `servers.reverb.scaling.enabled`. The spec itself flagged this path as "or the equivalent" — implemented against the real, verified path (`config('reverb.servers.reverb.scaling.enabled')`).
- No other deviations. `bootstrap/app.php` middleware placement, `ResolveTenant` caching, `SchoolCacheObserver`, and the accepted transaction-commit race window were implemented exactly as designed — the documented race was **not** "fixed" (no `DB::afterCommit()` was introduced), per explicit instruction.

## Issues Found / Environment Constraint (IMPORTANT)
This sandboxed Windows environment has **no usable PHP 8.3 runtime**: the only `php` on `PATH` is XAMPP's PHP 8.0.30, and the project's Composer dependencies (Laravel 12) hard-require PHP ">= 8.3.0" (`vendor/composer/platform_check.php` aborts `artisan`/`phpunit`/`pest` immediately). Docker Desktop is installed but its daemon is not running (`docker ps` fails to connect to the named pipe), and no WSL bash distro is available (`wsl -e bash` fails). As a result:
- **The test suite could not be executed in this session.** `vendor/bin/pest` / `vendor/bin/phpunit` / `php artisan test` all fail at the Composer platform-check step before any test runs.
- All new and modified PHP files were verified with `php -l` (PHP 8.0.30 lint — attribute syntax `#[...]` and match/enum-free code used here all lint clean under 8.0), confirming no syntax errors, but this does **not** confirm runtime/behavioral correctness (no DB, no Eloquent boot, no Pest assertions were exercised).
- **This is a hard blocker for full verification.** `sdd-verify` (or the next apply session) MUST run on a host with PHP 8.3+ available (e.g. `vendor/bin/pest` inside the project's actual dev environment, Sail container once the Docker daemon is started, or a CI runner) before this change can be considered verified. I did not fabricate pass/fail counts — no tests were run.

## Remaining Tasks
None — all 20 tasks across all 7 phases are implemented and marked `[x]` in `tasks.md`.

## Workload / PR Boundary
- Mode: single PR (per `Review Workload Forecast`, `400-line budget risk: Medium`, `Chained PRs recommended: No`, `Chain strategy: pending` — no chain needed).
- Current work unit: N/A (single PR covers both Suggested Work Units 1 and 2 from tasks.md, per orchestrator instruction to implement all 20 tasks in this batch).
- Boundary: this apply batch starts from zero prior progress and ends with all 7 phases complete.
- Estimated review budget impact: within the 320-400 line forecast; no split needed.

## Status
20/20 tasks complete. Blocked only on test execution (environment lacks PHP 8.3 / running Docker) — implementation is complete and lint-clean. Recommend `sdd-verify` be run in an environment with a working PHP 8.3 + Pest toolchain to execute `tests/Feature/Broadcasting/*.php` and confirm green before merge.
