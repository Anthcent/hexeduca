<?php

use App\IntegrationEvents\Outbox\OutboxWorker;
use App\Tenancy\Models\School;
use App\Tenancy\Scopes\TenantScope;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Users\Domain\Events\UserRegistered;
use Modules\Users\Infrastructure\Models\User;
use Modules\Users\Public\Events\UserCreated;
use Tests\Support\ForcedInsertFailure;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

// TENANCY_LANDLORD_HOSTS=localhost in phpunit.xml, and Laravel's testing
// HTTP client defaults to host "localhost" — so a plain path request
// without an explicit host hits the landlord host.
test('valid registration creates a student in the chosen school without authenticating', function () {
    $school = School::factory()->create(['subdomain' => 'schoola']);
    Event::fake([UserRegistered::class]);

    $response = $this->post('/register', [
        'school_id' => $school->id,
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::withoutGlobalScope(TenantScope::class)
        ->where('email', 'newuser@example.com')
        ->first();

    expect($user)->not->toBeNull();
    expect($user->school_id)->toBe($school->id);
    expect(Hash::check('password123', $user->password))->toBeTrue();
    expect($user->hasRole('student'))->toBeTrue();
    expect($user->getRoleNames())->toHaveCount(1);

    Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event) => $event->user->id() === $user->id);

    $response->assertRedirect('http://schoola.'.config('tenancy.base_domain').'/login?registered=1');
    $this->assertGuest();
});

test('valid registration records a UserCreated event in the integration outbox', function () {
    $school = School::factory()->create(['subdomain' => 'schoolevents']);

    $this->post('/register', [
        'school_id' => $school->id,
        'name' => 'Outbox User',
        'email' => 'outboxuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::withoutGlobalScope(TenantScope::class)
        ->where('email', 'outboxuser@example.com')
        ->first();

    $rows = DB::table('integration_outbox_events')
        ->where('event_name', 'user.created')
        ->where('aggregate_id', (string) $user->id)
        ->get();

    expect($rows)->toHaveCount(1);
    $row = $rows->first();
    expect($row->status)->toBe('pending');
    expect($row->event_class)->toBe(UserCreated::class);

    $payload = json_decode($row->payload, true);
    expect($payload['userId'])->toBe($user->id);
    expect($payload['name'])->toBe('Outbox User');
    expect($payload['email'])->toBe('outboxuser@example.com');
    expect($payload['schoolId'])->toBe($school->id);
    expect($payload['roles'])->toBe(['student']);
});

test('HTTP registration publishes only to the student projection', function () {
    $school = School::factory()->create(['subdomain' => 'schoolprojection']);

    $this->post('/register', [
        'school_id' => $school->id,
        'name' => 'Projected Student',
        'email' => 'projected@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect();

    $user = User::withoutGlobalScope(TenantScope::class)
        ->where('email', 'projected@example.com')
        ->sole();

    expect(app(OutboxWorker::class)->run())->toMatchArray(['published' => 1, 'failed' => 0]);
    expect(DB::table('enrollments_student_projection')->where('source_student_id', $user->id)->count())->toBe(1);
    expect(DB::table('academic_offers_teacher_projection')
        ->where('source_teacher_id', $user->id)
        ->where('is_active', true)
        ->count())->toBe(0);
});

test('role assignment failure rolls back user role outbox and domain event', function () {
    $school = School::factory()->create();
    Event::fake([UserRegistered::class]);
    ForcedInsertFailure::install('fail_registration_role', 'model_has_roles', 'forced role assignment failure');

    expect(fn () => $this->withoutExceptionHandling()->post('/register', [
        'school_id' => $school->id,
        'name' => 'Rollback Role',
        'email' => 'rollback-role@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]))->toThrow(QueryException::class);

    expect(User::withoutGlobalScope(TenantScope::class)->where('email', 'rollback-role@example.com')->count())->toBe(0);
    expect(DB::table('model_has_roles')->count())->toBe(0);
    expect(DB::table('integration_outbox_events')->where('event_name', 'user.created')->count())->toBe(0);
    Event::assertNotDispatched(UserRegistered::class);
});

test('outbox recording failure rolls back user role outbox and domain event', function () {
    $school = School::factory()->create();
    Event::fake([UserRegistered::class]);
    ForcedInsertFailure::install('fail_registration_outbox', 'integration_outbox_events', 'forced outbox recording failure', 'user.created');

    expect(fn () => $this->withoutExceptionHandling()->post('/register', [
        'school_id' => $school->id,
        'name' => 'Rollback Outbox',
        'email' => 'rollback-outbox@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]))->toThrow(QueryException::class);

    expect(User::withoutGlobalScope(TenantScope::class)->where('email', 'rollback-outbox@example.com')->count())->toBe(0);
    expect(DB::table('model_has_roles')->count())->toBe(0);
    expect(DB::table('integration_outbox_events')->where('event_name', 'user.created')->count())->toBe(0);
    Event::assertNotDispatched(UserRegistered::class);
});

test('registration does not establish a session on the landlord host', function () {
    $school = School::factory()->create(['subdomain' => 'schoolb']);

    $this->post('/register', [
        'school_id' => $school->id,
        'name' => 'Another User',
        'email' => 'another@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    expect(Auth::check())->toBeFalse();
    $this->assertGuest();
});

test('registration is unreachable from a tenant subdomain', function () {
    $school = School::factory()->create(['subdomain' => 'schoolc']);
    $baseDomain = config('tenancy.base_domain');

    $this->get("http://{$school->subdomain}.{$baseDomain}/register")->assertNotFound();

    $this->post("http://{$school->subdomain}.{$baseDomain}/register", [
        'school_id' => $school->id,
        'name' => 'Blocked User',
        'email' => 'blocked@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
});

test('retried duplicate registration keeps exactly one user student role and outbox row', function () {
    $school = School::factory()->create();

    $payload = [
        'school_id' => $school->id,
        'name' => 'Dup User',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $this->post('/register', $payload)->assertRedirect();
    $this->post('/register', $payload)->assertSessionHasErrors('email');

    $user = User::withoutGlobalScope(TenantScope::class)->where('email', 'existing@example.com')->sole();
    $studentRoleId = DB::table('roles')->where('name', 'student')->value('id');

    expect(User::withoutGlobalScope(TenantScope::class)->where('email', 'existing@example.com')->count())->toBe(1);
    expect(DB::table('model_has_roles')
        ->where('role_id', $studentRoleId)
        ->where('model_type', User::class)
        ->where('model_id', $user->id)
        ->count())->toBe(1);
    expect(DB::table('integration_outbox_events')
        ->where('event_name', 'user.created')
        ->where('aggregate_id', (string) $user->id)
        ->count())->toBe(1);
});

test('registration has a dedicated rate limit', function () {
    config()->set('security.rate_limits.registration_per_minute', 3);
    $school = School::factory()->create();
    $payload = [
        'school_id' => $school->id,
        'name' => 'Limited User',
        'email' => 'limited-registration@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $this->post('/register', $payload)->assertRedirect();
    $this->post('/register', $payload)->assertSessionHasErrors('email');
    $this->post('/register', $payload)->assertSessionHasErrors('email');
    $this->post('/register', $payload)->assertTooManyRequests();
});

test('missing or invalid school selection is rejected', function () {
    $this->post('/register', [
        'name' => 'No School',
        'email' => 'noschool@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('school_id');

    $inactiveSchool = School::factory()->inactive()->create();

    $this->post('/register', [
        'school_id' => $inactiveSchool->id,
        'name' => 'Inactive School',
        'email' => 'inactiveschool@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('school_id');

    $this->assertDatabaseMissing('users', ['email' => 'noschool@example.com']);
    $this->assertDatabaseMissing('users', ['email' => 'inactiveschool@example.com']);
});
