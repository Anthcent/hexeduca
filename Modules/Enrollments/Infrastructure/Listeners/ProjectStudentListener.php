<?php

namespace Modules\Enrollments\Infrastructure\Listeners;

use App\IntegrationEvents\IntegrationEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Users\Public\Events\UserCreated;
use Modules\Users\Public\Events\UserUpdated;
use Throwable;

/**
 * Reacts to Modules\Users\Public\Events\{UserCreated,UserUpdated}
 * (dispatched by App\IntegrationEvents\Outbox\OutboxWorker) and upserts a
 * row into `enrollments_student_projection` — this module's own local
 * copy, never a live query against Modules\Users (plan §6/§8). Same
 * version-guard idempotency pattern as
 * Modules\Grades\Infrastructure\Listeners\ProjectEnrollmentListener.
 */
class ProjectStudentListener implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60, 300];

    public function handle(UserCreated|UserUpdated $event): void
    {
        // Legacy role-less payloads cannot safely drive a role projection.
        // Ignore them; projection rebuilds must use Users' role-qualified
        // public readers as the authoritative source.
        if ($event->roles === null) {
            return;
        }

        $values = [
            'source_student_id' => $event->userId,
            'school_id' => $event->schoolId,
            'name' => $event->name,
            'email' => $event->email,
            'is_active' => in_array('student', $event->roles, true),
            'source_updated_at' => $event->occurredAt(),
            'last_event_version' => $event->version(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->applyIfNewer($event->userId, $event->version(), $values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function applyIfNewer(int $studentId, int $version, array $values): void
    {
        $updates = $values;
        unset($updates['source_student_id'], $updates['created_at']);

        $query = fn () => DB::table('enrollments_student_projection')
            ->where('source_student_id', $studentId)
            ->where('last_event_version', '<', $version);

        if ($query()->update($updates) > 0) {
            return;
        }

        if (DB::table('enrollments_student_projection')->insertOrIgnore($values) === 0) {
            $query()->update($updates);
        }
    }

    public function failed(IntegrationEvent $event, Throwable $exception): void
    {
        Log::channel('integration-events')->error('ProjectStudentListener failed', [
            'user_id' => $event->aggregateId(),
            'error' => $exception->getMessage(),
        ]);
    }
}
