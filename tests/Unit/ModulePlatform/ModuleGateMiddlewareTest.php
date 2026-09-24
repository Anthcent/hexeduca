<?php

use App\Http\Middleware\ModuleGateMiddleware;
use Tests\TestCase;

// Needs the Laravel container to resolve the router; opts into the Laravel
// TestCase like tests/Unit/Policies/UserPolicyTest.php does. The actual
// allow/deny behavior needs a DB (ModuleAccess queries the `modules` and
// `school_modules` tables) — see
// tests/Feature/ModulePlatform/ModuleGateMiddlewareBehaviorTest.php.
uses(TestCase::class);

test('the module middleware alias is registered', function () {
    $router = app('router');

    expect($router->getMiddleware())->toHaveKey('module')
        ->and($router->getMiddleware()['module'])->toBe(ModuleGateMiddleware::class);
});
