<?php

namespace App\AcademicPeriod\Http\Middleware;

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\AcademicPeriod\Contracts\ActivePeriodResolver;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant's active academic period and binds it into
 * AcademicPeriodContext before any period-scoped query runs.
 *
 * Platform-neutral: depends only on App\AcademicPeriod\Contracts\ActivePeriodResolver,
 * never on a concrete module. The binding for that interface is registered
 * by whichever module currently owns the academic period domain — see
 * Modules\Academic\Infrastructure\Providers\AcademicServiceProvider during
 * the Phase 1 bridge, Modules\AcademicPeriods from Phase 2 onward.
 *
 * MUST run after ResolveTenant in the middleware pipeline: it relies on
 * TenantContext already being bound. No tenant bound, or the resolver
 * returning null (no row flagged active), both result in a no-op
 * (AcademicPeriodContext stays unbound and AcademicPeriodScope silently
 * adds no filter) rather than a request failure — a school between cycles
 * is a valid state, not an error.
 */
class ResolveActivePeriod
{
    public function __construct(
        private readonly ActivePeriodResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! app(TenantContext::class)->hasTenant()) {
            return $next($request);
        }

        $period = $this->resolver->activeFor(app(TenantContext::class)->current());

        if ($period !== null) {
            app(AcademicPeriodContext::class)->set($period);
        }

        return $next($request);
    }
}
