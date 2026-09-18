<?php

use App\Tenancy\Models\School;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\NivelAcademico;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('nivel academico has many grados', function () {
    $school = School::factory()->create();
    $nivel = NivelAcademico::factory()->create(['school_id' => $school->id]);

    $gradoOne = Grado::factory()->create(['school_id' => $school->id, 'nivel_academico_id' => $nivel->id]);
    $gradoTwo = Grado::factory()->create(['school_id' => $school->id, 'nivel_academico_id' => $nivel->id]);

    expect($nivel->grados())->toBeInstanceOf(HasMany::class)
        ->and($nivel->grados()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$gradoOne->id, $gradoTwo->id])->sort()->values()->all());
});

test('grado belongs to a nivel academico', function () {
    $school = School::factory()->create();
    $nivel = NivelAcademico::factory()->create(['school_id' => $school->id]);
    $grado = Grado::factory()->create(['school_id' => $school->id, 'nivel_academico_id' => $nivel->id]);

    expect($grado->nivelAcademico()->first()->id)->toBe($nivel->id);
});
