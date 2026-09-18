<?php

namespace Tests\Isolated;

use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionSessionCookieContract extends TestCase
{
    public function test_production_response_emits_secure_session_cookie_without_override(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['APP_ENV'] = 'production';
        putenv('APP_ENV=production');
        Env::getRepository()->set('APP_ENV', 'production');

        unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);
        putenv('SESSION_SECURE_COOKIE');
        Env::getRepository()->clear('SESSION_SECURE_COOKIE');
        $this->refreshApplication();

        Route::middleware('web')->get('/_test/session-cookie', function (Request $request) {
            $request->session()->put('cookie-contract', true);

            return response()->noContent();
        });

        $response = $this->get('https://localhost/_test/session-cookie');
        $sessionCookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertSame('production', app()->environment());
        $this->assertTrue(config('session.secure'));
        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', $sessionCookie->getSameSite());
    }
}
