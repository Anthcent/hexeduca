<?php

namespace Tests\Feature\TeachingAssignments;

use App\Tenancy\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * Row builders for teaching assignments. Periods, offers and subjects come
 * from Tests\Feature\Subjects\SubjectsFixtures.
 */
final class TeachingAssignmentsFixtures
{
    public static function url(School $school, string $path = ''): string
    {
        return 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/teaching-assignments'.$path;
    }

    /**
     * @param  'titular'|'suplente'  $role
     */
    public static function assignment(
        School $school,
        int $periodId,
        int $offerId,
        int $subjectId,
        int $teacherId,
        string $role = 'titular',
        ?string $endedOn = null,
    ): int {
        return DB::table('teaching_assignments')->insertGetId([
            'school_id' => $school->id,
            'academic_period_id' => $periodId,
            'academic_offer_id' => $offerId,
            'study_plan_subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'role' => $role,
            'started_on' => now()->subWeek()->toDateString(),
            'ended_on' => $endedOn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
