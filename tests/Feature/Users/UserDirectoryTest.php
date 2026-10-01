<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Public\Contracts\UserDirectory;
use Tests\Feature\Subjects\SubjectsFixtures as S;

uses(RefreshDatabase::class);

test('the directory returns the names and role labels of the school\'s users only', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();

    $staff = S::user($school, 'director');
    $teacher = S::user($school, 'teacher');
    $student = S::user($school, 'student');
    $foreign = S::user($otherSchool, 'teacher');

    $names = app(UserDirectory::class)->namesFor($school->id, [$staff->id, $teacher->id, $student->id, $teacher->id, $foreign->id, 999999]);

    expect($names)->toBe([
        $staff->id => ['name' => $staff->name, 'role' => 'Dirección'],
        $teacher->id => ['name' => $teacher->name, 'role' => 'Docente'],
        $student->id => ['name' => $student->name, 'role' => 'Estudiante'],
    ])
        ->and(app(UserDirectory::class)->namesFor($school->id, []))->toBe([])
        ->and(app(UserDirectory::class)->namesFor($otherSchool->id, [$foreign->id, $staff->id]))->toBe([
            $foreign->id => ['name' => $foreign->name, 'role' => 'Docente'],
        ]);
});
