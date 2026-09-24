<?php

use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->school = School::factory()->create();
    $this->otherSchool = School::factory()->create();

    // Syncs module.json permissions onto the roles, enables mature modules
    // and entitles the schools created above.
    $this->seed(ModulePlatformSeeder::class);

    $this->staff = notificationsUser($this->school, 'staff/admin', 'Ana Directora');
});

function notificationsUrl(School $school, string $path = ''): string
{
    return 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/notifications'.$path;
}

function notificationsUser(School $school, string $role, ?string $name = null): User
{
    $user = User::factory()->create(array_filter(['school_id' => $school->id, 'name' => $name]));
    $user->assignRole($role);

    return $user;
}

/**
 * Inserts a notification row directly, bypassing the use case, for tests
 * that need precise dates or cross-school data.
 */
function notificationRow(User $recipient, array $overrides = []): int
{
    return DB::table('school_notifications')->insertGetId(array_merge([
        'school_id' => $recipient->school_id,
        'recipient_id' => $recipient->id,
        'sender_id' => null,
        'sender_name' => 'Dirección',
        'title' => 'Aviso',
        'body' => 'Contenido del aviso.',
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function announcementPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Reunión de padres',
        'body' => 'El viernes a las 18 h en el salón de actos.',
        'audience' => 'all',
    ], $overrides);
}

test('staff/admin sends an announcement to the chosen audience of their own school only', function (string $audience, int $expected) {
    notificationsUser($this->school, 'teacher');
    notificationsUser($this->school, 'teacher');
    notificationsUser($this->school, 'student');
    notificationsUser($this->school, 'student');
    notificationsUser($this->school, 'student');
    notificationsUser($this->otherSchool, 'teacher');
    notificationsUser($this->otherSchool, 'student');

    $response = $this->actingAs($this->staff)
        ->post(notificationsUrl($this->school), announcementPayload(['audience' => $audience]));

    $response->assertRedirect(route('notifications.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', "Anuncio enviado a {$expected} destinatarios.");

    $rows = DB::table('school_notifications')->get();
    expect($rows)->toHaveCount($expected)
        ->and($rows->pluck('school_id')->unique()->all())->toBe([$this->school->id])
        ->and($rows->pluck('recipient_id')->unique())->toHaveCount($expected)
        ->and($rows->pluck('sender_id')->unique()->all())->toBe([$this->staff->id])
        ->and($rows->pluck('sender_name')->unique()->all())->toBe(['Ana Directora'])
        ->and($rows->pluck('title')->unique()->all())->toBe(['Reunión de padres'])
        ->and($rows->whereNotNull('read_at'))->toHaveCount(0);

    $recipientRoles = User::withoutTenantScope()->whereIn('id', $rows->pluck('recipient_id'))->get()
        ->map(fn (User $user) => $user->getRoleNames()->first())->unique()->sort()->values()->all();
    expect($recipientRoles)->toBe(match ($audience) {
        'teachers' => ['teacher'],
        'students' => ['student'],
        'all' => ['student', 'teacher'],
    });
})->with([
    'teachers' => ['teachers', 2],
    'students' => ['students', 3],
    'all' => ['all', 5],
]);

test('the school always comes from the tenant, never from the request', function () {
    notificationsUser($this->school, 'teacher');
    notificationsUser($this->otherSchool, 'teacher');

    $this->actingAs($this->staff)
        ->post(notificationsUrl($this->school), announcementPayload(['audience' => 'teachers', 'school_id' => $this->otherSchool->id]))
        ->assertRedirect(route('notifications.index'));

    expect(DB::table('school_notifications')->pluck('school_id')->all())->toBe([$this->school->id]);
});

test('staff/admin sees the send form', function () {
    $this->actingAs($this->staff)
        ->get(notificationsUrl($this->school, '/create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Notifications::Create', false));
});

test('teachers and students cannot open the send form or send', function (string $role) {
    $user = notificationsUser($this->school, $role);
    notificationsUser($this->school, 'teacher');

    $this->actingAs($user)->get(notificationsUrl($this->school, '/create'))->assertForbidden();
    $this->actingAs($user)->post(notificationsUrl($this->school), announcementPayload())->assertForbidden();

    expect(DB::table('school_notifications')->count())->toBe(0);
})->with(['teacher', 'student']);

test('invalid announcements are rejected and nothing is saved', function (array $payload, string $field) {
    notificationsUser($this->school, 'teacher');

    $this->actingAs($this->staff)
        ->from(notificationsUrl($this->school, '/create'))
        ->post(notificationsUrl($this->school), announcementPayload($payload))
        ->assertRedirect(notificationsUrl($this->school, '/create'))
        ->assertSessionHasErrors($field);

    expect(DB::table('school_notifications')->count())->toBe(0);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'whitespace title' => [['title' => '   '], 'title'],
    'title over 120 characters' => [['title' => str_repeat('a', 121)], 'title'],
    'missing body' => [['body' => ''], 'body'],
    'body over 2000 characters' => [['body' => str_repeat('a', 2001)], 'body'],
    'missing audience' => [['audience' => ''], 'audience'],
    'unknown audience' => [['audience' => 'staff'], 'audience'],
]);

test('the limits accept exactly 120 and 2000 characters', function () {
    notificationsUser($this->school, 'teacher');

    $this->actingAs($this->staff)
        ->post(notificationsUrl($this->school), announcementPayload([
            'title' => str_repeat('a', 120),
            'body' => str_repeat('b', 2000),
            'audience' => 'teachers',
        ]))
        ->assertSessionHasNoErrors();

    expect(DB::table('school_notifications')->count())->toBe(1);
});

test('the inbox lists only the current user\'s notifications, unread first then newest', function (string $role) {
    $user = notificationsUser($this->school, $role);
    $classmate = notificationsUser($this->school, $role);

    $oldUnread = notificationRow($user, ['title' => 'Viejo sin leer', 'created_at' => now()->subDays(3)]);
    $newRead = notificationRow($user, ['title' => 'Nuevo leído', 'created_at' => now()->subHour(), 'read_at' => now()]);
    $newUnread = notificationRow($user, ['title' => 'Nuevo sin leer', 'created_at' => now()->subDay()]);
    notificationRow($classmate, ['title' => 'De otra persona']);

    $this->actingAs($user)
        ->get(notificationsUrl($this->school))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications::Index', false)
            ->has('inbox.data', 3)
            ->where('inbox.data.0.id', $newUnread)
            ->where('inbox.data.1.id', $oldUnread)
            ->where('inbox.data.2.id', $newRead)
            ->where('inbox.data.0.senderName', 'Dirección')
            // The page prop must not shadow the shared bell count.
            ->where('notifications.unreadCount', 2)
            ->where('canSend', $role === 'staff/admin'));
})->with(['staff/admin', 'teacher', 'student']);

test('the inbox is paginated', function () {
    $teacher = notificationsUser($this->school, 'teacher');

    foreach (range(1, 17) as $i) {
        notificationRow($teacher, ['title' => "Aviso {$i}"]);
    }

    $this->actingAs($teacher)
        ->get(notificationsUrl($this->school, '?page=2'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('inbox.data', 2)
            ->where('inbox.current_page', 2)
            ->where('inbox.last_page', 2)
            ->where('inbox.total', 17));
});

test('a user marks their own notification as read, idempotently', function () {
    $student = notificationsUser($this->school, 'student');
    $id = notificationRow($student);

    $this->actingAs($student)
        ->from(notificationsUrl($this->school))
        ->patch(notificationsUrl($this->school, "/{$id}/read"))
        ->assertRedirect(notificationsUrl($this->school));

    $firstReadAt = DB::table('school_notifications')->where('id', $id)->value('read_at');
    expect($firstReadAt)->not->toBeNull();

    $this->travel(2)->hours();

    $this->actingAs($student)
        ->patch(notificationsUrl($this->school, "/{$id}/read"))
        ->assertRedirect();

    expect(DB::table('school_notifications')->where('id', $id)->value('read_at'))->toBe($firstReadAt);
});

test('mark all as read only touches the current user\'s unread notifications', function () {
    $teacher = notificationsUser($this->school, 'teacher');
    $colleague = notificationsUser($this->school, 'teacher');
    $earlier = now()->subDay()->startOfSecond();

    $alreadyRead = notificationRow($teacher, ['read_at' => $earlier]);
    notificationRow($teacher);
    notificationRow($teacher);
    $colleagues = notificationRow($colleague);

    $this->actingAs($teacher)
        ->post(notificationsUrl($this->school, '/read-all'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DB::table('school_notifications')->where('recipient_id', $teacher->id)->whereNull('read_at')->count())->toBe(0)
        ->and(DB::table('school_notifications')->where('id', $colleagues)->value('read_at'))->toBeNull()
        ->and((string) DB::table('school_notifications')->where('id', $alreadyRead)->value('read_at'))->toStartWith($earlier->format('Y-m-d H:i:s'));
});

test('another user\'s notification is not found', function () {
    $student = notificationsUser($this->school, 'student');
    $classmate = notificationsUser($this->school, 'student');
    $id = notificationRow($classmate);

    $this->actingAs($student)
        ->patch(notificationsUrl($this->school, "/{$id}/read"))
        ->assertNotFound();

    expect(DB::table('school_notifications')->where('id', $id)->value('read_at'))->toBeNull();
});

test('another school\'s notification is not found', function () {
    $teacher = notificationsUser($this->school, 'teacher');
    $foreignTeacher = notificationsUser($this->otherSchool, 'teacher');
    $foreignId = notificationRow($foreignTeacher);
    // Even a row pointing at this user but owned by the other school stays hidden.
    $mislabeledId = notificationRow($teacher, ['school_id' => $this->otherSchool->id]);

    $this->actingAs($teacher)->patch(notificationsUrl($this->school, "/{$foreignId}/read"))->assertNotFound();
    $this->actingAs($teacher)->patch(notificationsUrl($this->school, "/{$mislabeledId}/read"))->assertNotFound();

    $this->actingAs($teacher)
        ->get(notificationsUrl($this->school))
        ->assertInertia(fn (Assert $page) => $page->has('inbox.data', 0));

    expect(DB::table('school_notifications')->whereNotNull('read_at')->count())->toBe(0);
});

test('the unread count is shared with every page for users who can view notifications', function () {
    $teacher = notificationsUser($this->school, 'teacher');
    notificationRow($teacher);
    notificationRow($teacher);
    notificationRow($teacher, ['read_at' => now()]);
    notificationRow(notificationsUser($this->school, 'teacher'));

    $this->actingAs($teacher)
        ->get('http://'.$this->school->subdomain.'.'.config('tenancy.base_domain').'/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 2)
            ->where('notifications.inboxUrl', route('notifications.index')));
});

test('the sidebar shows the inbox entry to every school role', function (string $role) {
    $user = notificationsUser($this->school, $role);

    $this->actingAs($user)
        ->get(notificationsUrl($this->school))
        ->assertInertia(fn (Assert $page) => $page->where(
            'moduleNav',
            fn ($items) => collect($items)->contains(fn ($item) => $item['label'] === 'Notificaciones'
                && $item['icon'] === 'Bell'
                && $item['href'] === route('notifications.index')),
        ));
})->with(['staff/admin', 'teacher', 'student']);

test('the module returns 404 and shares no count when it is disabled', function () {
    $teacher = notificationsUser($this->school, 'teacher');
    notificationRow($teacher);
    app(ModuleRegistry::class)->disable('notifications');

    $this->actingAs($teacher)->get(notificationsUrl($this->school))->assertNotFound();
    $this->actingAs($this->staff)->get(notificationsUrl($this->school, '/create'))->assertNotFound();
    $this->actingAs($this->staff)->post(notificationsUrl($this->school), announcementPayload())->assertNotFound();

    $this->actingAs($teacher)
        ->get('http://'.$this->school->subdomain.'.'.config('tenancy.base_domain').'/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications', null)
            ->where('moduleNav', fn ($items) => collect($items)->doesntContain('label', 'Notificaciones')));
});

test('the module returns 404 and shares no count when the school is not entitled', function () {
    $teacher = notificationsUser($this->school, 'teacher');
    $id = notificationRow($teacher);
    app(ModuleRegistry::class)->revoke('notifications', $this->school);

    $this->actingAs($teacher)->get(notificationsUrl($this->school))->assertNotFound();
    $this->actingAs($teacher)->patch(notificationsUrl($this->school, "/{$id}/read"))->assertNotFound();
    $this->actingAs($this->staff)->get(notificationsUrl($this->school, '/create'))->assertNotFound();

    $this->actingAs($teacher)
        ->get('http://'.$this->school->subdomain.'.'.config('tenancy.base_domain').'/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('notifications', null));
});

test('guests are redirected to the login page', function () {
    $this->get(notificationsUrl($this->school))->assertRedirect();
});
