<?php

namespace App\Http\Middleware;

use App\ModulePlatform\Services\ModuleAccess;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Denies access to a route unless its module is active AND either core or
 * the current school is entitled to it (see ModuleAccess::allows()).
 *
 * Returns 404 rather than 403 so a module that isn't available doesn't leak
 * its existence to a school that can't use it.
 */
class ModuleGateMiddleware
{
    public function __construct(
        private readonly ModuleAccess $moduleAccess,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        if (! $this->moduleAccess->allows($key, $this->tenantContext->current())) {
            abort(404);
        }

        return $next($request);
    }
}
