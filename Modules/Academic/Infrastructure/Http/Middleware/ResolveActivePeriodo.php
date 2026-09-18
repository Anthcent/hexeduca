<?php

namespace Modules\Academic\Infrastructure\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Period\PeriodoContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant's active academic period and binds it into
 * PeriodoContext before any period-scoped query runs.
 *
 * MUST run after ResolveTenant in the middleware pipeline: it relies on
 * TenantContext already being bound. Because `PeriodoAcademico` itself
 * `use`s `BelongsToTenant`, the lookup below is already implicitly
 * filtered to the current tenant by TenantScope — no explicit
 * `school_id` clause is needed here.
 *
 * Confirmed business decision: "active period" is a manual `is_active`
 * flag, not date-derived. No tenant bound, or no row flagged active,
 * both result in a no-op (PeriodoContext stays unbound and PeriodoScope
 * silently adds no filter) rather than a request failure — a school
 * between cycles is a valid state, not an error.
 */
class ResolveActivePeriodo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(TenantContext::class)->hasTenant()) {
            return $next($request);
        }

        $periodo = PeriodoAcademico::where('is_active', true)->first();

        if ($periodo !== null) {
            app(PeriodoContext::class)->set($periodo);
        }

        return $next($request);
    }
}
