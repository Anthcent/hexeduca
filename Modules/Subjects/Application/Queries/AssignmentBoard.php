<?php

namespace Modules\Subjects\Application\Queries;

use Modules\AcademicOffers\Public\DTOs\AcademicOfferSummary;
use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;
use Modules\Subjects\Domain\Services\EffectivePlanResolver;
use Modules\Subjects\Domain\Services\SubjectActivation;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

/**
 * Builds the "Asignaciones" screen of one period: the assignments per
 * scope and, for every offer, its effective plan, where it comes from and
 * its subjects with their active flag.
 */
final class AssignmentBoard
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly PlanAssignmentRepositoryInterface $assignments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $schoolId, int $periodId, AssignmentLabeler $labels): array
    {
        $plans = $this->plans->allInSchool($schoolId);
        $current = $this->assignments->currentForPeriod($schoolId, $periodId);
        $offers = $labels->offers($periodId);

        $effectiveByOffer = [];

        foreach ($offers as $offer) {
            $effectiveByOffer[$offer->id] = EffectivePlanResolver::resolve($current, $offer->id, (int) $offer->gradeLevelId);
        }

        $usedPlanIds = array_values(array_unique(array_map(fn (PlanAssignment $a): int => $a->planId(), $current)));
        $subjectsByPlan = $this->subjects->forPlans($usedPlanIds, $schoolId);
        $excludedByAssignment = $this->assignments->excludedSubjectIdsFor(
            array_map(fn (PlanAssignment $a): int => (int) $a->id(), $current),
            $schoolId,
        );

        return [
            'assignments' => $this->assignmentsByScope($periodId, $current, $plans, $effectiveByOffer, $excludedByAssignment, $labels),
            'offers' => array_values(array_map(
                fn (AcademicOfferSummary $offer): array => $this->offerRow(
                    $offer,
                    $effectiveByOffer[$offer->id],
                    $plans,
                    $subjectsByPlan,
                    $excludedByAssignment,
                ),
                $offers,
            )),
        ];
    }

    /**
     * @param  list<PlanAssignment>  $current
     * @param  array<int, StudyPlan>  $plans
     * @param  array<int, PlanAssignment|null>  $effectiveByOffer
     * @param  array<int, list<int>>  $excludedByAssignment
     * @return array{school: array<string, mixed>|null, gradeLevels: list<array<string, mixed>>, offers: list<array<string, mixed>>}
     */
    private function assignmentsByScope(int $periodId, array $current, array $plans, array $effectiveByOffer, array $excludedByAssignment, AssignmentLabeler $labels): array
    {
        $grouped = ['school' => null, 'gradeLevels' => [], 'offers' => []];
        $gradeOrder = array_flip(array_keys($labels->gradeLevels()));

        foreach ($current as $assignment) {
            $row = $labels->assignment($assignment, $plans) + [
                'gradeLevelId' => $assignment->gradeLevelId(),
                'offerId' => $assignment->offerId(),
                'offerCount' => count(array_filter($effectiveByOffer, fn (?PlanAssignment $e): bool => $e?->id() === $assignment->id())),
                // Lost when another plan takes the slot: the page warns first.
                'excludedCount' => count($excludedByAssignment[(int) $assignment->id()] ?? []),
            ];

            match ($assignment->scope()) {
                AssignmentScope::School => $grouped['school'] = $row,
                AssignmentScope::GradeLevel => $grouped['gradeLevels'][] = $row,
                AssignmentScope::Offer => $grouped['offers'][] = $row,
            };
        }

        $byGrade = fn (array $a, array $b): int => ($gradeOrder[$a['gradeLevelId']] ?? PHP_INT_MAX) <=> ($gradeOrder[$b['gradeLevelId']] ?? PHP_INT_MAX);
        usort($grouped['gradeLevels'], $byGrade);
        $offerOrder = array_flip(array_keys($labels->offers($periodId)));
        usort($grouped['offers'], fn (array $a, array $b): int => ($offerOrder[$a['offerId']] ?? PHP_INT_MAX) <=> ($offerOrder[$b['offerId']] ?? PHP_INT_MAX));

        return $grouped;
    }

    /**
     * @param  array<int, StudyPlan>  $plans
     * @param  array<int, list<Subject>>  $subjectsByPlan
     * @param  array<int, list<int>>  $excludedByAssignment
     * @return array<string, mixed>
     */
    private function offerRow(
        AcademicOfferSummary $offer,
        ?PlanAssignment $effective,
        array $plans,
        array $subjectsByPlan,
        array $excludedByAssignment,
    ): array {
        $plan = $effective ? ($plans[$effective->planId()] ?? null) : null;

        $subjects = $effective === null ? [] : SubjectActivation::forGradeLevel(
            $subjectsByPlan[$effective->planId()] ?? [],
            (int) $offer->gradeLevelId,
            $excludedByAssignment[(int) $effective->id()] ?? [],
        );

        return [
            'id' => $offer->id,
            'gradeLevelId' => $offer->gradeLevelId,
            'gradeLevelName' => $offer->gradeLevelName,
            'sectionName' => $offer->sectionName,
            'label' => AssignmentLabeler::offerLabel($offer),
            'effective' => $effective === null ? null : [
                'assignmentId' => (int) $effective->id(),
                'scope' => $effective->scope()->value,
                'plan' => $plan ? AssignmentLabeler::plan($plan) : null,
            ],
            'subjects' => array_map(fn (array $row): array => [
                'id' => (int) $row['subject']->id(),
                'name' => $row['subject']->name(),
                'code' => $row['subject']->code(),
                'weeklyHours' => $row['subject']->weeklyHours(),
                'active' => $row['active'],
            ], $subjects),
        ];
    }
}
