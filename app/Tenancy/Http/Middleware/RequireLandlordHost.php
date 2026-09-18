<?php

namespace App\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route group to the configured landlord host(s) only.
 *
 * Uses the exact same normalization as ResolveTenant (strtolower on both
 * the request host and the configured landlord_hosts array) so a
 * non-lowercase-configured landlord host is recognized identically at both
 * layers — no silent 404 from a casing mismatch.
 *
 * Currently applied only to /register (see design.md "Only registration is
 * route-constrained to the landlord host, enforced by middleware").
 */
class RequireLandlordHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $landlordHosts = array_map('strtolower', (array) config('tenancy.landlord_hosts', []));

        if (! in_array($host, $landlordHosts, true)) {
            abort(404);
        }

        return $next($request);
    }
}
