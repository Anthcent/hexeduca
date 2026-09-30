<?php

namespace Modules\TeachingAssignments\Domain\Services;

/**
 * A period is open while it is the active one or has not ended yet. Closed
 * periods are history: their assignments are read-only.
 */
final class OpenPeriodPolicy
{
    /**
     * @param  string  $endsOn  ISO date (Y-m-d)
     * @param  string  $today  ISO date (Y-m-d)
     */
    public static function isOpen(bool $isActive, string $endsOn, string $today): bool
    {
        return $isActive || $endsOn >= $today;
    }
}
