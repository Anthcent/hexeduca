<?php

namespace Modules\Grades\Domain\Entities;

use Modules\Grades\Domain\Services\MomentGrade;

/**
 * A stored evaluation plan with the ids of its referentes and indicators.
 */
final class EvaluationPlan
{
    /**
     * @param  list<array{id: int, position: int, topic: string, technique: ?string, indicators: list<array{id: int, letter: string, description: string, maxPoints: int}>}>  $referents
     */
    public function __construct(
        public readonly int $id,
        public readonly int $schoolId,
        public readonly int $periodId,
        public readonly int $offerId,
        public readonly int $subjectId,
        public readonly int $momentId,
        public readonly array $referents,
    ) {}

    /**
     * @return array{id: int, letter: string, description: string, maxPoints: int}|null
     */
    public function indicator(int $indicatorId): ?array
    {
        foreach ($this->referents as $referent) {
            foreach ($referent['indicators'] as $indicator) {
                if ($indicator['id'] === $indicatorId) {
                    return $indicator;
                }
            }
        }

        return null;
    }

    public function indicatorCount(): int
    {
        return array_sum(array_map(fn (array $r): int => count($r['indicators']), $this->referents));
    }

    /**
     * A student's standing: referente totals (missing scores count as 0),
     * average, and whether every indicator has a score.
     *
     * @param  array<int, int>  $pointsByIndicator
     * @return array{referentTotals: list<int>, average: float, complete: bool}
     */
    public function standing(array $pointsByIndicator): array
    {
        $totals = [];
        $complete = true;

        foreach ($this->referents as $referent) {
            $sum = 0;

            foreach ($referent['indicators'] as $indicator) {
                if (array_key_exists($indicator['id'], $pointsByIndicator)) {
                    $sum += $pointsByIndicator[$indicator['id']];
                } else {
                    $complete = false;
                }
            }

            $totals[] = $sum;
        }

        return ['referentTotals' => $totals, 'average' => MomentGrade::average($totals), 'complete' => $complete];
    }
}
