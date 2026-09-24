<?php

namespace Modules\AcademicPeriods\Infrastructure\Http\Resolvers;

use App\AcademicPeriod\Contracts\ActivePeriod;
use App\AcademicPeriod\Contracts\ActivePeriodResolver;
use App\Tenancy\Models\School;
use Modules\AcademicPeriods\Infrastructure\Models\AcademicPeriod as AcademicPeriodModel;

/**
 * Definitive implementation of the platform's ActivePeriodResolver
 * contract, backed by this module's own AcademicPeriod model.
 *
 * Replaces Modules\Academic\Infrastructure\Http\Resolvers\EloquentActivePeriodResolver
 * (Fase 1 provisional implementation, removed in this phase). No legacy
 * bridge: Academic's own models have been migrated to
 * App\AcademicPeriod\Concerns\BelongsToActivePeriod, so nothing reads the
 * old Modules\Academic\Infrastructure\Period\PeriodoContext anymore.
 */
class EloquentActivePeriodResolver implements ActivePeriodResolver
{
    public function activeFor(School $school): ?ActivePeriod
    {
        $period = AcademicPeriodModel::where('is_active', true)->first();

        if ($period === null) {
            return null;
        }

        return new ActivePeriod(
            id: $period->id,
            name: $period->name,
            startsOn: $period->starts_on,
            endsOn: $period->ends_on,
        );
    }
}
