<?php

namespace Database\Seeders;

use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Modules\Users\Infrastructure\Models\User;
use RuntimeException;

class SuperAdminUserSeeder extends Seeder
{
    /**
     * Seed a single explicitly configured landlord super-admin user.
     *
     * This account operates outside the tenant scope entirely: `school_id`
     * is explicitly NULL, marking it as the landlord identity (see
     * App\Tenancy\Concerns\BelongsToTenant / TenantContext). It is distinct
     * from any per-tenant `staff/admin` account, which is fully
     * tenant-scoped with a non-null `school_id`.
     *
     * Must run after RoleAndPermissionSeeder so the "super-admin" role exists.
     */
    public function run(): void
    {
        $credentials = config('bootstrap.super_admin');
        $validator = Validator::make($credentials, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => [
                'required',
                'string',
                Password::min(16)->mixedCase()->numbers()->symbols(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (in_array(strtolower($value), ['password', 'changeme', 'change-me', 'admin', 'superadmin', 'secret'], true)) {
                        $fail("The {$attribute} must not be a placeholder credential.");
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('Invalid super-admin bootstrap configuration: '.$validator->errors()->first());
        }

        $validated = $validator->validated();
        $user = User::updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
                // Explicit landlord marker: this account bypasses the
                // tenant scope and belongs to no school.
                'school_id' => null,
            ]
        );

        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }
    }
}
