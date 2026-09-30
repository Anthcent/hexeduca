<?php

namespace Modules\Grades\Domain\Repositories;

use Modules\Grades\Domain\Entities\EvaluationPlan;
use Modules\Grades\Domain\ValueObjects\PlanStructure;

interface GradeBookRepositoryInterface
{
    public function findPlan(int $planId, int $schoolId): ?EvaluationPlan;

    public function findPlanForSlot(int $schoolId, int $offerId, int $subjectId, int $momentId): ?EvaluationPlan;

    public function createPlan(int $schoolId, int $periodId, int $offerId, int $subjectId, int $momentId, int $createdBy, PlanStructure $structure): int;

    /**
     * Rebuilds referentes and indicators. Only for a plan without scores.
     */
    public function replaceStructure(int $planId, int $schoolId, PlanStructure $structure): void;

    /**
     * Rewrites topics, techniques and descriptions; the shape is unchanged.
     */
    public function updateTexts(EvaluationPlan $plan, PlanStructure $structure): void;

    public function hasScores(int $planId, int $schoolId): bool;

    public function isEnrolled(int $schoolId, int $offerId, int $studentId): bool;

    /**
     * @return array<int, int> points keyed by indicator id
     */
    public function studentScores(int $planId, int $studentId, int $schoolId): array;

    public function studentExtra(int $planId, int $studentId, int $schoolId): ?int;

    /**
     * Sets (or clears, with null) a score and logs the change. Returns the previous points.
     */
    public function setScore(EvaluationPlan $plan, int $indicatorId, int $studentId, ?int $points, int $actorId): ?int;

    /**
     * Sets (or clears, with null) the extra and logs the change. Returns the previous points.
     */
    public function setExtra(EvaluationPlan $plan, int $studentId, ?int $points, int $actorId): ?int;

    public function saveResult(EvaluationPlan $plan, int $studentId, float $average, int $extra, int $final, bool $complete): void;
}
