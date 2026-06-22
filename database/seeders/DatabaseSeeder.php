<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@ojmas.test',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->call([
            PaymentCategorySeeder::class,
            AdminUserSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
