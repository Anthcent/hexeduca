<?php

namespace Modules\Subjects\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Subjects\Application\Services\AssignmentSlots;
use Modules\Subjects\Domain\Exceptions\PlanAssignmentNotFound;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;

/**
 * Frees a slot, so its offers fall back to the next broader scope. An
 * archived plan's assignment is kept as replaced history; any other one is
 * deleted with its exclusions.
 */
final class RemovePlanAssignment
{
    public function __construct(
        private readonly PlanAssignmentRepositoryInterface $assignments,
        private readonly AssignmentSlots $slots,
    ) {}

    /**
     * @throws PlanAssignmentNotFound
     */
    public function handle(int $assignmentId, int $schoolId): void
    {
        DB::transaction(function () use ($assignmentId, $schoolId): void {
            $assignment = $this->assignments->findCurrentInSchool($assignmentId, $schoolId) ?? throw PlanAssignmentNotFound::withId($assignmentId);

            $this->slots->vacate($assignment);
        });
    }
}
