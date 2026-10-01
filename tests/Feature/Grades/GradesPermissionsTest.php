<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Users\Infrastructure\Models\User;
use Spatie\Permission\Models\Role;
use Tests\Feature\Grades\GradesFixtures as G;

uses(RefreshDatabase::class);

// Each fine-grained Grades permission works on its own: granting one to a
// teacher, as a direct extra permission, unlocks exactly that and no more.

beforeEach(function () {
    G::scenario($this);
});

test('a teacher sees only the screens of the functions they are granted', function () {
    $results = G::url($this->school, '/results?period='.$this->period);

    $this->actingAs($this->teacher)->get(G::url($this->school, '/monitor'))->assertForbidden();
    $this->actingAs($this->teacher)->get($results)->assertForbidden();

    $this->teacher->givePermissionTo('grades.monitor');

    $this->actingAs($this->teacher)->get(G::url($this->school, '/monitor'))->assertOk();
    $this->actingAs($this->teacher)->get($results)->assertForbidden();
    $this->actingAs($this->teacher)->get(G::url($this->school))
        ->assertInertia(fn (Assert $page) => $page->where('can', ['monitor' => true, 'results' => false]));
});

test('the all-sections scope widens a teacher to every subject', function () {
    $this->actingAs($this->teacher)->get(G::url($this->school))
        ->assertInertia(fn (Assert $page) => $page->where('seesAllSections', false)->has('cards', 1));

    $this->teacher->givePermissionTo('grades.scope.all');

    $this->actingAs($this->teacher)->get(G::url($this->school))
        ->assertInertia(fn (Assert $page) => $page->where('seesAllSections', true)->has('cards', 2));
});

test('the correction function lets a teacher open a correction', function () {
    $plan = G::plan($this, $this->math, $this->closedMoment);
    $url = G::url($this->school, "/sheets/{$plan}/correction");

    $this->actingAs($this->teacher)->post($url, ['reason' => 'Error', 'expires_on' => '2026-10-05'])->assertForbidden();

    $this->teacher->givePermissionTo('grades.correction');

    $this->actingAs($this->teacher)->post($url, ['reason' => 'Error', 'expires_on' => '2026-10-05'])
        ->assertSessionHasNoErrors();
    expect(DB::table('grade_corrections')->count())->toBe(1);
});

test('managing grades without the scope keeps a user to their assignments', function () {
    $role = Role::create(['name' => 'coordinator', 'guard_name' => 'web']);
    $role->givePermissionTo('grades.manage');
    $coordinator = User::factory()->create(['school_id' => $this->school->id]);
    $coordinator->assignRole($role);

    $this->actingAs($coordinator)->get(G::url($this->school))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('seesAllSections', false)->has('cards', 0));
    $this->actingAs($coordinator)->get(G::url($this->school, '/monitor'))->assertForbidden();
});
