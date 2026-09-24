<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Modules\Academic\Infrastructure\Database\Seeders\AcademicDatabaseSeeder;
use Modules\Users\Infrastructure\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            SuperAdminUserSeeder::class,
            AcademicDatabaseSeeder::class,
            ModulePlatformSeeder::class,
        ]);

        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            User::factory()->raw([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]),
        );
    }
}
