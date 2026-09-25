<?php

namespace Modules\Subjects\Domain\Services;

use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\ValueObjects\PlanReactivationImpact;
use Modules\Subjects\Domain\ValueObjects\SlotRestoration;

/**
 * Computes what reactivating an archived plan changes: each slot the plan
 * held (in a period that is still open) and lost while archived is
 * restored. When another assignment holds that slot now, restoring it
 * replaces that holder: a conflict the user must approve.
 */
final class PlanReactivationPlanner
{
    /**
     * @param  iterable<PlanAssignment>  $replacedAssignments  the plan's replaced assignments in open periods
     * @param  array<string, PlanAssignment>  $currentHoldersBySlot  current assignments keyed by slotKey()
     */
    public static function plan(int $planId, iterable $replacedAssignments, array $currentHoldersBySlot): PlanReactivationImpact
    {
        // The latest replaced assignment per slot is the one to restore.
        $latestBySlot = [];

        foreach ($replacedAssignments as $assignment) {
            if ($assignment->planId() !== $planId || ! $assignment->isReplaced()) {
                continue;
            }

            $key = $assignment->slotKey();

            if (! isset($latestBySlot[$key]) || $assignment->id() > $latestBySlot[$key]->id()) {
                $latestBySlot[$key] = $assignment;
            }
        }

        $restorations = [];

        foreach ($latestBySlot as $key => $assignment) {
            $holder = $currentHoldersBySlot[$key] ?? null;

            // The plan already holds the slot again: nothing to restore.
            if ($holder !== null && $holder->planId() === $planId) {
                continue;
            }

            $restorations[] = new SlotRestoration($assignment, $holder);
        }

        return new PlanReactivationImpact($restorations);
    }
}
