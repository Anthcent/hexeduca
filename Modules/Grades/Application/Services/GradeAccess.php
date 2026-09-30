<?php

namespace Modules\Grades\Application\Services;

use Modules\AcademicMoments\Public\Contracts\AcademicMomentReader;
use Modules\AcademicMoments\Public\DTOs\AcademicMomentDTO;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\AcademicOffers\Public\DTOs\AcademicOfferSummary;
use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\Grades\Application\DTOs\Actor;
use Modules\Grades\Domain\Exceptions\GradingRefused;
use Modules\Subjects\Public\Contracts\OfferSubjectsReader;
use Modules\Subjects\Public\DTOs\OfferSubjectDTO;
use Modules\TeachingAssignments\Public\Contracts\TeachingAssignmentReader;

/**
 * Every server-side check a grade action needs: the slot is real and the
 * school's, the actor teaches it, the period is open and the moment's
 * grading window is open.
 */
final class GradeAccess
{
    public function __construct(
        private readonly AcademicMomentReader $moments,
        private readonly AcademicOfferReader $offers,
        private readonly AcademicPeriodReader $periods,
        private readonly OfferSubjectsReader $offerSubjects,
        private readonly TeachingAssignmentReader $teaching,
    ) {}

    /**
     * @return array{moment: AcademicMomentDTO, offer: AcademicOfferSummary, subject: OfferSubjectDTO}
     *
     * @throws GradingRefused
     */
    public function slot(int $schoolId, int $offerId, int $subjectId, int $momentId): array
    {
        $moment = $this->moments->findForSchool($momentId, $schoolId) ?? throw GradingRefused::unknownSlot();

        $offer = null;

        foreach ($this->offers->allForPeriod($schoolId, $moment->periodId) as $candidate) {
            if ($candidate->id === $offerId) {
                $offer = $candidate;
            }
        }

        $subject = null;

        foreach ($offer ? $this->offerSubjects->forOffer($schoolId, $moment->periodId, $offerId) : [] as $candidate) {
            if ($candidate->id === $subjectId) {
                $subject = $candidate;
            }
        }

        if ($offer === null || $subject === null) {
            throw GradingRefused::unknownSlot();
        }

        return ['moment' => $moment, 'offer' => $offer, 'subject' => $subject];
    }

    public function canManage(Actor $actor, int $offerId, int $subjectId): bool
    {
        return $actor->isStaff || $this->teaching->teaches($actor->schoolId, $actor->id, $offerId, $subjectId);
    }

    /**
     * @throws GradingRefused
     */
    public function assertCanManage(Actor $actor, int $offerId, int $subjectId): void
    {
        if (! $this->canManage($actor, $offerId, $subjectId)) {
            throw GradingRefused::notYourSubject();
        }
    }

    public function periodOpen(int $periodId, int $schoolId): bool
    {
        $period = $this->periods->findForSchool($periodId, $schoolId);

        return $period !== null && ($period->isActive || $period->endsOn >= $this->today());
    }

    /**
     * @throws GradingRefused
     */
    public function assertPeriodOpen(int $periodId, int $schoolId): void
    {
        if (! $this->periodOpen($periodId, $schoolId)) {
            throw GradingRefused::periodClosed();
        }
    }

    /**
     * Grades can be entered only between the moment's grading dates. A
     * moment without dates is closed.
     */
    public function windowOpen(AcademicMomentDTO $moment): bool
    {
        $today = $this->today();

        return $moment->gradingOpensOn !== null
            && $moment->gradingClosesOn !== null
            && $moment->gradingOpensOn <= $today
            && $today <= $moment->gradingClosesOn;
    }

    private function today(): string
    {
        return now()->toDateString();
    }
}
