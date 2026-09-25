<?php

namespace Modules\Subjects\Domain\ValueObjects;

use Modules\Subjects\Domain\Exceptions\ReactivationNotApproved;

/**
 * The outcome of reactivating a plan. Without conflicts a plain
 * confirmation is enough; with conflicts the user must approve all of them
 * at once, or nothing changes.
 */
final readonly class PlanReactivationImpact
{
    /**
     * @param  list<SlotRestoration>  $restorations
     */
    public function __construct(public array $restorations) {}

    /**
     * @return list<SlotRestoration>
     */
    public function conflicts(): array
    {
        return array_values(array_filter($this->restorations, fn (SlotRestoration $r): bool => $r->isConflict()));
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts() !== [];
    }

    /**
     * Ids of the current assignments that reactivation would replace.
     *
     * @return list<int>
     */
    public function replacedHolderIds(): array
    {
        return array_map(fn (SlotRestoration $r): int => (int) $r->holder?->id(), $this->conflicts());
    }

    /**
     * Every conflict must be approved by the id of the assignment it
     * replaces, so a conflict that appeared after the review (and the user
     * never saw) is never applied.
     *
     * @param  list<int>  $approvedHolderIds
     *
     * @throws ReactivationNotApproved
     */
    public function assertApproved(array $approvedHolderIds): void
    {
        $missing = array_diff($this->replacedHolderIds(), array_map('intval', $approvedHolderIds));

        if ($missing !== []) {
            throw ReactivationNotApproved::conflicts(count($missing));
        }
    }
}
