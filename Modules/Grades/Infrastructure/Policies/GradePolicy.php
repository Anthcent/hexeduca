<?php

namespace Modules\Grades\Infrastructure\Policies;

use Modules\Grades\Infrastructure\Models\Grade;
use Modules\Users\Infrastructure\Models\User;

/**
 * Resource-level authorization (plan §11, punto 16 del pedido): a
 * `teacher` role is NOT sufficient on its own to modify a grade — must
 * also be the same school, the same active academic period, and the
 * teacher actually assigned to that grade's row (`teacher_id`). Compared
 * by plain id, no cross-module lookup needed for authorization.
 *
 * `staff/admin` bypasses the "assigned teacher" check but still must match
 * school + active period (no cross-tenant/cross-period edits from staff
 * either).
 *
 * Registered via `Gate::policy(Grade::class, GradePolicy::class)` in
 * GradesServiceProvider::boot() — same pattern as
 * Modules\Users\Infrastructure\Policies\UserPolicy.
 */
class GradePolicy
{
    public function update(User $user, Grade $grade): bool
    {
        return $this->authorizeForResource($user, $grade);
    }

    public function delete(User $user, Grade $grade): bool
    {
        return $this->authorizeForResource($user, $grade);
    }

    private function authorizeForResource(User $user, Grade $grade): bool
    {
        if ($user->school_id === null || $user->school_id !== $grade->school_id) {
            return false;
        }

        $activePeriod = current_academic_period();

        if ($activePeriod === null || $grade->periodo_academico_id !== $activePeriod->id) {
            return false;
        }

        if ($user->hasRole('staff/admin')) {
            return true;
        }

        return $user->hasRole('teacher') && $user->id === $grade->teacher_id;
    }
}
