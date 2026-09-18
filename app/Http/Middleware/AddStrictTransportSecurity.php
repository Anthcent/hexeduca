<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddStrictTransportSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('local') && $request->isSecure()) {
            $directives = ['max-age='.config('security.hsts.max_age')];

            if (config('security.hsts.include_subdomains')) {
                $directives[] = 'includeSubDomains';
            }

            if (config('security.hsts.preload')) {
                $directives[] = 'preload';
            }

            $response->headers->set('Strict-Transport-Security', implode('; ', $directives));
        }

        return $response;
    }
}
