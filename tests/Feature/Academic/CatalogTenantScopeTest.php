<?php

use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\NivelAcademico;
use Modules\Academic\Infrastructure\Models\Seccion;

uses(RefreshDatabase::class);

test('nivel academico query returns only the current school rows', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $nivelOne = NivelAcademico::factory()->create(['school_id' => $schoolOne->id]);
    NivelAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    app(TenantContext::class)->set($schoolOne);

    expect(NivelAcademico::all())->toHaveCount(1)
        ->and(NivelAcademico::first()->id)->toBe($nivelOne->id);
});

test('grado query returns only the current school rows', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $gradoOne = Grado::factory()->create(['school_id' => $schoolOne->id]);
    Grado::factory()->create(['school_id' => $schoolTwo->id]);

    app(TenantContext::class)->set($schoolOne);

    expect(Grado::all())->toHaveCount(1)
        ->and(Grado::first()->id)->toBe($gradoOne->id);
});

test('seccion query returns only the current school rows', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $seccionOne = Seccion::factory()->create(['school_id' => $schoolOne->id]);
    Seccion::factory()->create(['school_id' => $schoolTwo->id]);

    app(TenantContext::class)->set($schoolOne);

    expect(Seccion::all())->toHaveCount(1)
        ->and(Seccion::first()->id)->toBe($seccionOne->id);
});

test('catalog entities carry no period reference', function () {
    $columns = [
        'niveles_academicos' => Schema::getColumnListing('niveles_academicos'),
        'grados' => Schema::getColumnListing('grados'),
        'secciones' => Schema::getColumnListing('secciones'),
    ];

    foreach ($columns as $tableColumns) {
        expect($tableColumns)->not->toContain('periodo_academico_id');
    }
});
