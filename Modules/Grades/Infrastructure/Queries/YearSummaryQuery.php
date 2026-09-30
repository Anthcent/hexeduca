<?php

namespace Modules\Grades\Infrastructure\Queries;

use Modules\AcademicMoments\Public\Contracts\AcademicMomentReader;
use Modules\AcademicMoments\Public\DTOs\AcademicMomentDTO;
use Modules\Grades\Domain\Services\MomentGrade;
use Modules\Grades\Domain\Services\YearGrade;
use Modules\Grades\Domain\Services\YearOutcome;
use Modules\Grades\Infrastructure\Models\GradePlanModel;
use Modules\Grades\Infrastructure\Models\GradeResultModel;
use Modules\Subjects\Public\DTOs\OfferSubjectDTO;

/**
 * The year at a glance, computed on read so a correction is reflected at
 * once: for one subject, each student's grade per moment and definitive
 * grade; for a whole offer, each student's definitive grade per subject and
 * their result for the year.
 */
final class YearSummaryQuery
{
    public function __construct(
        private readonly OfferRoster $roster,
        private readonly AcademicMomentReader $moments,
    ) {}

    /**
     * @return array{moments: list<array{id: int, name: string, planId: int|null}>, rows: list<array<string, mixed>>}
     */
    public function forSubject(int $schoolId, int $periodId, int $offerId, int $subjectId): array
    {
        $moments = $this->moments->forPeriod($schoolId, $periodId);
        $plans = $this->plans($schoolId, $offerId);
        $results = $this->results($schoolId, $plans);

        $rows = array_map(function (array $student) use ($moments, $plans, $results, $subjectId): array {
            $grades = $this->momentGrades($moments, $plans[$subjectId] ?? [], $results[$student['id']] ?? []);
            $year = $this->yearGrade($grades);

            return $student + [
                'moments' => $grades,
                'year' => $year,
                'passes' => $year !== null && MomentGrade::passes($year),
            ];
        }, $this->roster->forOffer($schoolId, $offerId));

        return [
            'moments' => array_map(fn (AcademicMomentDTO $moment): array => [
                'id' => $moment->id,
                'name' => $moment->name,
                'planId' => $plans[$subjectId][$moment->id] ?? null,
            ], $moments),
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<OfferSubjectDTO>  $subjects
     * @return list<array{id: int, name: string, years: array<int, int|null>, failed: int, outcome: string}>
     */
    public function forOffer(int $schoolId, int $periodId, int $offerId, array $subjects): array
    {
        $moments = $this->moments->forPeriod($schoolId, $periodId);
        $plans = $this->plans($schoolId, $offerId);
        $results = $this->results($schoolId, $plans);

        return array_map(function (array $student) use ($moments, $plans, $results, $subjects): array {
            $years = [];

            foreach ($subjects as $subject) {
                $years[$subject->id] = $this->yearGrade($this->momentGrades($moments, $plans[$subject->id] ?? [], $results[$student['id']] ?? []));
            }

            return $student + [
                'years' => $years,
                'failed' => count(array_filter($years, fn (?int $year): bool => $year !== null && ! MomentGrade::passes($year))),
                'outcome' => YearOutcome::for(array_values($years)),
            ];
        }, $this->roster->forOffer($schoolId, $offerId));
    }

    /**
     * The offer's plans by subject and moment.
     *
     * @return array<int, array<int, int>>
     */
    private function plans(int $schoolId, int $offerId): array
    {
        $plans = [];

        foreach (GradePlanModel::query()->where('school_id', $schoolId)->where('academic_offer_id', $offerId)->get(['id', 'study_plan_subject_id', 'academic_moment_id']) as $plan) {
            $plans[$plan->study_plan_subject_id][$plan->academic_moment_id] = (int) $plan->id;
        }

        return $plans;
    }

    /**
     * Stored moment results by student and plan.
     *
     * @param  array<int, array<int, int>>  $plans
     * @return array<int, array<int, array{final: int, complete: bool}>>
     */
    private function results(int $schoolId, array $plans): array
    {
        $planIds = array_merge(...array_map('array_values', array_values($plans)));
        $results = [];

        foreach (GradeResultModel::query()->where('school_id', $schoolId)->whereIn('grade_plan_id', $planIds)->get(['grade_plan_id', 'student_id', 'final', 'complete']) as $result) {
            $results[$result->student_id][$result->grade_plan_id] = ['final' => $result->final, 'complete' => $result->complete];
        }

        return $results;
    }

    /**
     * @param  list<AcademicMomentDTO>  $moments
     * @param  array<int, int>  $planByMoment
     * @param  array<int, array{final: int, complete: bool}>  $studentResults
     * @return list<array{final: int|null, complete: bool}>
     */
    private function momentGrades(array $moments, array $planByMoment, array $studentResults): array
    {
        return array_map(function (AcademicMomentDTO $moment) use ($planByMoment, $studentResults): array {
            $result = $studentResults[$planByMoment[$moment->id] ?? 0] ?? null;

            return ['final' => $result['final'] ?? null, 'complete' => $result['complete'] ?? false];
        }, $moments);
    }

    /**
     * @param  list<array{final: int|null, complete: bool}>  $grades
     */
    private function yearGrade(array $grades): ?int
    {
        return YearGrade::final(array_map(fn (array $grade): ?int => $grade['complete'] ? $grade['final'] : null, $grades));
    }
}
