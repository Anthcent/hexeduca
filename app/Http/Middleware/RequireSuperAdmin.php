<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Landlord-only routes. The super-admin is a user flag, not a role: roles
 * belong to a school, and the landlord operator belongs to none.
 */
class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $request->user()?->is_super_admin, 403);

        return $next($request);
    }
}
