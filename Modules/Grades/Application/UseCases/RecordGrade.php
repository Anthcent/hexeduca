<?php

namespace Modules\Grades\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\AcademicMoments\Public\Contracts\AcademicMomentReader;
use Modules\Grades\Application\DTOs\Actor;
use Modules\Grades\Application\Services\GradeAccess;
use Modules\Grades\Domain\Entities\EvaluationPlan;
use Modules\Grades\Domain\Exceptions\GradingRefused;
use Modules\Grades\Domain\Repositories\GradeBookRepositoryInterface;
use Modules\Grades\Domain\Services\MomentGrade;

/**
 * Records one cell of the grade sheet: an indicator score or the extra
 * (indicatorId null). null points clear the cell. Every change is logged
 * and the student's moment grade is recomputed in the same transaction.
 */
final class RecordGrade
{
    public function __construct(
        private readonly GradeBookRepositoryInterface $book,
        private readonly GradeAccess $access,
        private readonly AcademicMomentReader $moments,
    ) {}

    /**
     * @return array{referentTotals: list<int>, average: float, extra: int, maxExtra: int, final: int, passes: bool, complete: bool}
     *
     * @throws GradingRefused
     */
    public function handle(Actor $actor, int $planId, int $studentId, ?int $indicatorId, ?int $points): array
    {
        $plan = $this->book->findPlan($planId, $actor->schoolId) ?? throw GradingRefused::planNotFound();
        $this->assertCanRecord($actor, $plan, $studentId);

        return DB::transaction(function () use ($actor, $plan, $studentId, $indicatorId, $points): array {
            if ($indicatorId !== null) {
                $indicator = $plan->indicator($indicatorId) ?? throw GradingRefused::unknownIndicator();

                if ($points !== null && ($points < 0 || $points > $indicator['maxPoints'])) {
                    throw GradingRefused::outOfRange($indicator['maxPoints']);
                }

                $this->book->setScore($plan, $indicatorId, $studentId, $points, $actor->id);
            } else {
                $standing = $plan->standing($this->book->studentScores($plan->id, $studentId, $actor->schoolId));
                $max = MomentGrade::maxExtra($standing['average']);

                if ($points !== null && ($points < 0 || $points > $max)) {
                    throw GradingRefused::extraOverCap($max);
                }

                $this->book->setExtra($plan, $studentId, $points, $actor->id);
            }

            return $this->recompute($plan, $studentId, $actor->schoolId);
        });
    }

    /**
     * @return array{referentTotals: list<int>, average: float, extra: int, maxExtra: int, final: int, passes: bool, complete: bool}
     */
    private function recompute(EvaluationPlan $plan, int $studentId, int $schoolId): array
    {
        $standing = $plan->standing($this->book->studentScores($plan->id, $studentId, $schoolId));
        $extra = $this->book->studentExtra($plan->id, $studentId, $schoolId) ?? 0;
        $final = MomentGrade::final($standing['average'], $extra);

        $this->book->saveResult($plan, $studentId, $standing['average'], $extra, $final, $standing['complete']);

        return [
            'referentTotals' => $standing['referentTotals'],
            'average' => $standing['average'],
            'extra' => $extra,
            'maxExtra' => MomentGrade::maxExtra($standing['average']),
            'final' => $final,
            'passes' => MomentGrade::passes($final),
            'complete' => $standing['complete'],
        ];
    }

    /**
     * @throws GradingRefused
     */
    private function assertCanRecord(Actor $actor, EvaluationPlan $plan, int $studentId): void
    {
        $this->access->assertCanManage($actor, $plan->offerId, $plan->subjectId);
        $this->access->assertPeriodOpen($plan->periodId, $actor->schoolId);

        $moment = $this->moments->findForSchool($plan->momentId, $actor->schoolId) ?? throw GradingRefused::unknownSlot();

        if (! $this->access->windowOpen($moment)) {
            throw GradingRefused::windowClosed();
        }

        if (! $this->book->isEnrolled($actor->schoolId, $plan->offerId, $studentId)) {
            throw GradingRefused::notEnrolled();
        }
    }
}
