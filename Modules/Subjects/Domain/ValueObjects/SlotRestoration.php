<?php

namespace Modules\Subjects\Domain\ValueObjects;

use Modules\Subjects\Domain\Entities\PlanAssignment;

/**
 * One slot a reactivated plan gets back. `holder` is the assignment that
 * holds the slot now and would be replaced (a conflict), or null.
 */
final readonly class SlotRestoration
{
    public function __construct(
        public PlanAssignment $restored,
        public ?PlanAssignment $holder,
    ) {}

    public function isConflict(): bool
    {
        return $this->holder !== null;
    }
}
