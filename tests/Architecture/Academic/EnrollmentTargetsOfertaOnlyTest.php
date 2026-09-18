<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Academic\Infrastructure\Models\Matricula;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the matriculas table has no grado_id or seccion_id column', function () {
    $columns = Schema::getColumnListing('matriculas');

    expect($columns)->not->toContain('grado_id')
        ->and($columns)->not->toContain('seccion_id');
});

test('the Matricula model exposes no direct relation to Grado or Seccion', function () {
    $matricula = new Matricula;

    expect(method_exists($matricula, 'grado'))->toBeFalse()
        ->and(method_exists($matricula, 'seccion'))->toBeFalse();
});

test('enrollment can only target an OfertaAcademica: matriculas has a foreign key to ofertas_academicas', function () {
    $foreignKeys = collect(Schema::getForeignKeys('matriculas'))
        ->flatMap(fn (array $fk) => $fk['columns']);

    expect($foreignKeys)->toContain('oferta_academica_id');
});
