<?php

namespace Modules\AcademicPeriods\Infrastructure\Broadcasting;

use App\Tenancy\Models\School;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\AcademicPeriods\Infrastructure\Models\AcademicPeriod;

/**
 * The single sanctioned way every period-scoped broadcast channel names
 * and authorizes a period-scoped channel.
 *
 * Port of Modules\Academic\Infrastructure\Period\Broadcasting\PeriodoChannel
 * (removed in this phase — this module now owns the period domain).
 * Structural mirror of App\Tenancy\Broadcasting\TenantChannel. Reverb runs
 * as a separate process with no visibility into AcademicPeriodScope or
 * AcademicPeriodContext, so isolation is enforced entirely here, inside the
 * Broadcast::channel() authorization closure — never via middleware, and
 * never against AcademicPeriodContext (see authorize()). The channel name
 * is always built from the row's own persisted `periodo_academico_id` (see
 * name()), never from the request-scoped context singleton, so queued
 * broadcasts remain correct with no HTTP kernel present.
 */
final class AcademicPeriodChannel
{
    /**
     * Tenant segment prefix shared with TenantChannel.
     */
    public const PREFIX = 'school';

    /**
     * Period segment name, placed after the school segment.
     */
    public const PERIOD_SEGMENT = 'periodo';

    /**
     * Build the concrete wire base name:
     * "school.7.periodo.3.matriculas.42" (no "private-" prefix — Laravel
     * applies that implicitly).
     */
    public static function name(int|School $school, int|AcademicPeriod $period, string $resource, int|string $id): string
    {
        $schoolId = $school instanceof School ? $school->id : $school;
        $periodId = $period instanceof AcademicPeriod ? $period->id : $period;

        return self::PREFIX.'.'.$schoolId.'.'.self::PERIOD_SEGMENT.'.'.$periodId.'.'.$resource.'.'.$id;
    }

    /**
     * Build the registration pattern for Broadcast::channel(), e.g.
     * "school.{schoolId}.periodo.{periodoId}.matriculas.{id}".
     */
    public static function pattern(string $resource): string
    {
        return self::PREFIX.'.{schoolId}.'.self::PERIOD_SEGMENT.'.{periodoId}.'.$resource.'.{id}';
    }

    /**
     * The ONLY sanctioned authorization predicate.
     *
     * MUST return false immediately when $user->school_id === null (explicit
     * null check, no cast) — never rely on `(int) null === (int) $schoolId`,
     * since PHP casts both null and non-numeric strings to 0. MUST also
     * reject a $schoolId or $periodId that is not a well-formed positive
     * integer (via ctype_digit((string) $value)) before comparing, so
     * malformed route segments like "abc" cannot coerce to 0 and collide
     * with a null school_id.
     *
     * Deliberately never reads AcademicPeriodContext: it is request-scoped
     * and absent for broadcasts emitted from queued jobs or console
     * commands, while $user->school_id is valid in every execution
     * context. The $periodId segment is trusted because it is always
     * sourced from the row's own persisted `periodo_academico_id` at
     * name() build time, not user input — this predicate only guards the
     * school dimension plus well-formedness of both segments.
     */
    public static function authorize(Authenticatable $user, int|string $schoolId, int|string $periodId): bool
    {
        $userSchoolId = $user->school_id ?? null;

        if ($userSchoolId === null) {
            return false;
        }

        if (! ctype_digit((string) $schoolId) || ! ctype_digit((string) $periodId)) {
            return false;
        }

        return (int) $userSchoolId === (int) $schoolId;
    }
}
