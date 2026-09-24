# hexeduca

Multi-tenant school management platform. Laravel 12 + Inertia.js + Vue 3, modular (nwidart/laravel-modules), subdomain-based tenancy.

## Stack

- **Backend**: Laravel 12, PHP 8.3, PostgreSQL, Redis, Laravel Reverb (broadcasting), Horizon (queues)
- **Frontend**: Vue 3 + Inertia.js + Tailwind CSS, Vite
- **Modules**: `Users`, `Academic`, `Admin`, `Schedule`, `Grades`, `Files`, `Notifications` (nwidart/laravel-modules, hexagonal-leaning: `Domain` / `Application` / `Infrastructure` per module)
- **Multi-tenancy**: shared-database, single-schema, subdomain-identified (`{tenant}.app.com`) — see `TENANCY.md`

## Local development

```bash
./vendor/bin/sail up -d
npm install && npm run dev   # or npm run build for production assets
```

See `TENANCY.md` for local subdomain/hosts-file setup and `openspec/` for the change history of this project (Spec-Driven Development artifacts).

## Tests

```bash
php artisan test
```

## Safe deployment order

Replace every `<...>` token below with commands and service names from the target environment. Do not run rebuilds while application writes, scheduled outbox publishing, manual publishers, or queue consumers remain active.

```bash
# 1. Stop every integration-event consumer. Use the process manager actually
# installed in the environment; these are examples, not literal commands.
sudo systemctl stop <scheduler-service> <outbox-publisher-service> <queue-worker-service>
# OR: supervisorctl stop <scheduler-program> <outbox-publisher-program> <queue-worker-program>
# OR: <platform-specific-stop-command>

# 2. Block application writes.
php artisan down

# 3. BEFORE migrating, run this preflight against the production database.
# Abort deployment and repair data if it returns any row.
<database-client-command> <<'SQL'
SELECT school_id, count(*) AS active_periods
FROM periodos_academicos
WHERE is_active = true
GROUP BY school_id
HAVING count(*) > 1;
SQL

# 4. Apply schema changes only after preflight succeeds.
php artisan migrate --force

# PostgreSQL aborts the Grades projection widening if its table lock cannot
# be acquired within 5 seconds. Clear the blocker, then rerun the migration.

# 5. Rebuild every projection while writes and consumers remain stopped.
php artisan academic-offers:rebuild-teacher-projection
php artisan enrollments:rebuild-student-projection
php artisan grades:rebuild-enrollments
```

Production may run multiple publisher processes: rows are atomically claimed and conditionally finalized. Delivery remains at-least-once because a worker can crash after dispatch but before finalization; every consumer must deduplicate by durable payload `eventId`, or use an equivalent aggregate/version conditional write as current projection listeners do.

Before enabling traffic, verify source/projection counts and failed outbox rows. Teacher and student projections intentionally include inactive tombstones, so compare active rows to source users with each role; Grades enrollment projection should equal source enrollments. Investigate any non-zero terminal outbox count. Example PostgreSQL checks:

```sql
SELECT count(*) FROM users u
JOIN model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
JOIN roles r ON r.id = m.role_id WHERE r.name = 'teacher';
SELECT count(*) FROM academic_offers_teacher_projection WHERE is_active = true;

SELECT count(*) FROM users u
JOIN model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
JOIN roles r ON r.id = m.role_id WHERE r.name = 'student';
SELECT count(*) FROM enrollments_student_projection WHERE is_active = true;

SELECT count(*) FROM matriculas;
SELECT count(*) FROM grades_enrollment_projection;

SELECT status, count(*) FROM integration_outbox_events GROUP BY status ORDER BY status;
SELECT id, event_name, aggregate_type, aggregate_id, attempts, failed_reason
FROM integration_outbox_events WHERE status = 'failed' ORDER BY id;

```

If verification succeeds, restore traffic, then restart long-running services through the environment's supervisor. Never run blocking `schedule:work` or `queue:work` inline in the deployment shell.

```bash
# 6. Restore application traffic.
php artisan up

# 7. Restart supervised services. Replace placeholders; choose one process manager.
sudo systemctl start <scheduler-service> <outbox-publisher-service> <queue-worker-service>
# OR: supervisorctl start <scheduler-program> <outbox-publisher-program> <queue-worker-program>
# OR: <platform-specific-start-command>

# 8. Post-deploy health checks.
php artisan schedule:list
php artisan queue:monitor <queue-connection>:<queue-name> --max=<alert-threshold>
php artisan integration-events:publish-outbox --limit=<health-check-batch-size>
<database-client-command> -c "SELECT status, count(*) FROM integration_outbox_events GROUP BY status ORDER BY status;"
<service-health-command> <scheduler-service> <outbox-publisher-service> <queue-worker-service>
```

`integration-events:publish-outbox` exits non-zero while any terminal failed row remains. A row becoming terminal on its fifth failed attempt logs a critical record through `INTEGRATION_EVENTS_LOG_CHANNEL` (default: `integration-events`). Alert on command failures, failed-row count, queue depth, and unhealthy supervised services.

Terminal outbox rows remain unhealthy across later publisher runs until an operator resolves them. After correcting the underlying cause, requeue a specific row deliberately inside a database transaction by setting `status = 'pending'`, `attempts = 0`, `available_at = CURRENT_TIMESTAMP`, `failed_reason = NULL`, `claim_token = NULL`, and `claimed_at = NULL`. Never bulk-requeue poison rows, and never mark one published unless its `eventId` side effect has been verified. The next publisher invocation retries requeued rows and remains non-zero while any other terminal row exists.
