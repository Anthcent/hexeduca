# Spec: Realtime Tenant Isolation

## Capability: realtime-tenant-isolation

### Purpose
Establish the real-time (Laravel Reverb broadcast) counterpart to the HTTP/Eloquent tenant isolation already provided by `multi-tenancy-foundation`: a documented, helper-backed convention for naming and authorizing school-scoped broadcast channels, and a build-time guardrail that catches any future channel that forgets the tenant segment. Reverb runs as a separate process with no visibility into the HTTP kernel or `TenantContext`, so isolation must be enforced entirely inside channel authorization closures against data that is valid in every execution context (HTTP or queued).

### Requirement: Tenant-scoped channel naming and authorization helper
The system MUST provide a tenancy-core helper (`App\Tenancy\Broadcasting`) exposing (a) a channel-name builder that embeds the numeric `schools.id` as the tenant segment in the shape `school.{schoolId}.{resource}.{id}` (wire name `private-school.{schoolId}.{resource}.{id}` once Laravel's `private-` prefix is applied), and (b) an authorization predicate that MUST first return `false` when `$user->school_id === null` (explicit null check, no cast), MUST then validate that `$schoolId` is a well-formed positive integer (e.g. via `ctype_digit((string) $schoolId)`) and return `false` if it is not, and only then compares `(int) $user->school_id === (int) $schoolId`. This ordering is required because PHP casts both `null` and any non-numeric string (e.g. `"abc"`) to `0`, so a naive `(int) $user->school_id === (int) $schoolId` comparison would incorrectly authorize a landlord user (`school_id = NULL`) against a malformed, non-numeric channel segment. The predicate MUST NOT read `TenantContext`, because `TenantContext` is request-scoped and is not bound when a broadcast is emitted from a queued job or console command.

#### Scenario: Helper builds a school-scoped channel name
- GIVEN a school id `7`, resource `grades`, and resource id `42`
- WHEN `schoolChannel(7, 'grades', 42)` is called
- THEN it returns `school.7.grades.42`

#### Scenario: Authorization predicate accepts a same-school user
- GIVEN a user with `school_id = 7`
- WHEN `authorizeSchool($user, 7)` is evaluated
- THEN it returns `true`

#### Scenario: Authorization predicate rejects a different-school user
- GIVEN a user with `school_id = 7`
- WHEN `authorizeSchool($user, 9)` is evaluated
- THEN it returns `false`

#### Scenario: Authorization predicate rejects a landlord (null school_id) user
- GIVEN a user with `school_id = NULL`
- WHEN `authorizeSchool($user, $schoolId)` is evaluated for any `$schoolId`, including a well-formed numeric id (e.g. `7`) or a malformed/non-numeric segment (e.g. `"abc"`)
- THEN it returns `false` in every case, because the predicate checks `$user->school_id === null` explicitly before any numeric comparison and never relies on PHP's `(int) null === (int) $schoolId` coercion (which would otherwise falsely match a malformed `$schoolId` that also casts to `0`)

#### Scenario: Authorization predicate coerces string channel ids
- GIVEN a user with `school_id = 7`
- WHEN `authorizeSchool($user, "7")` is evaluated (channel wildcard segments arrive as strings)
- THEN it returns `true`, matching via integer coercion rather than strict type equality

### Requirement: Channel-naming discipline is enforced by a build-time guardrail, not reviewer memory
The system MUST have an automated test that enumerates every channel registered in `routes/channels.php` and asserts each one begins with the `school.{schoolId}` tenant segment, except for an explicit, named allow-list of exemptions. This test MUST fail the build the first time a new channel is registered without the tenant segment and is not added to the allow-list.

#### Scenario: All non-exempt registered channels carry the tenant segment
- GIVEN the current channel registry (today: only the framework-default channel)
- WHEN the channel-registry discipline test runs
- THEN every registered channel not on the allow-list begins with `school.` followed by a numeric-id segment

#### Scenario: The framework-default user channel is the documented exemption
- GIVEN the framework-default `App.Models.User.{id}` channel, which is inherently per-user rather than per-tenant
- WHEN the channel-registry discipline test runs
- THEN this channel is present on the explicit allow-list and does not cause the test to fail

#### Scenario: A future non-exempt channel without the tenant segment fails the build
- GIVEN a hypothetical channel `grades.{id}` is registered without a `school.{schoolId}` prefix and without being added to the allow-list
- WHEN the channel-registry discipline test runs
- THEN the test fails, surfacing the missing tenant segment before the channel ships

### Requirement: Reverb Redis horizontal scaling stays disabled by default with a documented flip trigger
The system MUST keep `REVERB_SCALING_ENABLED=false` as the default for this change. The Redis-backed scaling infrastructure MAY remain fully provisioned and ready to enable, but enabling it is explicitly deferred, not forbidden. The concrete signal for when to enable it, and the Redis logical-DB collision check required at flip time, MUST be documented.

#### Scenario: Reverb scaling is off by default
- GIVEN the default application configuration
- WHEN `config('reverb.apps.apps.0.options.scaling.enabled')` (or the equivalent `REVERB_SCALING_ENABLED` env-backed config) is read
- THEN it evaluates to `false`

#### Scenario: The flip trigger is documented, not automatically evaluated
- GIVEN Reverb scaling is currently off
- WHEN a developer needs to decide whether to enable it
- THEN documentation states the concrete trigger (evidence of real multi-instance Reverb need or measured single-process connection saturation) and the required Redis logical-DB separation check from cache/session, and enabling it remains a deliberate, separate action rather than a default or an automatic switch
