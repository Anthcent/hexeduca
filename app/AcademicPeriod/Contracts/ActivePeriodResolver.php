<?php

namespace App\AcademicPeriod\Contracts;

use App\Tenancy\Models\School;

/**
 * Platform-neutral contract for resolving a tenant's active academic
 * period. The platform (App\AcademicPeriod\Http\Middleware\ResolveActivePeriod)
 * depends only on this interface — never on a concrete module.
 *
 * Bound by whichever module currently owns the academic period domain
 * (Modules\Academic during Phase 1, Modules\AcademicPeriods from Phase 2).
 */
interface ActivePeriodResolver
{
    public function activeFor(School $school): ?ActivePeriod;
}
