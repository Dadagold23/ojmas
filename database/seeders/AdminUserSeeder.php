<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ CREATE ALL ROLES FIRST
        $roles = [
            'Super Admin',
            'Admin',
            'Editor',
            'Reviewer',
            'Author'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web'
            ]);
        }

        // ✅ ADMIN USER
        $admin = User::firstOrCreate(
            ['email' => 'admin@ojmaspublish.online'],
            [
                'name' => 'OJMAS Super Admin',
                'phone' => '08000000000',
                'institution' => 'OJMAS Journal',
                'country' => 'Nigeria',
                'role' => 'Super Admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('Super Admin');
        $admin->syncLegacyRoleColumn('Super Admin');

        // ✅ EDITOR USER
        $editor = User::firstOrCreate(
            ['email' => 'editor@ojmaspublish.online'],
            [
                'name' => 'Journal Editor',
                'role' => 'Editor',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        $editor->assignRole('Editor');
        $editor->syncLegacyRoleColumn('Editor');

        // ✅ AUTHOR USER
        $author = User::firstOrCreate(
            ['email' => 'author@ojmaspublish.online'],
            [
                'name' => 'Test Author',
                'role' => 'Author',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        $author->assignRole('Author');
        $author->syncLegacyRoleColumn('Author');

        $user = \App\Models\User::where('email', 'test@ojmaspublish.online')->first(); $user?->getRoleNames();
    }
}
