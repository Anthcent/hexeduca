<?php

use App\Http\Middleware\ModuleGateMiddleware;
use Illuminate\Http\Request;
use Tests\TestCase;

// Needs the Laravel request/response cycle; opts into the Laravel
// TestCase like tests/Unit/Policies/UserPolicyTest.php does.
uses(TestCase::class);

test('the module gate middleware is a pass-through placeholder', function () {
    $middleware = new ModuleGateMiddleware;
    $request = Request::create('/whatever', 'GET');

    $response = $middleware->handle($request, fn (Request $r) => response('ok'), 'any-key');

    expect($response->getContent())->toBe('ok');
});

test('the module middleware alias is registered', function () {
    $router = app('router');

    expect($router->getMiddleware())->toHaveKey('module')
        ->and($router->getMiddleware()['module'])->toBe(ModuleGateMiddleware::class);
});
