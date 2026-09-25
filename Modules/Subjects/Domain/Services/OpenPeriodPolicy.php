<?php

namespace Modules\Subjects\Domain\Services;

/**
 * Academic periods have no "closed" state, so a period counts as open
 * (its assignments may still change) when it is the active one or it has
 * not ended yet. Past periods are history: reactivation never touches them.
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
