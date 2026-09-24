<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Academic\Application\DTOs\MatricularEstudianteData;
use Modules\Academic\Application\UseCases\MatricularEstudiante;
use Modules\Academic\Domain\Events\EstudianteMatriculado;
use Modules\Academic\Domain\Repositories\MatriculaRepositoryInterface;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('rejects enrollment past capacity', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $oferta = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
        'capacity' => 1,
    ]);

    $useCase = app(MatricularEstudiante::class);
    $firstStudent = User::factory()->create(['school_id' => $school->id]);
    $firstStudent->assignRole('student');
    $secondStudent = User::factory()->create(['school_id' => $school->id]);
    $secondStudent->assignRole('student');

    $useCase->handle(new MatricularEstudianteData(
        ofertaAcademicaId: $oferta->id,
        studentId: $firstStudent->id,
    ));

    expect(fn () => $useCase->handle(new MatricularEstudianteData(
        ofertaAcademicaId: $oferta->id,
        studentId: $secondStudent->id,
    )))->toThrow(DomainException::class, 'The OfertaAcademica has reached its enrollment capacity.');
});

test('rejects a duplicate (oferta, student) enrollment', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $oferta = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
        'capacity' => 10,
    ]);
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');

    $useCase = app(MatricularEstudiante::class);
    $data = new MatricularEstudianteData(ofertaAcademicaId: $oferta->id, studentId: $student->id);

    $useCase->handle($data);

    expect(fn () => $useCase->handle($data))
        ->toThrow(DomainException::class, 'The student is already enrolled in this OfertaAcademica.');
});

test('succeeds and stamps school_id and periodo_academico_id from the offering', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $oferta = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
        'capacity' => 10,
    ]);
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');

    $matricula = app(MatricularEstudiante::class)->handle(new MatricularEstudianteData(
        ofertaAcademicaId: $oferta->id,
        studentId: $student->id,
    ));

    expect($matricula->schoolId())->toBe($school->id)
        ->and($matricula->periodoAcademicoId())->toBe($periodo->id)
        ->and($matricula->studentId())->toBe($student->id);
});

test('a withdrawn matricula does not count toward capacity, freeing the seat for another student', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $oferta = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
        'capacity' => 1,
    ]);

    $firstStudent = User::factory()->create(['school_id' => $school->id]);
    $firstStudent->assignRole('student');
    $secondStudent = User::factory()->create(['school_id' => $school->id]);
    $secondStudent->assignRole('student');

    $useCase = app(MatricularEstudiante::class);

    $first = $useCase->handle(new MatricularEstudianteData(
        ofertaAcademicaId: $oferta->id,
        studentId: $firstStudent->id,
    ));

    // Withdraw the first student — this must free the single available seat.
    $repository = app(MatriculaRepositoryInterface::class);
    $entity = $repository->findById($first->id());
    $entity->changeStatusTo('withdrawn');
    $repository->save($entity);

    expect($repository->countByOfertaAcademica($oferta->id))->toBe(0);

    $second = $useCase->handle(new MatricularEstudianteData(
        ofertaAcademicaId: $oferta->id,
        studentId: $secondStudent->id,
    ));

    expect($second->studentId())->toBe($secondStudent->id);
});

test('legacy enrollment rolls back source and domain event when outbox recording fails', function () {
    $school = School::factory()->create();
    $period = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $offer = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $period->id,
    ]);
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');
    Event::fake([EstudianteMatriculado::class]);
    DB::statement(<<<'SQL'
        CREATE TRIGGER fail_legacy_enrollment_outbox
        BEFORE INSERT ON integration_outbox_events
        WHEN NEW.event_name = 'enrollment.created'
        BEGIN
            SELECT RAISE(ABORT, 'forced outbox failure');
        END
    SQL);

    expect(fn () => app(MatricularEstudiante::class)->handle(new MatricularEstudianteData(
        ofertaAcademicaId: $offer->id,
        studentId: $student->id,
    )))->toThrow(QueryException::class);

    $this->assertDatabaseCount('matriculas', 0);
    $this->assertDatabaseCount('integration_outbox_events', 0);
    Event::assertNotDispatched(EstudianteMatriculado::class);
});
