# Delta for project-foundation

## ADDED Requirements

### Requirement: Repository-wide Pint style compliance
The system MUST pass `vendor/bin/pint --test` with zero violations across the entire repository, with no behavioral changes introduced by style fixes.

#### Scenario: Pint test passes clean
- GIVEN the repository at its current HEAD
- WHEN `vendor/bin/pint --test` is run repository-wide
- THEN the command exits 0 with no reported style violations

#### Scenario: Style fixes do not alter behavior
- GIVEN Pint auto-fixes have been applied to resolve prior violations
- WHEN the full Pest suite is run afterward
- THEN all previously passing tests still pass, with no new failures attributable to the style changes

### Requirement: Idempotent database seeding
The system MUST allow `db:seed` to be run repeatedly against the same database without producing duplicate-key errors or duplicate records, including the default test user currently created via `factory()->create()` with a fixed `test@example.com` address in `DatabaseSeeder`.

#### Scenario: Repeated seeding succeeds
- GIVEN a database that has already been seeded once
- WHEN `sail artisan db:seed` is run again
- THEN the command completes successfully with no duplicate-key or unique-constraint error
- AND exactly one user record exists for `test@example.com`

#### Scenario: Seeded test user is updated, not duplicated
- GIVEN the `test@example.com` user already exists from a prior seed run
- WHEN `db:seed` runs again
- THEN the existing record is updated in place (e.g. via `updateOrCreate` keyed on email)
- AND no second row with the same email is created

### Requirement: Idempotent object-storage bootstrap and credential-safe probe
The system MUST make the `s3` disk actually functional (the `league/flysystem-aws-s3-v3` Composer package MUST be present — confirmed absent from `composer.json` today, which makes any use of the `s3` disk fail immediately with a missing-class error regardless of bucket state), MUST provide an idempotent bootstrap step that creates the configured object-storage bucket only if it does not already exist, and a probe that performs a real upload, read, and delete against that bucket using the `s3` disk, without ever exposing credential values in output or logs. The `local` disk MUST be restored as the default `FILESYSTEM_DISK` (confirmed currently set to `s3` in the running environment, contrary to intended default); the `s3`/MinIO disk MUST be used only when explicitly targeted.

#### Scenario: S3 disk dependency is installed and resolvable
- GIVEN `composer.json` before this change has no `league/flysystem-aws-s3-v3` dependency
- WHEN the package is added and `composer install` runs
- THEN resolving the `s3` filesystem disk no longer throws a missing-class error

#### Scenario: Bucket bootstrap is idempotent
- GIVEN the configured bucket already exists
- WHEN the bootstrap step runs again
- THEN it detects the existing bucket, makes no destructive change, and exits successfully

#### Scenario: Bucket bootstrap creates a missing bucket
- GIVEN the configured bucket does not yet exist
- WHEN the bootstrap step runs
- THEN the bucket is created
- AND a subsequent run of the same step is a no-op per the previous scenario

#### Scenario: Storage probe verifies real read/write/delete
- GIVEN the bucket exists and MinIO/S3 credentials are configured
- WHEN the probe command runs against the `s3` disk
- THEN it uploads a test object, reads it back and confirms content match, then deletes it
- AND the probe's output contains no credential values (keys, secrets, tokens)

#### Scenario: Local disk remains the default
- GIVEN default application configuration with no explicit disk override
- WHEN any component resolves the default filesystem disk
- THEN it resolves to `local`, not `s3`

### Requirement: Local latency root cause documented; no code-level threshold committed
Live measurement (10 warm requests, dev stack already running 1h+) confirmed the register's original latency finding: `/up` p50 ~2.7s, `/` p50 ~3.5s, with an erratic per-request spread (0.27s–7.2s on the same endpoint under identical conditions). A live test of config/route caching as a candidate fix was performed and reverted after showing no improvement, ruling out uncached Laravel config as the cause. The erratic spread is consistent with Windows Docker Desktop bind-mount filesystem I/O contention, an infrastructure characteristic outside the application code. The system MUST document this root-cause finding with the evidence above, MUST NOT claim a numeric latency threshold as met by an application-code change, and `/up` MUST remain a lightweight health check with no added heavy dependencies (e.g. DB/cache/queue checks) regardless.

#### Scenario: Root cause is documented with live evidence
- GIVEN the live latency measurements and the reverted config/route-cache experiment
- WHEN the debt register is updated for DEBT-005
- THEN it records the measured p50s, the ruled-out hypothesis (Laravel config caching), and the identified root cause (Windows bind-mount I/O contention) as evidence

#### Scenario: DEBT-005 is not marked Resolved by this change
- GIVEN no application-code fix eliminates bind-mount I/O contention
- WHEN the debt register status is updated
- THEN DEBT-005 is set to a status reflecting root-cause-documented-but-not-code-fixable (e.g. "Accepted — infrastructure follow-up required"), not "Resolved"

#### Scenario: `/up` stays lightweight
- GIVEN the current `/up` implementation
- WHEN `GET /up` is inspected
- THEN it performs no database, cache-store, or queue connectivity checks beyond a basic process-alive response
