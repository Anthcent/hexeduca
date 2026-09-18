<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('no migration file in Modules/Academic creates a promotion or progression table', function () {
    $migrationFiles = glob(base_path('Modules/Academic/Infrastructure/Database/Migrations/*.php'));

    expect($migrationFiles)->not->toBeEmpty();

    foreach ($migrationFiles as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toMatch('/Schema::create\(\s*[\'"][^\'"]*promo[^\'"]*[\'"]/i')
            ->and($contents)->not->toMatch('/Schema::create\(\s*[\'"][^\'"]*progres[^\'"]*[\'"]/i');
    }
});

test('no table in the migrated schema is named like a promotion/progression table', function () {
    $tables = collect(Schema::getTables())->pluck('name');

    $suspicious = $tables->filter(fn (string $name) => str_contains($name, 'promo') || str_contains($name, 'progres'));

    expect($suspicious)->toBeEmpty();
});

test('progression is derived-only: Matricula is the sole source of a student\'s enrollment history', function () {
    // Academic's schema is exactly the 7 declared tables (plus framework
    // tables) — no dedicated promotion/progression table exists alongside
    // Matricula.
    $academicTables = [
        'niveles_academicos',
        'grados',
        'secciones',
        'periodos_academicos',
        'momentos_academicos',
        'ofertas_academicas',
        'matriculas',
    ];

    foreach ($academicTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasTable('promociones'))->toBeFalse()
        ->and(Schema::hasTable('progressions'))->toBeFalse();
});
