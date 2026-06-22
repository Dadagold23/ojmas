<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['Super Admin', 'Managing Editor', 'Editor', 'Reviewer', 'Author'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
