<?php

namespace Modules\Grades\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Grades\Application\DTOs\Actor;
use Modules\Grades\Application\Services\GradeAccess;
use Modules\Grades\Domain\Exceptions\GradingRefused;
use Modules\Grades\Domain\Exceptions\InvalidPlan;
use Modules\Grades\Domain\Repositories\GradeBookRepositoryInterface;
use Modules\Grades\Domain\ValueObjects\PlanStructure;

/**
 * Creates or edits the evaluation plan of an offer × subject × moment.
 * Once the plan has scores only its texts may change, so no grade is ever
 * lost or reinterpreted.
 */
final class SaveEvaluationPlan
{
    public function __construct(
        private readonly GradeBookRepositoryInterface $book,
        private readonly GradeAccess $access,
    ) {}

    /**
     * @return int the plan id
     *
     * @throws GradingRefused
     * @throws InvalidPlan
     */
    public function handle(Actor $actor, int $offerId, int $subjectId, int $momentId, PlanStructure $structure): int
    {
        ['moment' => $moment] = $this->access->slot($actor->schoolId, $offerId, $subjectId, $momentId);
        $this->access->assertCanManage($actor, $offerId, $subjectId);
        $this->access->assertPeriodOpen($moment->periodId, $actor->schoolId);

        return DB::transaction(function () use ($actor, $offerId, $subjectId, $momentId, $moment, $structure): int {
            $plan = $this->book->findPlanForSlot($actor->schoolId, $offerId, $subjectId, $momentId);

            if ($plan === null) {
                return $this->book->createPlan($actor->schoolId, $moment->periodId, $offerId, $subjectId, $momentId, $actor->id, $structure);
            }

            if (! $this->book->hasScores($plan->id, $actor->schoolId)) {
                $this->book->replaceStructure($plan->id, $actor->schoolId, $structure);

                return $plan->id;
            }

            $current = PlanStructure::fromArray(array_map(fn (array $r): array => [
                'topic' => $r['topic'],
                'technique' => $r['technique'],
                'indicators' => array_map(fn (array $i): array => ['description' => $i['description'], 'maxPoints' => $i['maxPoints']], $r['indicators']),
            ], $plan->referents));

            if (! $current->sameShapeAs($structure)) {
                throw InvalidPlan::lockedByScores();
            }

            $this->book->updateTexts($plan, $structure);

            return $plan->id;
        });
    }
}
