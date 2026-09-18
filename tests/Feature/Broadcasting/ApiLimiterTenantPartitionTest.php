<?php

use App\Tenancy\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('api')->get('/_test/tenant-api-throttle', fn () => response()->noContent());
    Route::middleware(['web', 'throttle:login'])->post('/_test/tenant-login-throttle', fn () => response()->noContent());

    config()->set('security.rate_limits.api_per_minute', 3);
});

function tenantApiUrl(string $subdomain): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://{$subdomain}.{$baseDomain}/_test/tenant-api-throttle";
}

test('a single tenant api bucket still enforces its own limit', function () {
    $school = School::factory()->create();

    for ($request = 1; $request <= 3; $request++) {
        $this->get(tenantApiUrl($school->subdomain))->assertNoContent();
    }

    $this->get(tenantApiUrl($school->subdomain))->assertTooManyRequests();
});

test('two tenants consume independent api buckets', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    for ($request = 1; $request <= 3; $request++) {
        $this->get(tenantApiUrl($schoolOne->subdomain))->assertNoContent();
    }

    // School one's bucket is exhausted, but school two has not made a
    // single request yet — it must not be throttled by school one.
    $this->get(tenantApiUrl($schoolOne->subdomain))->assertTooManyRequests();
    $this->get(tenantApiUrl($schoolTwo->subdomain))->assertNoContent();
});

test('landlord or unresolved tenant requests fall back to the existing user or ip key', function () {
    $landlordHost = config('tenancy.landlord_hosts')[0]
        ?? 'admin.'.config('tenancy.base_domain');

    // No school resolved on the landlord host: the fallback key is the
    // request IP, so two different IPs must get independent buckets,
    // exactly as before this change — proving the null-tenant path is
    // unaffected by the tenant-aware key composition.
    for ($request = 1; $request <= 3; $request++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get("http://{$landlordHost}/_test/tenant-api-throttle")
            ->assertNoContent();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->get("http://{$landlordHost}/_test/tenant-api-throttle")
        ->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->get("http://{$landlordHost}/_test/tenant-api-throttle")
        ->assertNoContent();
});

test('the login limiter is unaffected by the tenant-aware api limiter change', function () {
    $landlordHost = config('tenancy.landlord_hosts')[0]
        ?? 'admin.'.config('tenancy.base_domain');

    // The 'web' group also runs ResolveTenant, so this route must be hit on
    // a recognized host (landlord) or it 404s before the limiter even runs —
    // that host classification is orthogonal to what this test verifies.
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post("http://{$landlordHost}/_test/tenant-login-throttle", ['email' => 'tenant-login@example.test'])
            ->assertNoContent();
    }

    $this->post("http://{$landlordHost}/_test/tenant-login-throttle", ['email' => 'tenant-login@example.test'])
        ->assertTooManyRequests();
});

test('reverb horizontal scaling is off by default', function () {
    expect(config('reverb.servers.reverb.scaling.enabled'))->toBeFalse();
});
