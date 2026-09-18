# Delta for project-foundation

## ADDED Requirements

### Requirement: Tenant-aware api rate limiter
The system MUST key the existing `api` rate limiter (`RateLimiter::for('api', ...)` in `app/Providers/AppServiceProvider.php`) by the resolved tenant in addition to the existing user/IP key, so each school's request budget is independent of every other school's. The key composition MUST be `school:{schoolId}|{user-or-ip}` when a tenant is resolved. A request with no resolved tenant (landlord/unresolved `school_id`) MUST fall back to the existing user/IP key unchanged. The `login` rate limiter, which is keyed by `email|ip` and runs before tenant authentication, MUST NOT be made tenant-aware by this requirement.

#### Scenario: Two tenants consume independent api buckets
- GIVEN a request resolved to school 1 and a separate request resolved to school 2, both from the same authenticated user or same IP
- WHEN school 1 exhausts its `api` rate limit
- THEN school 2's `api` requests are not throttled by school 1's exhausted bucket

#### Scenario: A single tenant's api bucket still enforces its own limit
- GIVEN a request resolved to school 1
- WHEN the number of requests from that school (for the given user/IP sub-key) exceeds the configured `api` threshold
- THEN subsequent requests from that same school/user-or-IP combination return HTTP 429 until the window resets

#### Scenario: Landlord or unresolved-tenant requests fall back to the existing key
- GIVEN a request with no resolved `school_id` (landlord host or unresolved tenant)
- WHEN the `api` rate limiter evaluates the request
- THEN it uses the pre-existing user/IP key with no tenant segment, and landlord throttling behavior is unchanged from before this change

#### Scenario: The login limiter is unaffected
- GIVEN the `login` rate limiter's existing `email|ip` key
- WHEN this change is applied
- THEN the `login` limiter's key composition and behavior remain unchanged
