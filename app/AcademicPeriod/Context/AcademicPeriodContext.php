<?php

namespace App\AcademicPeriod\Context;

use App\AcademicPeriod\Contracts\ActivePeriod;

/**
 * Holds the currently resolved active academic period for the request
 * lifecycle.
 *
 * Structural mirror of App\Tenancy\TenantContext. Bound as a scoped
 * container singleton (see AppServiceProvider) so each HTTP request gets a
 * fresh instance while console commands and queued jobs default to "no
 * period" unless they explicitly re-bind one.
 */
final class AcademicPeriodContext
{
    private ?ActivePeriod $period = null;

    public function set(ActivePeriod $period): void
    {
        $this->period = $period;
    }

    public function current(): ?ActivePeriod
    {
        return $this->period;
    }

    public function hasPeriod(): bool
    {
        return $this->period !== null;
    }

    public function forget(): void
    {
        $this->period = null;
    }
}
