<?php

namespace Modules\Subjects\Domain\Services;

use Modules\Subjects\Domain\Entities\PlanAssignment;

/**
 * Picks the assignment that gives an offer its plan: among the current
 * assignments of the offer's period that cover it, the most specific scope
 * wins (offer > grade level > school).
 */
final class EffectivePlanResolver
{
    /**
     * @param  iterable<PlanAssignment>  $currentAssignments  current assignments of one period
     */
    public static function resolve(iterable $currentAssignments, int $offerId, int $offerGradeLevelId): ?PlanAssignment
    {
        $effective = null;

        foreach ($currentAssignments as $assignment) {
            if ($assignment->isReplaced() || ! $assignment->coversOffer($offerId, $offerGradeLevelId)) {
                continue;
            }

            if ($effective === null || $assignment->scope()->precedence() > $effective->scope()->precedence()) {
                $effective = $assignment;
            }
        }

        return $effective;
    }
}
