<?php

namespace Modules\Grades\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Enrollments\Public\Contracts\EnrollmentProjectionSource;

/**
 * Rebuilds `grades_enrollment_projection` from scratch via the public
 * contract Modules\Enrollments\Public\Contracts\EnrollmentProjectionSource
 * — never by querying Modules\Enrollments\Infrastructure\Models directly
 * (plan §10/§13). Safety net for projection drift, not a substitute for
 * the event-driven listener.
 */
class RebuildEnrollmentProjection extends Command
{
    protected $signature = 'grades:rebuild-enrollments';

    protected $description = 'Rebuild grades_enrollment_projection from the Enrollments source of truth.';

    public function handle(EnrollmentProjectionSource $source): int
    {
        $count = 0;

        DB::transaction(function () use ($source, &$count) {
            // DELETE participates in this transaction on PostgreSQL and
            // SQLite. TRUNCATE has database-specific transactional behavior.
            DB::table('grades_enrollment_projection')->delete();

            foreach ($source->allForRebuild() as $row) {
                DB::table('grades_enrollment_projection')->insert([
                    'source_enrollment_id' => $row->id,
                    'student_id' => $row->studentId,
                    'academic_offer_id' => $row->academicOfferId,
                    'school_id' => $row->schoolId,
                    'academic_period_id' => $row->academicPeriodId,
                    'status' => $row->status,
                    'source_updated_at' => $row->enrolledAt,
                    'last_event_version' => $row->version,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $count++;
            }
        });

        $this->info("Rebuilt grades_enrollment_projection: {$count} rows.");

        return self::SUCCESS;
    }
}
