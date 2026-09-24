<?php

use Symfony\Component\Finder\Finder;

/**
 * Architectural boundary test (ver plan §3 y §5, Fase 6).
 *
 * AcademicOffers y Enrollments son módulos HERMANOS de Users (no forman
 * parte de la cadena vertical Tenant->Periodo), así que nunca deben
 * importar `Modules\Users\Infrastructure\...` ni ninguna otra clase de
 * Users fuera de `Modules\Users\Public\...`. Antes de esta fase, ambos
 * importaban `Modules\Users\Infrastructure\Models\User` directo.
 */
function crossModuleUsersImports(string $moduleDir): array
{
    $violations = [];

    if (! is_dir($moduleDir)) {
        return $violations;
    }

    $finder = (new Finder)->files()->in($moduleDir)->name('*.php');

    foreach ($finder as $file) {
        $contents = $file->getContents();

        if (preg_match_all('/^use (Modules\\\\Users\\\\(?!Public\\\\)[^;]+);/m', $contents, $matches)) {
            foreach ($matches[1] as $match) {
                $violations[] = $file->getRelativePathname().' -> '.$match;
            }
        }
    }

    return $violations;
}

test('Modules/AcademicOffers never imports Modules\\Users outside Public\\', function () {
    expect(crossModuleUsersImports(dirname(__DIR__, 2).'/Modules/AcademicOffers'))->toBe([]);
});

test('Modules/Enrollments never imports Modules\\Users outside Public\\', function () {
    expect(crossModuleUsersImports(dirname(__DIR__, 2).'/Modules/Enrollments'))->toBe([]);
});
