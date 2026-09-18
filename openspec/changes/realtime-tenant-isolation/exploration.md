# Exploration: Realtime Tenant Isolation (Reverb/Broadcasting)

## Current State

1. `/broadcasting/auth` IS registered automatically by Laravel 12's `ApplicationBuilder::withRouting(channels: ...)` -> `withBroadcasting()` -> `Broadcast::routes(null)` which defaults to `['middleware' => ['web']]` (vendor/laravel/framework BroadcastManager.php:69-83). No custom BroadcastServiceProvider exists (only AppServiceProvider). bootstrap/app.php appends `ResolveTenant::class` to the `web` group, so ResolveTenant DOES run on `/broadcasting/auth`. No gap.
2. Reverb runs as a fully separate ReactPHP process (vendor/laravel/reverb/src/Servers/Reverb/Http/Server.php) with zero dependency on Laravel's HTTP kernel/middleware — it never sees ResolveTenant. Tenant enforcement must happen entirely in the `Broadcast::channel()` closure during the `/broadcasting/auth` HTTP call (which does go through ResolveTenant); Reverb itself just relays pre-signed channel subscriptions.
3. `RateLimiter::for('login'|'api', ...)` both defined in app/Providers/AppServiceProvider.php boot(), both keyed IP/user only, tenant-agnostic. Making 'api' tenant-aware is trivial: `app(TenantContext::class)->current()?->id` inside the closure resolves per-request correctly (TenantContext is request-scoped singleton, closure body runs per-request not at boot).
4. No existing tenant-scoped channel naming precedent in repo (routes/channels.php only has framework default `App.Models.User.{id}`). Laravel channel names support arbitrary segments like `school.{schoolId}.grades.{id}` natively.
5. Redis already fully provisioned: config/reverb.php scaling section reads standard REDIS_* env vars, config/database.php has a complete redis connection, docker-compose.yml (Sail) already runs a redis service with healthcheck. Enabling REVERB_SCALING_ENABLED=true needs no new infra.

## Recommendation

Channel-name-embedded tenant id approach (`private-school.{schoolId}.{resource}.{id}`, closure validates `$user->school_id === $schoolId`) — mirrors existing closure pattern, no new middleware needed since ResolveTenant already covers /broadcasting/auth via the web group. Avoid TenantContext-implicit validation for channels since TenantContext is request-scoped and won't be bound for any future queued-job broadcast contexts. Pair with tenant-aware `api` rate limiter (key by school_id).

## Risks

- No enforcement mechanism prevents future modules from forgetting the school_id prefix in channel names — recommend an architecture test similar to SecurityBaselineTest.php.
- TenantContext is request-scoped; won't be populated in queued jobs — channel validation should rely on `$user->school_id` comparison, not TenantContext, for robustness.
- `throttle:login` location not confirmed (not in bootstrap/app.php or app/, likely in routes/web.php or auth routes) — unconfirmed, check before touching login throttling.
- Redis scaling config (reverb.php) and cache config (database.php) read REDIS_* env vars independently — verify no unintended DB/instance collision if scaling enabled.

## Open Question for Proposal Scoping

Is Redis-backed Reverb horizontal scaling in scope for this change, or a separate future change? Infra is ready either way.

## Ready for Proposal

Yes.
