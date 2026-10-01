<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('the super-admin holds every permission without an explicit grant', function () {
    $superAdmin = User::factory()->create(['school_id' => null]);
    $superAdmin->forceFill(['is_super_admin' => true])->save();

    expect($superAdmin->can('grades.manage'))->toBeTrue()
        ->and($superAdmin->can('any.permission.name'))->toBeTrue();
});

test('the bypass never reaches policy checks', function () {
    $superAdmin = User::factory()->create(['school_id' => null]);
    $superAdmin->forceFill(['is_super_admin' => true])->save();

    expect($superAdmin->can('delete', $superAdmin))->toBeFalse();
});

test('other roles get no bypass', function () {
    $staff = User::factory()->create(['school_id' => School::factory()->create()->id]);
    $staff->assignRole('director');

    expect($staff->can('any.permission.name'))->toBeFalse();
});
