<?php

namespace Tests\Feature\Grades;

use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AcademicMoments\AcademicMomentsFixtures as M;
use Tests\Feature\Subjects\SubjectsFixtures as S;
use Tests\Feature\TeachingAssignments\TeachingAssignmentsFixtures as T;
use Tests\TestCase;

/**
 * Row and payload builders for the Grades feature tests. Periods, offers,
 * subjects, moments and teaching assignments come from their modules'
 * fixtures; this adds the enrollment projection and plan payloads.
 */
final class GradesFixtures
{
    private static int $enrollment = 0;

    /**
     * The shared world of the Grades tests, set as properties of the test:
     * one active period on 2026-09-28 with an offer (1st year, section A)
     * taking Matemática and Arte; `teacher` teaches Matemática, `otherTeacher`
     * teaches nothing; `s1` and `s2` are enrolled, `s3` is not. `moment` has
     * an open grading window, `closedMoment` a past one, `undatedMoment` none.
     */
    public static function scenario(TestCase $test): void
    {
        $test->travelTo('2026-09-28 10:00:00');
        $test->seed(RoleAndPermissionSeeder::class);

        $test->school = School::factory()->create();
        $test->otherSchool = School::factory()->create();
        $test->seed(ModulePlatformSeeder::class);

        $test->staff = S::user($test->school, 'staff/admin');
        $test->teacher = S::user($test->school, 'teacher');
        $test->otherTeacher = S::user($test->school, 'teacher');
        $test->student = S::user($test->school, 'student');
        $test->s1 = S::user($test->school, 'student');
        $test->s2 = S::user($test->school, 'student');
        $test->s3 = S::user($test->school, 'student');

        $test->grade1 = S::gradeLevel($test->school, 'Primer año', 1);
        $test->period = S::period($test->school, '2026', true);
        $test->offer = S::offer($test->school, $test->period, $test->grade1, S::section($test->school, 'A'));
        $test->studyPlan = S::plan($test->school, '31060');
        $test->math = S::subject($test->school, $test->studyPlan, $test->grade1, 'Matemática');
        $test->art = S::subject($test->school, $test->studyPlan, $test->grade1, 'Arte');
        S::assignment($test->school, $test->period, $test->studyPlan, 'school');

        $test->moment = M::moment($test->period, 'Primer momento', 1, '2026-09-01', '2026-10-15');
        $test->closedMoment = M::moment($test->period, 'Segundo momento', 2, '2026-07-01', '2026-07-31');
        $test->undatedMoment = M::moment($test->period, 'Tercer momento', 3);

        T::assignment($test->school, $test->period, $test->offer, $test->math, $test->teacher->id);

        self::enroll($test->school, $test->period, $test->offer, $test->s1->id);
        self::enroll($test->school, $test->period, $test->offer, $test->s2->id);
    }

    public static function url(School $school, string $path = ''): string
    {
        return 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/grades'.$path;
    }

    /**
     * A row of Grades' own enrollment projection, the only source of who can
     * be graded in an offer.
     */
    public static function enroll(School $school, int $periodId, int $offerId, int $studentId, string $status = 'active'): void
    {
        DB::table('grades_enrollment_projection')->insert([
            'source_enrollment_id' => ++self::$enrollment + 100000,
            'student_id' => $studentId,
            'academic_offer_id' => $offerId,
            'school_id' => $school->id,
            'academic_period_id' => $periodId,
            'status' => $status,
            'source_updated_at' => now(),
            'last_event_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Two referentes: 12 + 8 and 10 + 10.
     *
     * @return list<array<string, mixed>>
     */
    public static function referents(): array
    {
        return [
            ['topic' => 'Fracciones', 'technique' => 'Prueba escrita', 'indicators' => [
                ['description' => 'Suma fracciones', 'maxPoints' => 12],
                ['description' => 'Simplifica', 'maxPoints' => 8],
            ]],
            ['topic' => 'Geometría', 'technique' => null, 'indicators' => [
                ['description' => 'Calcula áreas', 'maxPoints' => 10],
                ['description' => 'Traza figuras', 'maxPoints' => 10],
            ]],
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $referents
     * @return array<string, mixed>
     */
    public static function planPayload(int $offerId, int $subjectId, int $momentId, ?array $referents = null): array
    {
        return [
            'offer_id' => $offerId,
            'subject_id' => $subjectId,
            'moment_id' => $momentId,
            'referents' => $referents ?? self::referents(),
        ];
    }

    /**
     * Staff saves the default plan for a slot of the scenario's offer (or
     * `$offerId`) and returns its id.
     */
    public static function plan(TestCase $test, int $subjectId, int $momentId, ?int $offerId = null): int
    {
        $offerId ??= $test->offer;

        $test->actingAs($test->staff)
            ->put(self::url($test->school, '/plan'), self::planPayload($offerId, $subjectId, $momentId))
            ->assertSessionHasNoErrors();

        return (int) DB::table('grade_plans')
            ->where('academic_offer_id', $offerId)
            ->where('study_plan_subject_id', $subjectId)
            ->where('academic_moment_id', $momentId)
            ->value('id');
    }

    /**
     * A plan of `otherSchool`, saved by that school's staff.
     */
    public static function foreignPlan(TestCase $test): int
    {
        $period = S::period($test->otherSchool, '2026', true);
        $grade = S::gradeLevel($test->otherSchool, 'Primero', 1);
        $offer = S::offer($test->otherSchool, $period, $grade, S::section($test->otherSchool, 'A'));
        $studyPlan = S::plan($test->otherSchool, '1');
        $subject = S::subject($test->otherSchool, $studyPlan, $grade, 'Física');
        S::assignment($test->otherSchool, $period, $studyPlan, 'school');
        $moment = M::moment($period, 'Primer momento', 1, '2026-09-01', '2026-10-15');

        $test->actingAs(S::user($test->otherSchool, 'staff/admin'))
            ->put(self::url($test->otherSchool, '/plan'), self::planPayload($offer, $subject, $moment))
            ->assertSessionHasNoErrors();

        return (int) DB::table('grade_plans')->where('school_id', $test->otherSchool->id)->value('id');
    }

    /**
     * Staff opens a correction on a plan.
     */
    public static function openCorrection(TestCase $test, int $planId, string $expiresOn, string $reason = 'Error de transcripción'): void
    {
        $test->actingAs($test->staff)
            ->post(self::url($test->school, "/sheets/{$planId}/correction"), ['reason' => $reason, 'expires_on' => $expiresOn])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Corrección abierta.');
    }

    /**
     * The plan's indicator ids in referente and letter order.
     *
     * @return list<int>
     */
    public static function indicatorIds(int $planId): array
    {
        return DB::table('grade_plan_indicators')
            ->join('grade_plan_referents', 'grade_plan_referents.id', '=', 'grade_plan_indicators.grade_plan_referent_id')
            ->where('grade_plan_indicators.grade_plan_id', $planId)
            ->orderBy('grade_plan_referents.position')
            ->orderBy('grade_plan_indicators.letter')
            ->pluck('grade_plan_indicators.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
