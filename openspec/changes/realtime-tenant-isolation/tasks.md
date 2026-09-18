# Tasks: Realtime Tenant Isolation

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 320-400 (2 new classes, 3 new test files, 5 modified files, 1 doc) |
| 400-line budget risk | Medium |
| Chained PRs recommended | No |
| Suggested split | Single PR (optional 2-unit split available if reviewer load runs high) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Medium

### Suggested Work Units
| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | `TenantChannel` helper + `routes/channels.php` convention + registry discipline test + helper tests | PR 1 | Independent; no dependency on limiter/caching work |
| 2 | Tenant-aware `api` limiter + `bootstrap/app.php` reorder + `ResolveTenant` caching + `SchoolCacheObserver` + limiter test | PR 2 | Depends on nothing from Unit 1; can ship separately if reviewer load is a concern |

## Phase 1: Tenant-Scoped Broadcasting Helper
- [x] 1.1 Create `app/Tenancy/Broadcasting/TenantChannel.php`: `final class` with `PREFIX = 'school'`, `ALLOWED_UNSCOPED = ['App.Models.User.{id}']`.
- [x] 1.2 Implement `TenantChannel::name(int|School $school, string $resource, int|string $id): string` returning `school.{id}.{resource}.{id}`.
- [x] 1.3 Implement `TenantChannel::pattern(string $resource): string` returning `school.{schoolId}.{resource}.{id}` registration pattern.
- [x] 1.4 Implement `TenantChannel::authorize(Authenticatable $user, int|string $schoolId): bool` — reject `$user->school_id === null` first (no cast), reject non-`ctype_digit` `$schoolId`, then compare `(int) $user->school_id === (int) $schoolId`.
- [x] 1.5 Implement `TenantChannel::isCompliant(string $channelName): bool` — true if `school.`-prefixed or in `ALLOWED_UNSCOPED`.

## Phase 2: Channel Registration Convention
- [x] 2.1 Modify `routes/channels.php`: add convention comment (naming shape, "never authorize against `TenantContext`" rule) plus the commented `TenantChannel::pattern('grades')` reference example — no live business channel.

## Phase 3: Tenant-Aware API Rate Limiter
- [x] 3.1 Modify `app/Providers/AppServiceProvider.php`: change `RateLimiter::for('api', ...)` key to `$schoolId !== null ? "school:{$schoolId}|{$key}" : $key`, resolving `$schoolId = app(TenantContext::class)->current()?->id`. Leave `login` limiter untouched.

## Phase 4: Middleware Ordering & Resolution Caching
- [x] 4.1 Modify `bootstrap/app.php`: reorder the `api` middleware group so `ResolveTenant::class` runs before `'throttle:api'`.
- [x] 4.2 Modify `app/Tenancy/Http/Middleware/ResolveTenant.php`: cache the School-by-subdomain lookup as `Cache::remember("tenant:school:{$label}", now()->addMinutes(5), fn () => ... ?? false)` with a non-null sentinel, then coerce `false` back to `null`, so both positive and negative lookups are cached.

## Phase 5: Cache Invalidation
- [x] 5.1 Create `app/Tenancy/Observers/SchoolCacheObserver.php`: on `saved`/`deleted`, `Cache::forget("tenant:school:{$school->subdomain}")`; if `subdomain` changed, also forget the original-subdomain key.
- [x] 5.2 Modify `app/Tenancy/Models/School.php`: add `#[ObservedBy(SchoolCacheObserver::class)]` attribute to the class declaration.

## Phase 6: Documentation & Scaling Decision
- [x] 6.1 Modify `TENANCY.md`: document the channel-naming convention, the "authorize against `$user->school_id`, never `TenantContext`" rule, `REVERB_SCALING_ENABLED=false` default, the flip trigger (multi-instance need or measured connection saturation), and the required Redis logical-DB collision check before enabling.

## Phase 7: Tests (map every spec scenario)
- [x] 7.1 `tests/Feature/Broadcasting/TenantChannelTest.php` — `name()`/`pattern()` produce `school.{id}.{resource}.{id}` (spec: "Helper builds a school-scoped channel name").
- [x] 7.2 Same file — `authorize()` returns `true` for a same-school user (spec: "Authorization predicate accepts a same-school user").
- [x] 7.3 Same file — `authorize()` returns `false` for a different-school user (spec: "Authorization predicate rejects a different-school user").
- [x] 7.4 Same file — `authorize()` returns `false` for a landlord user against both well-formed and malformed `$schoolId` segments (spec: "Authorization predicate rejects a landlord (null school_id) user").
- [x] 7.5 Same file — `authorize()` coerces string channel ids (spec: "Authorization predicate coerces string channel ids").
- [x] 7.6 `tests/Feature/Broadcasting/ChannelRegistryDisciplineTest.php` — every non-exempt registered channel is `school.`-prefixed (spec: "All non-exempt registered channels carry the tenant segment").
- [x] 7.7 Same file — `App.Models.User.{id}` is allow-listed and does not fail (spec: "The framework-default user channel is the documented exemption").
- [x] 7.8 Same file — a hypothetical unprefixed, non-allow-listed channel fails the test (spec: "A future non-exempt channel without the tenant segment fails the build").
- [x] 7.9 `tests/Feature/Broadcasting/ApiLimiterTenantPartitionTest.php` — two tenants consume independent `api` buckets (spec: "Two tenants consume independent api buckets"), exercised through the real `bootstrap/app.php` middleware group.
- [x] 7.10 Same file — a single tenant's bucket still enforces its own threshold and returns 429 (spec: "A single tenant's api bucket still enforces its own limit").
- [x] 7.11 Same file — null-tenant request falls back to the existing user/IP key (spec: "Landlord or unresolved-tenant requests fall back to the existing key").
- [x] 7.12 Same file — `login` limiter key/behavior is unchanged (spec: "The login limiter is unaffected").
- [x] 7.13 Assert `config('reverb.servers.reverb.scaling.enabled')` is `false` by default (spec: "Reverb scaling is off by default"). Note: the actual config path differs from the spec's placeholder `reverb.apps.apps.0.options.scaling.enabled`, which does not exist in `config/reverb.php` — scaling lives under `servers.reverb.scaling`, not per-app `options`; tested against the real path.
