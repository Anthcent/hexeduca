<?php

namespace Modules\Grades\Infrastructure\Queries;

use Illuminate\Support\Facades\DB;
use Modules\AcademicMoments\Public\DTOs\AcademicMomentDTO;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\Grades\Infrastructure\Models\GradeCorrectionModel;
use Modules\Grades\Infrastructure\Models\GradeIndicatorModel;
use Modules\Grades\Infrastructure\Models\GradePlanModel;
use Modules\Grades\Infrastructure\Models\GradeResultModel;
use Modules\Grades\Infrastructure\Models\GradeScoreModel;
use Modules\Subjects\Public\Contracts\OfferSubjectsReader;
use Modules\TeachingAssignments\Public\Contracts\TeachingAssignmentReader;
use Modules\Users\Public\Contracts\UserDirectory;

/**
 * Staff overview of one moment: every subject of every offer with its
 * titular teacher, plan, loading progress and open correction. A fixed
 * number of queries, whatever the size of the school.
 */
final class GradeMonitorQuery
{
    public function __construct(
        private readonly AcademicOfferReader $offers,
        private readonly OfferSubjectsReader $offerSubjects,
        private readonly TeachingAssignmentReader $teaching,
        private readonly UserDirectory $users,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forMoment(int $schoolId, AcademicMomentDTO $moment): array
    {
        $periodId = $moment->periodId;

        $titulars = [];

        foreach ($this->teaching->forPeriod($schoolId, $periodId) as $assignment) {
            if ($assignment->role === 'titular') {
                $titulars["{$assignment->offerId}-{$assignment->subjectId}"] = $assignment->teacherId;
            }
        }

        $names = $this->users->namesFor($schoolId, array_values(array_unique($titulars)));

        $plans = GradePlanModel::query()->where('school_id', $schoolId)->where('academic_moment_id', $moment->id)
            ->get(['id', 'academic_offer_id', 'study_plan_subject_id']);
        $planBySlot = [];

        foreach ($plans as $plan) {
            $planBySlot["{$plan->academic_offer_id}-{$plan->study_plan_subject_id}"] = $plan->id;
        }

        $planIds = $plans->pluck('id')->all();
        $indicatorCounts = GradeIndicatorModel::query()->where('school_id', $schoolId)->whereIn('grade_plan_id', $planIds)
            ->selectRaw('grade_plan_id, COUNT(*) as total')->groupBy('grade_plan_id')->pluck('total', 'grade_plan_id');
        $scoreCounts = GradeScoreModel::query()->where('school_id', $schoolId)->whereIn('grade_plan_id', $planIds)
            ->selectRaw('grade_plan_id, COUNT(*) as total')->groupBy('grade_plan_id')->pluck('total', 'grade_plan_id');
        $completeCounts = GradeResultModel::query()->where('school_id', $schoolId)->whereIn('grade_plan_id', $planIds)->where('complete', true)
            ->selectRaw('grade_plan_id, COUNT(*) as total')->groupBy('grade_plan_id')->pluck('total', 'grade_plan_id');
        // Only finished grades count as failing: a partial load is not a result yet.
        $failingCounts = GradeResultModel::query()->where('school_id', $schoolId)->whereIn('grade_plan_id', $planIds)->where('complete', true)->where('final', '<', 10)
            ->selectRaw('grade_plan_id, COUNT(*) as total')->groupBy('grade_plan_id')->pluck('total', 'grade_plan_id');
        $corrections = GradeCorrectionModel::query()->where('school_id', $schoolId)->whereIn('grade_plan_id', $planIds)
            ->whereNull('closed_at')->where('expires_at', '>', now())->pluck('expires_at', 'grade_plan_id');
        $studentCounts = DB::table('grades_enrollment_projection')->where('school_id', $schoolId)->where('academic_period_id', $periodId)
            ->where('status', 'active')->selectRaw('academic_offer_id, COUNT(*) as total')->groupBy('academic_offer_id')->pluck('total', 'academic_offer_id');

        $subjectsByOffer = $this->offerSubjects->forPeriod($schoolId, $periodId);
        $rows = [];

        foreach ($this->offers->allForPeriod($schoolId, $periodId) as $offer) {
            $students = (int) ($studentCounts[$offer->id] ?? 0);

            foreach ($subjectsByOffer[$offer->id] ?? [] as $subject) {
                $slot = "{$offer->id}-{$subject->id}";
                $planId = $planBySlot[$slot] ?? null;
                $teacherId = $titulars[$slot] ?? null;
                $expected = $planId ? $students * (int) ($indicatorCounts[$planId] ?? 0) : 0;
                $progress = $expected > 0 ? (int) floor(100 * min($expected, (int) ($scoreCounts[$planId] ?? 0)) / $expected) : 0;

                $rows[] = [
                    'offerId' => $offer->id,
                    'offerLabel' => "{$offer->gradeLevelName} · Sección {$offer->sectionName}",
                    'subjectId' => $subject->id,
                    'subjectName' => $subject->name,
                    'teacher' => $teacherId ? ($names[$teacherId]['name'] ?? null) : null,
                    'planId' => $planId,
                    'students' => $students,
                    'complete' => $planId ? (int) ($completeCounts[$planId] ?? 0) : 0,
                    'failing' => $planId ? (int) ($failingCounts[$planId] ?? 0) : 0,
                    'progress' => $progress,
                    'status' => match (true) {
                        $planId === null => 'no_plan',
                        $progress === 100 => 'complete',
                        $progress > 0 => 'in_progress',
                        default => 'not_started',
                    },
                    'correctionUntil' => $planId && isset($corrections[$planId]) ? $corrections[$planId]->toIso8601String() : null,
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a['offerLabel'], $a['subjectName']] <=> [$b['offerLabel'], $b['subjectName']]);

        return $rows;
    }
}
