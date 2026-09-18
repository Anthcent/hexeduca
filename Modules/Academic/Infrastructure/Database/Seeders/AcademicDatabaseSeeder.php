<?php

namespace Modules\Academic\Infrastructure\Database\Seeders;

use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\NivelAcademico;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Models\Seccion;
use Modules\Users\Infrastructure\Models\User;

/**
 * Seeds a reproducible demo tenant so the offering-management screens can be
 * exercised end-to-end from a clean clone: a demo School, its active
 * academic period, a small catalog (one level, two grades, two sections),
 * and a staff/admin + a couple of students + one teacher.
 *
 * Idempotent: safe to run more than once against the same database. Every
 * row is looked up by a natural key (subdomain/email/name combination)
 * before creating, matching this project's `firstOrCreate` seeding
 * convention (see root `DatabaseSeeder::run()`).
 */
class AcademicDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Sequence matters: the demo School must exist AND be bound to
     * TenantContext BEFORE any catalog/period row is created, otherwise
     * BelongsToTenant's creating() hook has no tenant to auto-stamp from
     * and rows would land with a null school_id. Passing school_id
     * explicitly on every create() call below is a second, redundant
     * safeguard against that same failure mode.
     *
     * TenantContext is bound as a `scoped()` container singleton (see
     * AppServiceProvider::register()), which behaves like a plain singleton
     * for the lifetime of a single `artisan db:seed` process — it is NOT
     * reset between seeder classes. Any seeder that runs after this one in
     * the same `call([...])` chain would otherwise inherit the demo tenant
     * and get its rows silently stamped with the demo school_id. The
     * `forget()` call at the end restores the "no tenant" state so
     * subsequent seeders (e.g. root DatabaseSeeder's `test@example.com`)
     * are unaffected.
     */
    public function run(): void
    {
        $school = School::firstOrCreate(
            ['subdomain' => 'demo'],
            ['name' => 'Demo School', 'is_active' => true],
        );

        app(TenantContext::class)->set($school);

        $periodo = PeriodoAcademico::firstOrCreate(
            ['school_id' => $school->id, 'name' => '2026-2027'],
            [
                'starts_on' => now()->startOfYear(),
                'ends_on' => now()->startOfYear()->addMonths(10),
                'is_active' => true,
            ],
        );

        if (! $periodo->is_active) {
            $periodo->update(['is_active' => true]);
        }

        $nivel = NivelAcademico::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Primaria'],
        );

        collect(['1ro', '2do'])->each(
            fn (string $name) => Grado::firstOrCreate(
                ['school_id' => $school->id, 'nivel_academico_id' => $nivel->id, 'name' => $name],
                ['order' => $name === '1ro' ? 1 : 2],
            ),
        );

        collect(['A', 'B'])->each(
            fn (string $name) => Seccion::firstOrCreate(
                ['school_id' => $school->id, 'name' => $name],
            ),
        );

        $staffAdmin = User::firstOrCreate(
            ['email' => 'staff@demo.test'],
            [
                'name' => 'Demo Staff Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'school_id' => $school->id,
            ],
        );

        if (! $staffAdmin->hasRole('staff/admin')) {
            $staffAdmin->assignRole('staff/admin');
        }

        $teacher = User::firstOrCreate(
            ['email' => 'teacher@demo.test'],
            [
                'name' => 'Demo Teacher',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'school_id' => $school->id,
            ],
        );

        if (! $teacher->hasRole('teacher')) {
            $teacher->assignRole('teacher');
        }

        collect(['student1@demo.test' => 'Demo Student One', 'student2@demo.test' => 'Demo Student Two'])
            ->each(function (string $name, string $email) use ($school): void {
                $student = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'school_id' => $school->id,
                    ],
                );

                if (! $student->hasRole('student')) {
                    $student->assignRole('student');
                }
            });

        app(TenantContext::class)->forget();
    }
}
