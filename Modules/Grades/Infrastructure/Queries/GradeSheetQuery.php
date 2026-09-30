<?php

namespace Modules\Grades\Infrastructure\Queries;

use Illuminate\Support\Facades\DB;
use Modules\AcademicMoments\Public\Contracts\AcademicMomentReader;
use Modules\Grades\Application\DTOs\Actor;
use Modules\Grades\Application\Services\GradeAccess;
use Modules\Grades\Domain\Entities\EvaluationPlan;
use Modules\Grades\Domain\Services\MomentGrade;
use Modules\Grades\Infrastructure\Models\GradeExtraModel;
use Modules\Grades\Infrastructure\Models\GradeScoreModel;
use Modules\Users\Public\Contracts\StudentReader;
use Modules\Users\Public\DTOs\StudentDTO;

/**
 * Everything the grade sheet shows: the students of the offer with every
 * score, their extra and their computed moment grade. Loaded with a fixed
 * number of queries, whatever the size of the class.
 */
final class GradeSheetQuery
{
    public function __construct(
        private readonly StudentReader $students,
        private readonly AcademicMomentReader $moments,
        private readonly GradeAccess $access,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forPlan(Actor $actor, EvaluationPlan $plan): array
    {
        $schoolId = $actor->schoolId;
        $studentIds = DB::table('grades_enrollment_projection')
            ->where('school_id', $schoolId)
            ->where('academic_offer_id', $plan->offerId)
            ->where('status', 'active')
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $names = [];

        foreach ($this->students->allForSchool($schoolId) as $student) {
            /** @var StudentDTO $student */
            $names[$student->id] = $student->name;
        }

        $scores = [];

        foreach (GradeScoreModel::query()->where('school_id', $schoolId)->where('grade_plan_id', $plan->id)->get(['student_id', 'grade_plan_indicator_id', 'points']) as $row) {
            $scores[$row->student_id][$row->grade_plan_indicator_id] = $row->points;
        }

        $extras = GradeExtraModel::query()->where('school_id', $schoolId)->where('grade_plan_id', $plan->id)
            ->pluck('points', 'student_id')->map(fn ($points): int => (int) $points)->all();

        $rows = array_map(function (int $studentId) use ($plan, $names, $scores, $extras): array {
            $points = $scores[$studentId] ?? [];
            $standing = $plan->standing($points);
            $extra = $extras[$studentId] ?? null;
            $final = MomentGrade::final($standing['average'], $extra ?? 0);

            return [
                'id' => $studentId,
                'name' => $names[$studentId] ?? "Estudiante {$studentId}",
                'scores' => (object) $points,
                'extra' => $extra,
                'referentTotals' => $standing['referentTotals'],
                'average' => $standing['average'],
                'maxExtra' => MomentGrade::maxExtra($standing['average']),
                'final' => $points === [] && $extra === null ? null : $final,
                'passes' => MomentGrade::passes($final),
                'complete' => $standing['complete'],
            ];
        }, $studentIds);

        usort($rows, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        $moment = $this->moments->findForSchool($plan->momentId, $schoolId);
        $windowOpen = $moment !== null && $this->access->windowOpen($moment);
        $periodOpen = $this->access->periodOpen($plan->periodId, $schoolId);

        return [
            'rows' => $rows,
            'moment' => $moment ? [
                'id' => $moment->id,
                'name' => $moment->name,
                'gradingOpensOn' => $moment->gradingOpensOn,
                'gradingClosesOn' => $moment->gradingClosesOn,
            ] : null,
            'windowOpen' => $windowOpen,
            'periodOpen' => $periodOpen,
            'canEdit' => $windowOpen && $periodOpen && $this->access->canManage($actor, $plan->offerId, $plan->subjectId),
        ];
    }
}
