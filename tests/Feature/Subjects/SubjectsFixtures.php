<?php

namespace Tests\Feature\Subjects;

use App\Tenancy\Models\School;
use Illuminate\Support\Facades\DB;
use Modules\Subjects\Infrastructure\Models\StudyPlanModel;
use Modules\Users\Infrastructure\Models\User;

/**
 * Row builders for the Subjects feature tests. Sibling-module rows
 * (grade levels, sections, periods, offers) are inserted directly, so the
 * tests do not depend on those modules' internals.
 */
final class SubjectsFixtures
{
    public static function url(School $school, string $path = ''): string
    {
        return 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/study-plans'.$path;
    }

    public static function user(School $school, string $role): User
    {
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole($role);

        return $user;
    }

    public static function gradeLevel(School $school, string $name, int $order): int
    {
        $levelId = DB::table('niveles_academicos')->where('school_id', $school->id)->value('id')
            ?? DB::table('niveles_academicos')->insertGetId(['school_id' => $school->id, 'name' => 'Secundaria', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('grados')->insertGetId([
            'school_id' => $school->id,
            'nivel_academico_id' => $levelId,
            'name' => $name,
            'order' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function section(School $school, string $name): int
    {
        return DB::table('secciones')->insertGetId(['school_id' => $school->id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function period(School $school, string $name, bool $active = false, ?string $startsOn = null, ?string $endsOn = null): int
    {
        return DB::table('periodos_academicos')->insertGetId([
            'school_id' => $school->id,
            'name' => $name,
            'starts_on' => $startsOn ?? now()->subMonths(2)->toDateString(),
            'ends_on' => $endsOn ?? now()->addMonths(8)->toDateString(),
            'is_active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function offer(School $school, int $periodId, int $gradeLevelId, int $sectionId): int
    {
        return DB::table('ofertas_academicas')->insertGetId([
            'school_id' => $school->id,
            'periodo_academico_id' => $periodId,
            'grado_id' => $gradeLevelId,
            'seccion_id' => $sectionId,
            'teacher_id' => null,
            'capacity' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function plan(School $school, string $code, array $overrides = []): int
    {
        $row = array_merge([
            'school_id' => $school->id,
            'code' => $code,
            'name' => 'Plan '.$code,
            'observation' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return DB::table('study_plans')->insertGetId($row + [
            'search_text' => StudyPlanModel::searchText($row['code'].' '.$row['name'].' '.$row['observation']),
        ]);
    }

    public static function subject(School $school, int $planId, int $gradeLevelId, string $name, array $overrides = []): int
    {
        return DB::table('study_plan_subjects')->insertGetId(array_merge([
            'school_id' => $school->id,
            'study_plan_id' => $planId,
            'grade_level_id' => $gradeLevelId,
            'name' => $name,
            'code' => null,
            'weekly_hours' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * @param  'school'|'grade_level'|'offer'  $scope
     */
    public static function assignment(
        School $school,
        int $periodId,
        int $planId,
        string $scope,
        ?int $gradeLevelId = null,
        ?int $offerId = null,
        bool $replaced = false,
    ): int {
        return DB::table('study_plan_assignments')->insertGetId([
            'school_id' => $school->id,
            'academic_period_id' => $periodId,
            'study_plan_id' => $planId,
            'scope' => $scope,
            'grade_level_id' => $gradeLevelId,
            'academic_offer_id' => $offerId,
            'target_id' => match ($scope) {
                'school' => 0,
                'grade_level' => $gradeLevelId,
                'offer' => $offerId,
            },
            'replaced_at' => $replaced ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function exclude(School $school, int $assignmentId, int $subjectId): void
    {
        DB::table('study_plan_subject_exclusions')->insert([
            'school_id' => $school->id,
            'study_plan_assignment_id' => $assignmentId,
            'study_plan_subject_id' => $subjectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
