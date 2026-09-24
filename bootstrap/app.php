<?php

use App\AcademicPeriod\Http\Middleware\ResolveActivePeriod;
use App\Http\Middleware\AddStrictTransportSecurity;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\SecurityPolicy;
use App\Tenancy\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: SecurityPolicy::trustedProxies());

        $middleware->append(AddStrictTransportSecurity::class);

        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class,
            ResolveTenant::class,
            ResolveActivePeriod::class,
            'throttle:api',
            SubstituteBindings::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            ResolveTenant::class,
            ResolveActivePeriod::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
