<?php

namespace App\Tenancy\Http\Middleware;

use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Classifies the request host into landlord / tenant / unrecognized and
 * binds the resolved School into TenantContext before any controller or
 * tenant-scoped query runs.
 *
 * Fail-closed, with cache invalidated synchronously on School changes via
 * SchoolCacheObserver: an unrecognized or inactive subdomain aborts with
 * 404 instead of silently falling through to an implicit/default tenant,
 * and an admin deactivating a School takes effect immediately rather than
 * waiting out the cache TTL.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $baseDomain = strtolower((string) config('tenancy.base_domain'));
        $landlordHosts = array_map('strtolower', (array) config('tenancy.landlord_hosts', []));

        if (in_array($host, $landlordHosts, true)) {
            // Landlord host: no tenant bound, global scope no-ops.
            return $next($request);
        }

        $label = $this->subdomainLabel($host, $baseDomain);

        if ($label === null || $label === '' || $label === 'www') {
            // Bare base domain, "www", or a foreign host with no
            // extractable subdomain label is not a tenant lookup.
            abort(404);
        }

        // Cache both positive (active tenant) and negative (nonexistent or
        // inactive subdomain) lookups. Cache::remember() treats a raw
        // cached `null` as a miss (it checks is_null() internally), which
        // would re-run this query on EVERY request for an unknown/inactive
        // label — exactly the bot/flood-scanning traffic this cache exists
        // to protect against. The `false` sentinel avoids that.
        $school = Cache::remember(
            "tenant:school:{$label}",
            now()->addMinutes(5),
            fn () => School::withoutTenantScope()
                ->where('subdomain', $label)
                ->where('is_active', true)
                ->first() ?? false
        );
        $school = $school ?: null;

        if (! $school) {
            // Unknown or inactive subdomain: fail closed.
            abort(404);
        }

        app(TenantContext::class)->set($school);

        return $next($request);
    }

    /**
     * Strip the configured base domain suffix from the host and return the
     * remaining leftmost label. Returns null when the host does not belong
     * to the base domain at all (foreign/unrecognized host).
     */
    private function subdomainLabel(string $host, string $baseDomain): ?string
    {
        if ($baseDomain === '' || ! Str::endsWith($host, '.'.$baseDomain)) {
            return null;
        }

        return Str::beforeLast($host, '.'.$baseDomain);
    }
}
