<?php

namespace Modules\Grades\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\AcademicMoments\Public\Contracts\AcademicMomentReader;
use Modules\Grades\Application\DTOs\Actor;
use Modules\Grades\Application\Services\GradeAccess;
use Modules\Grades\Domain\Exceptions\GradingRefused;
use Modules\Grades\Domain\Repositories\ConductBookRepositoryInterface;
use Modules\Grades\Domain\Repositories\GradeBookRepositoryInterface;

/**
 * Records a student's Convivir letter for a moment (null clears it). The
 * orientador works inside the moment's grading window of an open period;
 * staff may record it at any time. Every change is logged.
 */
final class RecordConduct
{
    public function __construct(
        private readonly ConductBookRepositoryInterface $conduct,
        private readonly GradeBookRepositoryInterface $book,
        private readonly GradeAccess $access,
        private readonly AcademicMomentReader $moments,
    ) {}

    /**
     * @param  array<string, string>  $scale  letter => description
     * @return bool whether the student's letter has now been edited after its first entry
     *
     * @throws GradingRefused
     */
    public function handle(Actor $actor, int $offerId, int $momentId, int $studentId, ?string $letter, array $scale): bool
    {
        $moment = $this->moments->findForSchool($momentId, $actor->schoolId) ?? throw GradingRefused::unknownSlot();
        $offer = $this->access->offer($actor->schoolId, $moment->periodId, $offerId);

        if (! $this->access->canManageConduct($actor, $offer)) {
            throw GradingRefused::notYourSection();
        }

        if (! $actor->isStaff) {
            $this->access->assertPeriodOpen($moment->periodId, $actor->schoolId);

            if (! $this->access->windowOpen($moment)) {
                throw GradingRefused::windowClosed();
            }
        }

        if ($letter !== null && ! array_key_exists($letter, $scale)) {
            throw GradingRefused::unknownLetter(array_keys($scale));
        }

        if (! $this->book->isEnrolled($actor->schoolId, $offerId, $studentId)) {
            throw GradingRefused::notEnrolled();
        }

        return DB::transaction(function () use ($actor, $offerId, $momentId, $studentId, $letter): bool {
            $this->conduct->setLetter($actor->schoolId, $offerId, $momentId, $studentId, $letter, $actor->id);

            return $this->conduct->isEdited($actor->schoolId, $offerId, $momentId, $studentId);
        });
    }
}
