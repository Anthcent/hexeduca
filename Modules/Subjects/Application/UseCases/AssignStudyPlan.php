<?php

namespace Modules\Subjects\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\AcademicOffers\Public\DTOs\AcademicOfferSummary;
use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\Subjects\Application\DTOs\AssignStudyPlanData;
use Modules\Subjects\Application\Services\AssignmentSlots;
use Modules\Subjects\Application\Services\OpenPeriods;
use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Exceptions\InvalidAssignment;
use Modules\Subjects\Domain\Exceptions\PeriodClosed;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

/**
 * Puts a plan in one slot of a period. The slot's current holder, if any,
 * is vacated first (see AssignmentSlots). Assigning the plan that already
 * holds the slot changes nothing, so its exclusions survive.
 */
final class AssignStudyPlan
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly PlanAssignmentRepositoryInterface $assignments,
        private readonly AssignmentSlots $slots,
        private readonly AcademicPeriodReader $periods,
        private readonly GradeLevelReader $gradeLevels,
        private readonly AcademicOfferReader $offers,
        private readonly OpenPeriods $openPeriods,
    ) {}

    /**
     * @throws StudyPlanNotFound
     * @throws RecordArchived when the plan is archived
     * @throws InvalidAssignment when the period or the target is not the school's
     * @throws PeriodClosed
     */
    public function handle(AssignStudyPlanData $data): PlanAssignment
    {
        $plan = $this->plans->findInSchool($data->planId, $data->schoolId) ?? throw StudyPlanNotFound::withId($data->planId);
        $plan->assertEditable();

        $period = $this->periods->findForSchool($data->periodId, $data->schoolId) ?? throw InvalidAssignment::unknownPeriod($data->periodId);

        if (! $this->openPeriods->isOpen($period)) {
            throw PeriodClosed::withId($period->id);
        }

        $assignment = $this->newAssignment($data);

        return DB::transaction(function () use ($assignment): PlanAssignment {
            $holder = $this->assignments->currentInSlot($assignment->schoolId(), $assignment->periodId(), $assignment->scope(), $assignment->targetId());

            if ($holder !== null && $holder->planId() === $assignment->planId()) {
                return $holder;
            }

            if ($holder !== null) {
                $this->slots->vacate($holder);
            }

            return $this->assignments->save($assignment);
        });
    }

    private function newAssignment(AssignStudyPlanData $data): PlanAssignment
    {
        return match ($data->scope) {
            AssignmentScope::School => PlanAssignment::forSchool($data->schoolId, $data->periodId, $data->planId),
            AssignmentScope::GradeLevel => PlanAssignment::forGradeLevel($data->schoolId, $data->periodId, $data->planId, $this->gradeLevelId($data)),
            AssignmentScope::Offer => $this->offerAssignment($data),
        };
    }

    private function gradeLevelId(AssignStudyPlanData $data): int
    {
        $id = (int) $data->gradeLevelId;

        if ($this->gradeLevels->findForSchool($id, $data->schoolId) === null) {
            throw InvalidAssignment::unknownGradeLevel($id);
        }

        return $id;
    }

    private function offerAssignment(AssignStudyPlanData $data): PlanAssignment
    {
        $offerId = (int) $data->offerId;
        $offer = $this->findOffer($data->schoolId, $data->periodId, $offerId) ?? throw InvalidAssignment::unknownOffer($offerId);

        return PlanAssignment::forOffer($data->schoolId, $data->periodId, $data->planId, $offerId, (int) $offer->gradeLevelId);
    }

    private function findOffer(int $schoolId, int $periodId, int $offerId): ?AcademicOfferSummary
    {
        foreach ($this->offers->allForPeriod($schoolId, $periodId) as $offer) {
            if ($offer->id === $offerId) {
                return $offer;
            }
        }

        return null;
    }
}
