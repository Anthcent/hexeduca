<?php

use App\Tenancy\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Period\Broadcasting\PeriodoChannel;
use Modules\Users\Infrastructure\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('name builds the exact school and period scoped channel shape', function () {
    expect(PeriodoChannel::name(7, 3, 'matriculas', 42))->toBe('school.7.periodo.3.matriculas.42');
});

test('name accepts School and PeriodoAcademico model instances', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);

    expect(PeriodoChannel::name($school, $periodo, 'matriculas', 42))
        ->toBe("school.{$school->id}.periodo.{$periodo->id}.matriculas.42");
});

test('pattern builds the exact registration pattern shape', function () {
    expect(PeriodoChannel::pattern('matriculas'))->toBe('school.{schoolId}.periodo.{periodoId}.matriculas.{id}');
});

test('authorize accepts a same-school user with well-formed segments', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create(['school_id' => $school->id]);

    expect(PeriodoChannel::authorize($user, $school->id, $periodo->id))->toBeTrue();
});

test('authorize rejects a landlord user (null school_id) for any period id, well-formed or malformed', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create(['school_id' => null]);

    expect(PeriodoChannel::authorize($user, $school->id, $periodo->id))->toBeFalse()
        ->and(PeriodoChannel::authorize($user, $school->id, 'abc'))->toBeFalse();
});

test('authorize rejects a different-school user even with a well-formed period id', function () {
    $ownSchool = School::factory()->create();
    $otherSchool = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $otherSchool->id]);
    $user = User::factory()->create(['school_id' => $ownSchool->id]);

    expect(PeriodoChannel::authorize($user, $otherSchool->id, $periodo->id))->toBeFalse();
});

test('authorize rejects a non numeric school id segment even against a tenant user', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create(['school_id' => $school->id]);

    expect(PeriodoChannel::authorize($user, 'abc', $periodo->id))->toBeFalse();
});

test('authorize rejects a non numeric period id segment even against a tenant user', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    expect(PeriodoChannel::authorize($user, $school->id, 'abc'))->toBeFalse();
});

test('authorize coerces string channel ids', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create(['school_id' => $school->id]);

    expect(PeriodoChannel::authorize($user, (string) $school->id, (string) $periodo->id))->toBeTrue();
});
