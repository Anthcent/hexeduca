<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pass-through placeholder for the `module:{key}` route middleware.
 *
 * Generated module routes (see stubs/modules/routes/*.stub) already
 * reference `module:{key}` so they do not need editing once real gating
 * lands. R2 (sdd/module-developer-platform) replaces this with the real
 * ModuleAccess resolver: active AND (core OR school entitled), see
 * sdd/module-developer-platform/tasks R2.3-R2.4.
 */
class ModuleGateMiddleware
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        return $next($request);
    }
}
