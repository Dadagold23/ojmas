<?php

namespace App\Services\Permissions;

use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;
use Throwable;

/**
 * Registers the permission catalogue with Laravel's authorisation layer.
 *
 * Every ability is defined as a Gate check backed by Spatie.
 *
 * The check is deliberately forgiving in one specific direction: if the
 * permissions table has never been seeded, the ability falls back to a
 * role-name check derived from the same catalogue. A deployment that forgets to
 * run the seeder therefore loses the *granularity* of the new permission layer
 * but does **not** lock every member of staff out of the system. As soon as
 * the seeder has run, the Spatie path takes over and the role fallback is no
 * longer consulted.
 */
class PermissionRegistrar
{
    public function register(): void
    {
        foreach (PermissionCatalog::all() as $permissions) {
            foreach ($permissions as $permission) {
                Gate::define($permission, $this->abilityResolver($permission));
            }
        }
    }

    private function abilityResolver(string $permission): \Closure
    {
        return function ($user) use ($permission) {
            if ($this->permissionsAreSeeded() && method_exists($user, 'hasPermissionTo')) {
                try {
                    return $user->hasPermissionTo($permission);
                } catch (Throwable) {
                    // An unknown permission falls through to the role check.
                }
            }

            return $this->roleGrantsPermission($user, $permission);
        };
    }

    private function permissionsAreSeeded(): bool
    {
        try {
            return Permission::query()->where('name', 'like', '%.%')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Role-name fallback derived from the same catalogue, so the two paths can
     * never disagree about who should have what.
     */
    private function roleGrantsPermission(mixed $user, string $permission): bool
    {
        try {
            $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        } catch (Throwable) {
            return false;
        }

        $matrix = PermissionCatalog::roleMatrix();

        foreach ($roleNames as $roleName) {
            if (in_array($permission, $matrix[$roleName] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates any missing permissions and attaches them to their roles.
     *
     * Idempotent: existing roles keep every permission they already have, and
     * only the additions from the catalogue are applied. Nothing is revoked.
     *
     * @return array{created_permissions: int, created_roles: int, granted: int}
     */
    public function sync(): array
    {
        $this->forgetCache();

        $createdPermissions = 0;
        $createdRoles = 0;
        $granted = 0;

        foreach (PermissionCatalog::names() as $name) {
            if (Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                continue;
            }

            Permission::create(['name' => $name, 'guard_name' => 'web']);
            $createdPermissions++;
        }

        foreach (PermissionCatalog::roleMatrix() as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($role->wasRecentlyCreated) {
                $createdRoles++;
            }

            $existing = $role->permissions()->pluck('name')->all();
            $missing = array_values(array_diff($permissions, $existing));

            if ($missing === []) {
                continue;
            }

            $role->givePermissionTo($missing);
            $granted += count($missing);
        }

        $this->forgetCache();

        return [
            'created_permissions' => $createdPermissions,
            'created_roles' => $createdRoles,
            'granted' => $granted,
        ];
    }

    private function forgetCache(): void
    {
        app(SpatiePermissionRegistrar::class)->forgetCachedPermissions();
    }
}