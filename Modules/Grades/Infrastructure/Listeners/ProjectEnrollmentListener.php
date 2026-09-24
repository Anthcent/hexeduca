<?php

namespace Modules\Grades\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Enrollments\Public\Events\EnrollmentCreated;
use Throwable;

/**
 * Reacts to Modules\Enrollments\Public\Events\EnrollmentCreated (dispatched
 * by App\IntegrationEvents\Outbox\OutboxWorker) and upserts a row into
 * `grades_enrollment_projection` — Grades' own local copy, never a live
 * query against Modules\Enrollments (plan §6/§8).
 *
 * A version guard makes this idempotent (plan §7): a late-arriving older
 * version of the same enrollment can never overwrite a newer projected
 * state.
 */
class ProjectEnrollmentListener implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60, 300];

    public function handle(EnrollmentCreated $event): void
    {
        $values = [
            'source_enrollment_id' => $event->enrollmentId,
            'student_id' => $event->studentId,
            'academic_offer_id' => $event->academicOfferId,
            'school_id' => $event->schoolId,
            'academic_period_id' => $event->academicPeriodId,
            'status' => $event->status,
            'source_updated_at' => $event->occurredAt(),
            'last_event_version' => $event->version(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->applyIfNewer($event->enrollmentId, $event->version(), $values);
    }

    /**
     * Database-side compare-and-update removes the check-then-upsert race.
     * Insert-or-ignore handles a missing row; the second conditional update
     * resolves a concurrent insert without allowing an older version to win.
     *
     * @param  array<string, mixed>  $values
     */
    private function applyIfNewer(int $enrollmentId, int $version, array $values): void
    {
        $updates = $values;
        unset($updates['source_enrollment_id'], $updates['created_at']);

        $query = fn () => DB::table('grades_enrollment_projection')
            ->where('source_enrollment_id', $enrollmentId)
            ->where('last_event_version', '<', $version);

        if ($query()->update($updates) > 0) {
            return;
        }

        if (DB::table('grades_enrollment_projection')->insertOrIgnore($values) === 0) {
            $query()->update($updates);
        }
    }

    public function failed(EnrollmentCreated $event, Throwable $exception): void
    {
        Log::channel('integration-events')->error('ProjectEnrollmentListener failed', [
            'enrollment_id' => $event->enrollmentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
