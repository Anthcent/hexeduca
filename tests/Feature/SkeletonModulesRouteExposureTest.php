<?php

use Illuminate\Support\Facades\Route;

test('skeleton modules expose no HTTP routes', function (string $module, string $uriPrefix) {
    $routes = collect(Route::getRoutes())->filter(function ($route) use ($module, $uriPrefix): bool {
        $controller = (string) ($route->getAction('controller') ?? '');

        return str_starts_with($controller, "Modules\\{$module}\\")
            || $route->uri() === $uriPrefix
            || str_starts_with($route->uri(), $uriPrefix.'/');
    });

    expect($routes)->toHaveCount(0);
})->with([
    'Files' => ['Files', 'files'],
    'Notifications' => ['Notifications', 'notifications'],
    'Schedule' => ['Schedule', 'schedule'],
]);
