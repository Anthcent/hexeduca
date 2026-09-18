<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\SecurityPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Route::middleware(['web', 'auth:sanctum'])->post('/_test/csrf-protected', fn () => response()->noContent());
    Route::middleware(['web', 'throttle:login'])->post('/_test/login-throttle', fn () => response()->noContent());
    Route::middleware('web')->get('/_test/request-context', fn (Request $request) => [
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
    ]);
    Route::middleware('api')->get('/_test/api-throttle', fn () => response()->noContent());
});

test('stateful post without a csrf token is rejected', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/',
    ])->post('/_test/csrf-protected')->assertStatus(419);
});

test('named login limiter rejects requests above its threshold', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post('/_test/login-throttle', ['email' => 'limited@example.test'])
            ->assertNoContent();
    }

    $this->post('/_test/login-throttle', ['email' => 'limited@example.test'])
        ->assertTooManyRequests();
});

test('https responses outside local environment include hsts', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->get('https://localhost/')
        ->assertHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );
});

test('hsts is omitted for insecure and local responses', function () {
    $this->app->detectEnvironment(fn () => 'production');
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

    $this->app->detectEnvironment(fn () => 'local');
    $this->get('https://localhost/')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('untrusted forwarded headers cannot control client ip or scheme', function () {
    SymfonyRequest::setTrustedProxies([], SymfonyRequest::HEADER_X_FORWARDED_FOR | SymfonyRequest::HEADER_X_FORWARDED_PROTO);

    $this->withHeaders([
        'X-Forwarded-For' => '203.0.113.10',
        'X-Forwarded-Proto' => 'https',
    ])->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->getJson('/_test/request-context')
        ->assertExactJson(['ip' => '198.51.100.20', 'secure' => false]);
});

test('configured trusted proxies honor forwarded client ip and scheme', function () {
    SymfonyRequest::setTrustedProxies(['10.0.0.0/8'], SymfonyRequest::HEADER_X_FORWARDED_FOR | SymfonyRequest::HEADER_X_FORWARDED_PROTO);

    try {
        $request = SymfonyRequest::create('http://localhost/', server: [
            'REMOTE_ADDR' => '10.1.2.3',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        expect($request->getClientIp())->toBe('203.0.113.10')
            ->and($request->isSecure())->toBeTrue();
    } finally {
        SymfonyRequest::setTrustedProxies([], SymfonyRequest::HEADER_X_FORWARDED_FOR | SymfonyRequest::HEADER_X_FORWARDED_PROTO);
    }
});

test('trusted proxy deployment setting parses addresses and cidrs', function () {
    Env::getRepository()->set('TRUSTED_PROXIES', '10.0.0.0/8, 192.0.2.10');

    try {
        expect(SecurityPolicy::trustedProxies())->toBe(['10.0.0.0/8', '192.0.2.10']);
    } finally {
        Env::getRepository()->clear('TRUSTED_PROXIES');
    }
});

test('api limiter consumes exactly one quota unit per request', function () {
    RateLimiter::for('api', fn () => Limit::perMinute(3)->by('quota-boundary'));

    for ($request = 1; $request <= 3; $request++) {
        $this->getJson('/_test/api-throttle')->assertNoContent();
    }

    $this->getJson('/_test/api-throttle')->assertTooManyRequests();
});

test('api and web middleware preserve the required security order', function () {
    $groups = app(Router::class)->getMiddlewareGroups();

    $stateful = array_search(EnsureFrontendRequestsAreStateful::class, $groups['api'], true);
    $throttle = array_search('throttle:api', $groups['api'], true);
    $bindings = array_search(SubstituteBindings::class, $groups['api'], true);

    expect($stateful)->toBeInt()
        ->and($throttle)->toBeInt()
        ->and($bindings)->toBeInt()
        ->and($stateful)->toBeLessThan($throttle)
        ->and($throttle)->toBeLessThan($bindings)
        ->and($groups['web'])->toContain(ValidateCsrfToken::class, HandleInertiaRequests::class);
});

test('local responses retain safe session cookie defaults', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('session.secure'))->toBeFalse()
        ->and(app()->environment())->toBe('testing');
});

test('production responses emit secure session cookies in an isolated process', function () {
    $process = new Process([
        PHP_BINARY,
        base_path('vendor/bin/phpunit'),
        '--configuration',
        base_path('phpunit.xml'),
        base_path('tests/Isolated/ProductionSessionCookieContract.php'),
    ], base_path());
    $process->setTimeout(120);
    $process->mustRun();

    expect($process->getOutput())->toContain('OK (1 test, 6 assertions)');
});

test('production cookie bootstrap cannot leak into the normal test process', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('session.secure'))->toBeFalse()
        ->and(Env::get('APP_ENV'))->toBe('testing')
        ->and(Env::get('SESSION_SECURE_COOKIE'))->toBeNull();
});
