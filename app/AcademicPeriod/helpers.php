<?php

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\AcademicPeriod\Contracts\ActivePeriod;

if (! function_exists('current_academic_period')) {
    /**
     * Resolve the currently bound active academic period, or null when no
     * tenant/period is bound (landlord, console, or a school between
     * cycles).
     */
    function current_academic_period(): ?ActivePeriod
    {
        return app(AcademicPeriodContext::class)->current();
    }
}
