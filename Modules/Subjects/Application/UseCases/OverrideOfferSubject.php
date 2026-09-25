<?php

namespace Modules\Subjects\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\Subjects\Application\DTOs\AssignStudyPlanData;
use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Exceptions\InvalidAssignment;
use Modules\Subjects\Domain\Exceptions\PlanAssignmentNotFound;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Services\EffectivePlanResolver;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

/**
 * "Only this section": changes one subject for a single offer whose plan
 * comes from a broader scope. It creates an offer-scope assignment of the
 * same plan, copies the inherited exclusions (so the section starts
 * identical), then applies the change there. When the offer already has
 * its own assignment, the change goes straight to it.
 */
final class OverrideOfferSubject
{
    public function __construct(
        private readonly PlanAssignmentRepositoryInterface $assignments,
        private readonly AcademicOfferReader $offers,
        private readonly AssignStudyPlan $assign,
        private readonly SetSubjectExclusion $setExclusion,
    ) {}

    /**
     * @throws InvalidAssignment when the offer is not in the period or has no effective plan
     * @throws StudyPlanNotFound
     * @throws PlanAssignmentNotFound
     * @throws RecordArchived when the effective plan is archived
     */
    public function handle(int $schoolId, int $periodId, int $offerId, int $subjectId, bool $excluded): PlanAssignment
    {
        $offerGradeLevelId = $this->offerGradeLevelId($schoolId, $periodId, $offerId);

        return DB::transaction(function () use ($schoolId, $periodId, $offerId, $offerGradeLevelId, $subjectId, $excluded): PlanAssignment {
            $effective = EffectivePlanResolver::resolve($this->assignments->currentForPeriod($schoolId, $periodId), $offerId, $offerGradeLevelId)
                ?? throw InvalidAssignment::noEffectivePlan($offerId);

            $target = $effective;

            if ($effective->scope() !== AssignmentScope::Offer) {
                $target = $this->assign->handle(new AssignStudyPlanData(
                    schoolId: $schoolId,
                    periodId: $periodId,
                    planId: $effective->planId(),
                    scope: AssignmentScope::Offer,
                    offerId: $offerId,
                ));
                $this->assignments->copyExclusions((int) $effective->id(), (int) $target->id(), $schoolId);
            }

            $this->setExclusion->handle((int) $target->id(), $subjectId, $excluded, $schoolId);

            return $target;
        });
    }

    private function offerGradeLevelId(int $schoolId, int $periodId, int $offerId): int
    {
        foreach ($this->offers->allForPeriod($schoolId, $periodId) as $offer) {
            if ($offer->id === $offerId) {
                return (int) $offer->gradeLevelId;
            }
        }

        throw InvalidAssignment::unknownOffer($offerId);
    }
}
