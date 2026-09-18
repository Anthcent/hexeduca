# Multi-Tenancy Conventions

This project is a shared-database, single-schema, subdomain-identified multi-tenant
application. Tenancy is implemented as app-level infrastructure under `App\Tenancy`
(see `openspec/changes/multi-tenancy-foundation/design.md` for the full rationale).

Every module that stores tenant-owned data MUST follow the conventions below.

## 1. The `school_id` column convention

Every table that stores per-tenant (per-school) data MUST have a `school_id` foreign
key column, added in the table's creation migration:

```php
$table->foreignId('school_id')->constrained();
```

Notes:

- Use a **non-nullable** `constrained()` FK for ordinary tenant-owned tables. The
  only table in the system with a **nullable** `school_id` is `users`, because
  `NULL` is the landlord marker (see `design.md`, "Nullable users.school_id as
  landlord marker"). Do not copy the nullable pattern into new tables unless the
  table is explicitly meant to hold landlord-only rows.
- Add an index on `school_id` (either via `constrained()`'s implicit index or an
  explicit `->index()`) — every tenant-scoped query filters on it.

On the model side, add the `App\Tenancy\Concerns\BelongsToTenant` trait:

```php
use App\Tenancy\Concerns\BelongsToTenant;

class Course extends Model
{
    use BelongsToTenant;
}
```

`BelongsToTenant` registers the `TenantScope` global scope (auto-filters every
query by the current tenant) and stamps `school_id` from `TenantContext` on
`creating`. You do not need to set `school_id` manually when creating records
inside a tenant-bound request — the trait does it for you.

## 2. Safe cross-tenant querying (landlord bypass)

The tenant scope is **unconditional and opt-out, never opt-in**. There is no
implicit "if super-admin, skip the scope" branch anywhere in the codebase, on
purpose: an implicit check makes every query silently trust whatever role/context
happens to be active, and a single bug or impersonation edge case would leak every
tenant's data at once.

The **only** sanctioned way to read or write across tenants is the explicit
landlord bypass:

```php
// Explicit, grep-able, individually reviewable and testable.
Course::withoutTenantScope()->get();

// Equivalent, works on any model using BelongsToTenant:
Course::withoutGlobalScope(\App\Tenancy\Scopes\TenantScope::class)->get();
```

Rules:

- Never add a conditional bypass inside `TenantScope::apply()` or inside a trait
  based on the authenticated user's role. Isolation must stay unconditional.
- Cross-tenant reads/writes should be rare, concentrated in clearly-named landlord
  services/controllers (e.g. an admin/platform module), and each call site should
  be easy to find with `rg withoutTenantScope`.
- Code review must treat any new `withoutTenantScope()` / `withoutGlobalScope`
  call as a point requiring explicit justification — why does this operation need
  to cross tenant boundaries?

## 3. Raw-query discipline (no runtime guard — this is a review rule)

Eloquent global scopes, including `TenantScope`, **only apply to Eloquent query
builder calls**. They do **not** apply to:

- `DB::table(...)` / the query builder used outside an Eloquent model
- Raw SQL (`DB::select`, `DB::statement`, etc.)
- Any manual joins/subqueries that don't go through the scoped model

There is intentionally **no runtime guard** that blocks or rewrites raw queries to
add `school_id` filtering — Laravel has no reliable, non-fragile way to do this
generically, and a leaky heuristic guard would be worse than no guard (false
confidence). This means:

- **Code review is the enforcement mechanism.** Any PR introducing `DB::table()`,
  raw SQL, or a manual join on a tenant-owned table must be scrutinized for a
  missing `school_id` filter, exactly as carefully as a missing `WHERE` clause on
  a destructive query.
- If you must use `DB::table()` on a tenant-owned table (e.g. for a bulk
  operation or performance reason), add the `school_id` filter manually and
  comment why Eloquent wasn't used.
- Prefer Eloquent + `BelongsToTenant` for anything that doesn't have a specific,
  documented reason to bypass it.

## 4. Console commands

Artisan commands run outside the HTTP request lifecycle, so no `ResolveTenant`
middleware ever binds a tenant for them. By default, **a console command runs as
landlord** (`TenantContext::hasTenant()` is `false`, every `TenantScope` no-ops,
and unscoped queries return all tenants' rows via Eloquent — because the scope
that would normally restrict them was never bound in the first place).

Convention:

- If a command needs to operate against a single tenant's data, it MUST accept
  an explicit `--school={id|subdomain}` option and bind `TenantContext` itself
  (e.g. `TenantContext::set(School::query()->where(...)->firstOrFail())`) before
  running any tenant-scoped queries.
- Never assume an "ambient" or "current" tenant inside a command. There is none.
- Commands that intentionally operate platform-wide (across all schools) do not
  need `--school` — but should say so clearly in their `$description`.

## 5. Queued jobs

Queued jobs run on a worker process, detached from the HTTP request that
dispatched them — `TenantContext` is **not** carried across the queue
automatically.

Convention:

- Any job that touches tenant-scoped data MUST explicitly capture `school_id`
  (or the `School` model) in its constructor and store it as a public/protected
  property so it survives serialization.
- At the start of `handle()`, the job MUST re-bind the tenant context before
  running any tenant-scoped queries:

  ```php
  public function __construct(private readonly int $schoolId) {}

  public function handle(): void
  {
      TenantContext::set(School::withoutTenantScope()->findOrFail($this->schoolId));

      // ... tenant-scoped work here
  }
  ```

- Never rely on whatever tenant happened to be bound when the job was
  dispatched — that context does not exist on the worker. Jobs that skip this
  re-binding step will run as landlord (unscoped) by default, which is usually
  wrong for tenant-owned work.

## 6. Real-time (Reverb broadcast) channel isolation

Laravel Reverb runs as a separate ReactPHP process with no access to the HTTP
kernel, `TenantScope`, or `TenantContext`. The only enforcement point it
honors is the `Broadcast::channel()` authorization closure, invoked
synchronously over `/broadcasting/auth` (which does run through
`ResolveTenant` via the `web` middleware group). Isolation is therefore
delivered as a naming convention + a tenancy-core helper + a build-time
guardrail test, not middleware.

### Channel naming convention

Every school-scoped private channel MUST follow:

```
private-school.{schoolId}.{resource}.{id}
```

(the `private-` prefix is implicit — `Broadcast::channel('school.{schoolId}.{resource}.{id}', ...)`
registers the base name). `{schoolId}` is the numeric `schools.id` and MUST
always be the first segment after the type prefix, so it is greppable and
machine-checkable. `{resource}` is a stable module noun — today an informal
list (`grades`, `notifications`, `schedule`), not enforced by the helper.
`{id}` is the resource key.

Use `App\Tenancy\Broadcasting\TenantChannel`:

```php
use App\Tenancy\Broadcasting\TenantChannel;

Broadcast::channel(TenantChannel::pattern('grades'), function ($user, $schoolId, $id) {
    return TenantChannel::authorize($user, $schoolId);
});
```

### Authorize against `$user->school_id`, never `TenantContext`

`TenantChannel::authorize()` is the ONLY sanctioned authorization predicate.
It MUST NOT be reimplemented per-module. Channel closures MUST NOT read
`TenantContext`: `TenantContext` is request-scoped and is bound on the
synchronous `/broadcasting/auth` call, but is ABSENT for broadcasts emitted
from queued jobs or console commands. `$user->school_id` is valid in every
execution context, so it is the only safe comparison target.

Every channel registered in `routes/channels.php` is checked by
`tests/Feature/Broadcasting/ChannelRegistryDisciplineTest.php`, which fails
the build the first time a non-exempt channel is registered without the
`school.{schoolId}` tenant segment. The only current exemption is the
framework-default `App.Models.User.{id}` channel (per-user, not per-tenant).

### Reverb Redis horizontal scaling

`REVERB_SCALING_ENABLED` defaults to `false`. The Redis-backed scaling
infrastructure (`config/reverb.php` `servers.reverb.scaling`, driven by the
`REDIS_*` env vars) is already fully provisioned and ready to enable — no
new infra is required to flip it on.

**Flip trigger**: enable scaling only when there is real evidence of a
multi-instance Reverb need (horizontal Reverb scaling, i.e. >1 Reverb
process) or measured single-process connection saturation. There is zero
concurrent-connection load today, so enabling it now would add a Redis
pub/sub hop and a shared-state failure surface for no benefit.

**Required check before flipping**: verify Reverb's `REDIS_DB` (scaling
connection) does not collide with the logical Redis database(s) used for
cache/session — a shared logical DB between Reverb scaling and
cache/session is an instance-collision risk that must be ruled out before
enabling. Flipping the flag is its own small change with its own
verification, not bundled into this foundation.

## References

- `openspec/changes/multi-tenancy-foundation/design.md` — architecture decisions,
  host classification, Sanctum wildcard stateful domains, local dev setup.
- `app/Tenancy/` — `School`, `TenantContext`, `TenantScope`, `BelongsToTenant`,
  `ResolveTenant` middleware.
- `config/tenancy.php` — `base_domain`, `landlord_hosts`.
