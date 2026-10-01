<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AcademicOffers\Infrastructure\Listeners\ProjectTeacherListener;
use Modules\Enrollments\Infrastructure\Listeners\ProjectStudentListener;
use Modules\Users\Application\DTOs\UserData;
use Modules\Users\Application\UseCases\RegisterUser;
use Modules\Users\Infrastructure\Models\User;
use Modules\Users\Public\Contracts\StudentReader;
use Modules\Users\Public\Contracts\TeacherReader;
use Modules\Users\Public\Enums\UserType;
use Modules\Users\Public\Events\UserUpdated;

uses(RefreshDatabase::class);

// Who a person is (type) is separate from what they may do (roles): the
// readers and the projections follow the type, never a role name.

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->school = School::factory()->create();
});

test('the readers find people by type, whatever roles they hold', function () {
    $teachingDirector = User::factory()->create(['school_id' => $this->school->id, 'type' => 'teacher']);
    $teachingDirector->assignRole('staff/admin');
    $roleOnlyTeacher = User::factory()->create(['school_id' => $this->school->id, 'type' => 'staff']);
    $roleOnlyTeacher->assignRole('teacher');

    $teacherIds = array_map(fn ($teacher) => $teacher->id, app(TeacherReader::class)->allForSchool($this->school->id));

    expect($teacherIds)->toBe([$teachingDirector->id])
        ->and(app(StudentReader::class)->allForSchool($this->school->id))->toBe([]);
});

test('registering a user stores the type implied by the role, or an explicit one', function () {
    $register = app(RegisterUser::class);

    $teacher = $register->handle(new UserData('Ada', 'ada@example.test', 'secret-password', $this->school->id, 'teacher'));
    $director = $register->handle(new UserData('Grace', 'grace@example.test', 'secret-password', $this->school->id, 'staff/admin', UserType::Teacher));

    expect(User::find($teacher->id())->type)->toBe(UserType::Teacher)
        ->and(User::find($director->id())->type)->toBe(UserType::Teacher);

    $payload = json_decode((string) DB::table('integration_outbox_events')
        ->where('aggregate_id', (string) $director->id())->value('payload'), true);

    expect($payload['type'])->toBe('teacher')
        ->and($payload['roles'])->toBe(['staff/admin']);
});

test('the projections follow the type over the roles', function (?string $type, ?array $roles, ?bool $teacherActive, ?bool $studentActive) {
    $event = new UserUpdated(userId: 42, name: 'Ada', email: 'ada@example.test', schoolId: 7, roles: $roles, type: $type);

    (new ProjectTeacherListener)->handle($event);
    (new ProjectStudentListener)->handle($event);

    // SQLite returns 0/1 and PostgreSQL a boolean.
    $active = fn (string $table, string $key): ?bool => ($value = DB::table($table)->where($key, 42)->value('is_active')) === null ? null : (bool) $value;

    expect($active('academic_offers_teacher_projection', 'source_teacher_id'))->toBe($teacherActive)
        ->and($active('enrollments_student_projection', 'source_student_id'))->toBe($studentActive);
})->with([
    'teacher type with a staff role' => ['teacher', ['staff/admin'], true, false],
    'student type' => ['student', ['student'], false, true],
    'staff type with a teacher role' => ['staff', ['teacher'], false, false],
    'deleted user' => [null, [], false, false],
    'payload from before types' => [null, ['teacher'], true, false],
    'role-less legacy payload' => [null, null, null, null],
]);

test('the migration backfills the type from the roles users already hold', function () {
    $migration = require base_path('Modules/Users/Infrastructure/Database/Migrations/2026_09_30_120000_add_type_to_users_table.php');
    $migration->down();

    $make = function (array $roles) {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole($roles);

        return $user->id;
    };
    $teacher = $make(['teacher']);
    $student = $make(['student']);
    $staff = $make(['staff/admin']);
    $both = $make(['student', 'teacher']);

    $migration->up();

    $types = DB::table('users')->pluck('type', 'id');
    expect($types[$teacher])->toBe('teacher')
        ->and($types[$student])->toBe('student')
        ->and($types[$staff])->toBe('staff')
        ->and($types[$both])->toBe('teacher');
});
