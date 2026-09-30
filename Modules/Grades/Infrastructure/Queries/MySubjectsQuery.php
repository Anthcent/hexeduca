<?php

namespace Modules\Grades\Infrastructure\Queries;

use Illuminate\Support\Facades\DB;
use Modules\AcademicMoments\Public\Contracts\AcademicMomentReader;
use Modules\AcademicMoments\Public\DTOs\AcademicMomentDTO;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\Grades\Application\DTOs\Actor;
use Modules\Grades\Application\Services\GradeAccess;
use Modules\Grades\Infrastructure\Models\GradeCorrectionModel;
use Modules\Grades\Infrastructure\Models\GradeIndicatorModel;
use Modules\Grades\Infrastructure\Models\GradePlanModel;
use Modules\Grades\Infrastructure\Models\GradeScoreModel;
use Modules\Subjects\Public\Contracts\OfferSubjectsReader;
use Modules\TeachingAssignments\Public\Contracts\TeachingAssignmentReader;

/**
 * The actor's subjects in a period, each with its moments: plan status,
 * grading window and loading progress. A teacher sees their assignments;
 * staff sees every subject of every offer.
 */
final class MySubjectsQuery
{
    public function __construct(
        private readonly AcademicOfferReader $offers,
        private readonly AcademicMomentReader $moments,
        private readonly OfferSubjectsReader $offerSubjects,
        private readonly TeachingAssignmentReader $teaching,
        private readonly GradeAccess $access,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forPeriod(Actor $actor, int $periodId): array
    {
        $schoolId = $actor->schoolId;
        $offers = [];

        foreach ($this->offers->allForPeriod($schoolId, $periodId) as $offer) {
            $offers[$offer->id] = $offer;
        }

        $subjectsByOffer = $this->offerSubjects->forPeriod($schoolId, $periodId);
        $slots = [];

        if ($actor->isStaff) {
            foreach ($subjectsByOffer as $offerId => $subjects) {
                foreach ($subjects as $subject) {
                    $slots["{$offerId}-{$subject->id}"] = ['offerId' => $offerId, 'subject' => $subject, 'role' => null];
                }
            }
        } else {
            foreach ($this->teaching->forTeacher($schoolId, $periodId, $actor->id) as $assignment) {
                foreach ($subjectsByOffer[$assignment->offerId] ?? [] as $subject) {
                    if ($subject->id === $assignment->subjectId) {
                        $slots["{$assignment->offerId}-{$subject->id}"] = ['offerId' => $assignment->offerId, 'subject' => $subject, 'role' => $assignment->role];
                    }
                }
            }
        }

        $moments = $this->moments->forPeriod($schoolId, $periodId);
        $plans = GradePlanModel::query()->where('school_id', $schoolId)->where('academic_period_id', $periodId)->get(['id', 'academic_offer_id', 'study_plan_subject_id', 'academic_moment_id']);
        $planBySlot = [];

        foreach ($plans as $plan) {
            $planBySlot["{$plan->academic_offer_id}-{$plan->study_plan_subject_id}-{$plan->academic_moment_id}"] = $plan->id;
        }

        $planIds = $plans->pluck('id')->all();
        $indicatorCounts = GradeIndicatorModel::query()->where('school_id', $schoolId)->whereIn('grade_plan_id', $planIds)
            ->selectRaw('grade_plan_id, COUNT(*) as total')->groupBy('grade_plan_id')->pluck('total', 'grade_plan_id');
        $scoreCounts = GradeScoreModel::query()->where('school_id', $schoolId)->whereIn('grade_plan_id', $planIds)
            ->selectRaw('grade_plan_id, COUNT(*) as total')->groupBy('grade_plan_id')->pluck('total', 'grade_plan_id');
        $corrections = GradeCorrectionModel::query()->where('school_id', $schoolId)->whereIn('grade_plan_id', $planIds)
            ->whereNull('closed_at')->where('expires_at', '>', now())->pluck('grade_plan_id')->flip();
        $studentCounts = DB::table('grades_enrollment_projection')->where('school_id', $schoolId)->where('academic_period_id', $periodId)
            ->where('status', 'active')->selectRaw('academic_offer_id, COUNT(*) as total')->groupBy('academic_offer_id')->pluck('total', 'academic_offer_id');

        $cards = [];

        foreach ($slots as ['offerId' => $offerId, 'subject' => $subject, 'role' => $role]) {
            $offer = $offers[$offerId] ?? null;

            if ($offer === null) {
                continue;
            }

            $students = (int) ($studentCounts[$offerId] ?? 0);

            $cards[] = [
                'offerId' => $offerId,
                'offerLabel' => "{$offer->gradeLevelName} · Sección {$offer->sectionName}",
                'gradeLevelId' => $offer->gradeLevelId,
                'subjectId' => $subject->id,
                'subjectName' => $subject->name,
                'role' => $role,
                'students' => $students,
                'moments' => array_map(function (AcademicMomentDTO $moment) use ($planBySlot, $offerId, $subject, $indicatorCounts, $scoreCounts, $students, $corrections): array {
                    $planId = $planBySlot["{$offerId}-{$subject->id}-{$moment->id}"] ?? null;
                    $expected = $planId ? $students * (int) ($indicatorCounts[$planId] ?? 0) : 0;

                    return [
                        'id' => $moment->id,
                        'name' => $moment->name,
                        'window' => $this->windowState($moment),
                        'planId' => $planId,
                        'correction' => $planId !== null && isset($corrections[$planId]),
                        'progress' => $expected > 0 ? (int) floor(100 * min($expected, (int) ($scoreCounts[$planId] ?? 0)) / $expected) : 0,
                    ];
                }, $moments),
            ];
        }

        usort($cards, fn (array $a, array $b): int => [$a['offerLabel'], $a['subjectName']] <=> [$b['offerLabel'], $b['subjectName']]);

        return $cards;
    }

    private function windowState(AcademicMomentDTO $moment): string
    {
        if ($moment->gradingOpensOn === null || $moment->gradingClosesOn === null) {
            return 'undefined';
        }

        if ($this->access->windowOpen($moment)) {
            return 'open';
        }

        return $moment->gradingOpensOn > now()->toDateString() ? 'upcoming' : 'closed';
    }
}
