<?php

/**
 * Architectural boundary test (ver C:\Users\DELL 3380\.claude\plans\soft-imagining-pascal.md §1 y §12).
 *
 * Antes de la Fase 1, `bootstrap/app.php` importaba
 * `Modules\Academic\Infrastructure\Http\Middleware\ResolveActivePeriodo`
 * directo — la plataforma (código que corre para TODA request web/api)
 * dependía de una clase interna de un módulo de negocio.
 *
 * Desde la Fase 1, `bootstrap/app.php` importa únicamente
 * `App\AcademicPeriod\Http\Middleware\ResolveActivePeriod` (plataforma
 * neutral). Este test ahora exige, sin excepciones, que `bootstrap/app.php`
 * nunca vuelva a importar una clase de `Modules\`.
 */
test('bootstrap/app.php imports the platform-neutral ResolveActivePeriod middleware', function () {
    $contents = file_get_contents(dirname(__DIR__, 2).'/bootstrap/app.php');

    expect($contents)->toContain('use App\AcademicPeriod\Http\Middleware\ResolveActivePeriod;')
        ->and($contents)->toMatch("/group\('api', \[.*ResolveTenant::class,\s*ResolveActivePeriod::class,/s")
        ->and($contents)->toMatch('/web\(append: \[.*ResolveTenant::class,\s*ResolveActivePeriod::class,/s');
});

test('bootstrap/app.php never imports a class from Modules\\', function () {
    $contents = file_get_contents(dirname(__DIR__, 2).'/bootstrap/app.php');

    preg_match_all('/^use (Modules\\\\[^;]+);/m', $contents, $matches);

    expect($matches[1])->toBe([]);
});
