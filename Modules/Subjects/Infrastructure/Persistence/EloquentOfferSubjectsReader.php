<?php

namespace Modules\Subjects\Infrastructure\Persistence;

use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;
use Modules\Subjects\Domain\Services\EffectivePlanResolver;
use Modules\Subjects\Public\Contracts\OfferSubjectsReader;
use Modules\Subjects\Public\DTOs\OfferSubjectDTO;

final class EloquentOfferSubjectsReader implements OfferSubjectsReader
{
    public function __construct(
        private readonly PlanAssignmentRepositoryInterface $assignments,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly AcademicOfferReader $offers,
    ) {}

    public function forPeriod(int $schoolId, int $periodId): array
    {
        $assignments = $this->assignments->currentForPeriod($schoolId, $periodId);
        $effective = [];

        foreach ($this->offers->allForPeriod($schoolId, $periodId) as $offer) {
            $effective[$offer->id] = [
                'gradeLevelId' => (int) $offer->gradeLevelId,
                'assignment' => EffectivePlanResolver::resolve($assignments, $offer->id, (int) $offer->gradeLevelId),
            ];
        }

        $used = array_filter(array_column($effective, 'assignment'));
        $subjectsByPlan = $this->subjects->forPlans(array_values(array_unique(array_map(fn (PlanAssignment $a): int => $a->planId(), $used))), $schoolId);
        $excluded = $this->assignments->excludedSubjectIdsFor(array_values(array_map(fn (PlanAssignment $a): int => (int) $a->id(), $used)), $schoolId);

        $result = [];

        foreach ($effective as $offerId => ['gradeLevelId' => $gradeLevelId, 'assignment' => $assignment]) {
            if ($assignment === null) {
                $result[$offerId] = [];

                continue;
            }

            $skip = array_flip($excluded[(int) $assignment->id()] ?? []);

            $result[$offerId] = array_values(array_map(
                fn (Subject $subject): OfferSubjectDTO => new OfferSubjectDTO(
                    id: (int) $subject->id(),
                    planId: $subject->planId(),
                    gradeLevelId: $subject->gradeLevelId(),
                    name: $subject->name(),
                    code: $subject->code(),
                    weeklyHours: $subject->weeklyHours(),
                ),
                array_filter(
                    $subjectsByPlan[$assignment->planId()] ?? [],
                    fn (Subject $subject): bool => $subject->gradeLevelId() === $gradeLevelId
                        && ! $subject->isArchived()
                        && ! isset($skip[(int) $subject->id()]),
                ),
            ));
        }

        return $result;
    }

    public function forOffer(int $schoolId, int $periodId, int $offerId): array
    {
        return $this->forPeriod($schoolId, $periodId)[$offerId] ?? [];
    }
}
