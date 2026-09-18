<?php

use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\NivelAcademico;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Models\Seccion;

uses(RefreshDatabase::class);

test('the same catalog grado and seccion are reused across two periodos academicos with no duplication', function () {
    $school = School::factory()->create();
    app(TenantContext::class)->set($school);

    $nivel = NivelAcademico::factory()->create(['school_id' => $school->id]);
    $grado = Grado::factory()->create(['school_id' => $school->id, 'nivel_academico_id' => $nivel->id]);
    $seccion = Seccion::factory()->create(['school_id' => $school->id]);

    $periodoOne = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'name' => '2024-2025']);
    $periodoTwo = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'name' => '2025-2026']);

    // No new catalog rows are created for the second cycle — the same
    // Grado/Sección records are simply referenced again.
    expect(Grado::count())->toBe(1)
        ->and(Seccion::count())->toBe(1)
        ->and(PeriodoAcademico::count())->toBe(2)
        ->and($grado->fresh()->id)->toBe($grado->id)
        ->and($seccion->fresh()->id)->toBe($seccion->id)
        ->and($periodoOne->id)->not->toBe($periodoTwo->id);
});
