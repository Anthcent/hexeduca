# Design: Realtime Tenant Isolation

## Technical Approach
Reverb runs as a separate ReactPHP process with no access to the HTTP kernel, `TenantScope`, or `TenantContext`. The only enforcement point it honors is the `Broadcast::channel()` authorization closure, invoked synchronously over `/broadcasting/auth` (which already runs `ResolveTenant` via the `web` group). So isolation is delivered as a **convention + a tenancy-core helper + a guardrail test**, plus a tenant-aware `api` limiter key. No new package, no middleware for Reverb, no runtime channel registered yet — this is the reusable pattern the 7 future modules copy. Placement mirrors `multi-tenancy-foundation`: broadcasting isolation is cross-cutting tenancy infrastructure, so it lives under `App\Tenancy`, beside `School`/`TenantContext`/`TenantScope`.

## Architecture Decisions

### Decision: Helper is a real class `App\Tenancy\Broadcasting\TenantChannel`, not documentation
**Choice**: `app/Tenancy/Broadcasting/TenantChannel.php` — a `final` class with static builder + authorizer + discipline predicates.
**Alternatives considered**: (a) documented convention only; (b) global helper functions in `helpers.php`.
**Rationale**: A doc-only rule is enforced by reviewer memory — exactly what leaks school 2's data the day someone forgets. A class gives ONE tested implementation the modules call, a stable namespace matching `App\Tenancy\*`, and a home for the allow-list the discipline test reads. Class over loose functions keeps the name-builder, route-pattern builder, auth predicate, and exemption list cohesive and importable.

### Decision: Authorize against `$user->school_id`, never `TenantContext`
**Choice**: predicate first rejects `$user->school_id === null` outright (no cast), then validates `$schoolId` is a well-formed positive integer (`ctype_digit((string) $schoolId)`) before comparing `(int) $user->school_id === (int) $schoolId`; embedded id parsed from the channel.
**Alternatives considered**: compare channel id to `app(TenantContext::class)->current()?->id`; naive `(int) $user->school_id === (int) $schoolId` without an explicit null/numeric guard.
**Rationale**: `TenantContext` is request-scoped and populated on the synchronous auth call, but ABSENT for broadcasts emitted from queued jobs/console. Authorizing against the user's persisted `school_id` is context-independent and cannot silently break when a broadcast is later queued. The helper encodes this so modules cannot pick the wrong predicate. Naive `(int)` casting is unsafe: PHP casts both `null` and any non-numeric string (e.g. `"abc"`) to `0`, so a landlord (`school_id = NULL`) requesting a malformed channel segment like `school.abc.grades.1` would satisfy `(int) null === (int) "abc"` → `0 === 0` → `true`, incorrectly authorizing a landlord onto a tenant channel. The explicit null check plus strict-numeric validation of `$schoolId` closes this hole.

### Decision: `api` limiter keyed by `school:{id}|{user-or-ip}`, `login` untouched
**Choice**: prepend the resolved tenant to the existing user/IP key; null tenant (landlord/unresolved) falls back to the current key unchanged.
**Alternatives considered**: tenant as the SOLE key (one bucket per school regardless of user).
**Rationale**: Combined key means a single user cannot exhaust a whole school's bucket AND one noisy school cannot starve others. Sole-tenant key would let one abusive user throttle every user in their school. `login` runs pre-tenant-auth (`routes/web.php:25`, `throttle:login`) and is already `email|ip` — adding `school_id` there is neither possible nor correct.

### Decision: Reverb scaling stays off, documented flip trigger
**Choice**: `REVERB_SCALING_ENABLED` default `false` (unchanged); `config/reverb.php` already reads `REDIS_*` under `servers.reverb.scaling`. Enabling is env-only, no code change.
**Rationale**: Zero concurrent load today; enabling adds a Redis pub/sub hop and shared-state failure surface for no benefit. Trigger to flip: measured single-process connection saturation or a real >1 Reverb process need — and only after verifying Reverb's `REDIS_DB` does not collide with cache/session logical DBs.

## Channel Naming Convention
Wire name: `private-school.{schoolId}.{resource}.{id}`. The `private-` prefix is implicit — `Broadcast::channel('school.{schoolId}.{resource}.{id}', ...)` registers the base name. `{schoolId}` is numeric `schools.id` and is ALWAYS the first segment after the type prefix, so it is greppable and machine-checkable. `{resource}` is a stable module noun (`grades`, `notifications`, `schedule`); `{id}` is the resource key.

## Data Flow
```
Echo client ──subscribe private-school.7.grades.42──► /broadcasting/auth (web + ResolveTenant)
                                                          │
                                          Broadcast::channel closure
                                                          │
                          TenantChannel::authorize($user, $schoolId=7)
                                                          │
      $user->school_id === null ? deny : (valid numeric $schoolId && (int) $user->school_id === 7) ? allow : deny (403)
```

## File Changes
| File | Action | Description |
|------|--------|-------------|
| `app/Tenancy/Broadcasting/TenantChannel.php` | Create | Name builder, route-pattern builder, auth predicate, exemption allow-list |
| `routes/channels.php` | Modify | Add convention comment + commented reference example using `TenantChannel` (no live business channel) |
| `app/Providers/AppServiceProvider.php` | Modify | Tenant-aware `api` limiter key (see below) |
| `bootstrap/app.php` | Modify | Reorder the `api` middleware group so `ResolveTenant::class` runs BEFORE `'throttle:api'` (currently `[EnsureFrontendRequestsAreStateful::class, 'throttle:api', SubstituteBindings::class, ResolveTenant::class]`); without this the tenant-aware limiter key above always sees a null `TenantContext` and never partitions by school |
| `app/Tenancy/Http/Middleware/ResolveTenant.php` | Modify | Cache the `School`-by-subdomain lookup using a non-null sentinel so BOTH positive and negative (nonexistent/inactive subdomain) lookups are cached: `$school = Cache::remember("tenant:school:{$label}", now()->addMinutes(5), fn () => School::withoutTenantScope()->where('subdomain', $label)->where('is_active', true)->first() ?? false); $school = $school ?: null;` — without the sentinel, `Cache::remember()` treats a cached `null` as a miss (`Illuminate\Cache\Repository::remember()` checks `is_null()`) and re-runs the callback on EVERY request for that label, which fails to mitigate unthrottled DB load from bot/flood scanning of nonexistent subdomains — exactly the reorder-introduced risk this cache was meant to close |
| `app/Tenancy/Observers/SchoolCacheObserver.php` | Create | Model observer on `School` (`saved`/`deleted` events) that calls `Cache::forget("tenant:school:{$school->subdomain}")` to invalidate the cache synchronously when a School's `is_active` or `subdomain` changes; if `subdomain` itself changed, also forgets the old key via `Cache::forget("tenant:school:{$school->getOriginal('subdomain')}")` |
| `app/Tenancy/Models/School.php` | Modify | Add the `#[ObservedBy(SchoolCacheObserver::class)]` PHP attribute (Laravel 12's native attribute-based observer registration) on the `School` class declaration, wiring `SchoolCacheObserver` so Eloquent actually dispatches `saved`/`deleted` events to it — without this attribute the observer class exists but is never invoked |
| `tests/Feature/Broadcasting/TenantChannelTest.php` | Create | Helper + auth predicate behavior tests |
| `tests/Feature/Broadcasting/ChannelRegistryDisciplineTest.php` | Create | Architecture test: `routes/channels.php` holds only allow-listed or `school.`-prefixed channels |
| `tests/Feature/Broadcasting/ApiLimiterTenantPartitionTest.php` | Create | Two-tenant bucket independence + null-tenant fallback |
| `TENANCY.md` | Modify | Channel convention, `TenantContext`-forbidden note, Reverb scaling flip trigger |

## Interfaces / Contracts
```php
namespace App\Tenancy\Broadcasting;

final class TenantChannel
{
    public const PREFIX = 'school';

    /** Channels allowed to be non-tenant-scoped (per-user, not per-tenant). */
    public const ALLOWED_UNSCOPED = ['App.Models.User.{id}'];

    /** Concrete wire base name: "school.7.grades.42" (no private- prefix). */
    public static function name(int|School $school, string $resource, int|string $id): string;

    /** Registration pattern for Broadcast::channel(): "school.{schoolId}.grades.{id}". */
    public static function pattern(string $resource): string;

    /**
     * The ONLY sanctioned authorization predicate.
     * MUST return false immediately when $user->school_id === null (explicit
     * null check, no cast) — never rely on `(int) null === (int) $schoolId`,
     * since PHP casts both null and non-numeric strings to 0. MUST also
     * reject $schoolId that is not a well-formed positive integer (e.g. via
     * ctype_digit((string) $schoolId)) before comparing, so malformed route
     * segments like "abc" cannot coerce to 0 and collide with a null school_id.
     */
    public static function authorize(Authenticatable $user, int|string $schoolId): bool;

    /** True if a channel name is tenant-scoped or explicitly exempt (discipline test). */
    public static function isCompliant(string $channelName): bool;
}
```

`AppServiceProvider::boot()` — `api` limiter, before/after:
```php
// before
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(config('security.rate_limits.api_per_minute'))->by(
        $request->user()?->getAuthIdentifier() ?: $request->ip()
    );
});

// after
RateLimiter::for('api', function (Request $request) {
    $key = $request->user()?->getAuthIdentifier() ?: $request->ip();
    $schoolId = app(TenantContext::class)->current()?->id;

    return Limit::perMinute(config('security.rate_limits.api_per_minute'))->by(
        $schoolId !== null ? "school:{$schoolId}|{$key}" : $key
    );
});
```
`TenantContext` is already app-`scoped` (registered in `register()`), so the per-request closure resolves the correct tenant; landlord/null falls back to `$key` with zero behavior change. Uses an explicit `!== null` check rather than a truthy check, because a truthy check would treat a hypothetical school id of `0` as falsy and silently fall through to the unscoped bucket.

**Critical ordering dependency**: this closure only partitions by tenant if `TenantContext` is already bound when `RateLimiter::for('api')` runs. The currently-implemented `bootstrap/app.php` orders the `api` middleware group as `[EnsureFrontendRequestsAreStateful::class, 'throttle:api', SubstituteBindings::class, ResolveTenant::class]` — `throttle:api` fires BEFORE `ResolveTenant::class` binds the tenant, so `app(TenantContext::class)->current()` is always `null` at rate-limit time and the tenant-aware key is dead code as things stand today. `bootstrap/app.php` MUST be updated (see File Changes) so `ResolveTenant::class` runs before `'throttle:api'`.

Reordering `ResolveTenant::class` ahead of `'throttle:api'` has a cost: `ResolveTenant` performs a `School::withoutTenantScope()->where('subdomain', $label)->where('is_active', true)->first()` query on every non-landlord-host request, and with the new order that query now runs BEFORE any throttling applies — flood/bot traffic scanning subdomains would hit the database unthrottled, a resource-exhaustion/DoS-amplification risk the reorder introduces. To close this gap, `ResolveTenant` MUST cache the School-by-subdomain lookup, key convention `tenant:school:{subdomain}`, 5-minute TTL. Critically, the cached value MUST use a non-null sentinel for the miss case: `Cache::remember()` decides whether to re-invoke its callback based on `is_null($this->get($key))`, so caching a raw `null` for a nonexistent/inactive subdomain is indistinguishable from a cache miss and the callback re-runs on EVERY request for that label — which is exactly the bot/flood-scanning threat model the cache exists to mitigate. The corrected form: `$school = Cache::remember("tenant:school:{$label}", now()->addMinutes(5), fn () => School::withoutTenantScope()->where('subdomain', $label)->where('is_active', true)->first() ?? false); $school = $school ?: null;`. This means BOTH positive (real active tenant) AND negative (nonexistent/inactive subdomain) lookups are now cached, closing the DoS-amplification gap for the actual bot-scanning threat model. The net tradeoff is: reorder for correctness (tenant-aware limiter actually partitions) + cache for cost-safety (the now-earlier query stays cheap under unthrottled scanning, including for scanning traffic that overwhelmingly targets nonexistent subdomains).

Cache invalidation: because negative lookups are now cached, a School's `is_active` flag flipping (deactivation) is no longer guaranteed to become visible within a short window purely by chance — it could remain stale for up to the full 5-minute TTL, which contradicts `ResolveTenant.php`'s own doc comment describing "fail-closed" behavior (an inactive subdomain should 404 immediately, not up to 5 minutes later). To close this, cache invalidation is NOT left to TTL expiry alone: `App\Tenancy\Observers\SchoolCacheObserver` (registered on the `School` model's `saved` and `deleted` events) calls `Cache::forget("tenant:school:{$school->subdomain}")` on every create/update/delete, and additionally forgets the pre-change key via `Cache::forget("tenant:school:{$school->getOriginal('subdomain')}")` when `subdomain` itself was changed. Registration is NOT left implicit: `App\Tenancy\Models\School` carries the `#[ObservedBy(SchoolCacheObserver::class)]` PHP attribute directly on the class declaration (Laravel 12's native attribute-based observer registration), so Eloquent actually dispatches `saved`/`deleted` events to the observer — a class file alone, with no `#[ObservedBy]` attribute and no `School::observe()` call, would never be invoked and the cache-invalidation fix above would be inert at runtime. `ResolveTenant.php`'s doc comment should be read/updated as "fail-closed, with cache invalidated synchronously on School changes via `SchoolCacheObserver`" — an ordinary admin action (deactivating a tenant) now takes effect immediately rather than waiting out the TTL.

**KNOWN LIMITATION — transaction-commit race window (accepted foundation-stage risk):** if a `School` update (e.g. deactivation) runs inside an outer `DB::transaction()`, the `saved` event — and therefore `SchoolCacheObserver`'s `Cache::forget()` call — fires before that transaction commits. A concurrent request that cache-misses in that narrow window re-queries the database under read-committed isolation, observes the pre-change row, and re-caches the stale value for a fresh 5-minute TTL, reopening the staleness window this fix exists to close. This is accepted as a documented foundation-stage risk given no production traffic yet; revisit with `DB::afterCommit()`-based invalidation if this becomes a real operational issue under concurrent admin usage.

Example channel registration (the pattern modules copy — kept commented in `routes/channels.php`, not live):
```php
// use App\Tenancy\Broadcasting\TenantChannel;
// Broadcast::channel(TenantChannel::pattern('grades'), function ($user, $schoolId, $id) {
//     return TenantChannel::authorize($user, $schoolId);
// });
```

## Testing Strategy
| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `name()`/`pattern()` produce exact `school.{id}.{resource}.{id}` shape | Pest assertions on strings |
| Unit | `authorize()` true only on match; false on mismatch, null `school_id` (explicit check, not cast-to-0), non-numeric/malformed `$schoolId` segments (e.g. `"abc"`), string/int coercion of well-formed numeric ids | table of cases, including the landlord-plus-malformed-segment collision case |
| Architecture | Every entry in `routes/channels.php` is `ALLOWED_UNSCOPED` or `school.`-prefixed | Read file source, regex `Broadcast::channel('...')` first arg, assert `isCompliant()`; passes trivially today, fails the build the day a module adds `grades.{id}` unprefixed |
| Integration | Two tenants consume independent `api` buckets; exhausting school 1 does not throttle school 2; null-tenant fallback | mirror `SecurityBaselineTest` `beforeEach` route + `throttle:api`, bind distinct tenants; MUST exercise the real `bootstrap/app.php` `api` middleware group end-to-end (not a test that manually binds `TenantContext` before invoking the closure), so the `ResolveTenant`-before-`throttle:api` ordering is actually proven rather than assumed |

Follow `tests/Feature/SecurityBaselineTest.php` style (Pest, `beforeEach` route registration). The registry test reads `routes/channels.php` contents rather than reflecting Reverb internals — deterministic and framework-version-proof.

## Migration / Rollout
No migration, no data. Additive only. Rollback = revert the limiter closure, revert the `bootstrap/app.php` middleware ordering change, delete `TenantChannel` + the three test files, revert the `routes/channels.php` comment. `REVERB_SCALING_ENABLED=false` is unchanged, nothing to revert there.

## Open Questions
- [ ] None blocking. Confirm `resource` naming registry (grades/notifications/schedule) is documented in TENANCY.md as an informal list, not enforced by the helper.
