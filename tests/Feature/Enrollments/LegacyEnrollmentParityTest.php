<?php

use App\IntegrationEvents\Outbox\OutboxWorker;
use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(ModulePlatformSeeder::class);
});

test('legacy and new enrollment HTTP paths emit equivalent events consumed by Grades', function () {
    $school = School::factory()->create();
    $period = PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $legacyOffer = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $period->id,
    ]);
    $newOffer = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $period->id,
    ]);
    $legacyStudent = User::factory()->create(['school_id' => $school->id]);
    $legacyStudent->assignRole('student');
    $newStudent = User::factory()->create(['school_id' => $school->id]);
    $newStudent->assignRole('student');
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $baseUrl = 'http://'.$school->subdomain.'.'.config('tenancy.base_domain');

    $this->actingAs($admin)->post($baseUrl.'/academic/matriculas', [
        'oferta_academica_id' => $legacyOffer->id,
        'student_id' => $legacyStudent->id,
    ])->assertRedirect();
    $this->actingAs($admin)->post($baseUrl.'/enrollments', [
        'academic_offer_id' => $newOffer->id,
        'student_id' => $newStudent->id,
    ])->assertRedirect();

    expect(DB::table('integration_outbox_events')->where('event_name', 'enrollment.created')->count())->toBe(2);
    expect(app(OutboxWorker::class)->run())->toMatchArray(['published' => 2, 'failed' => 0]);
    expect(DB::table('grades_enrollment_projection')->count())->toBe(2)
        ->and(DB::table('grades_enrollment_projection')->pluck('student_id')->sort()->values()->all())
        ->toBe(collect([$legacyStudent->id, $newStudent->id])->sort()->values()->all());
});
