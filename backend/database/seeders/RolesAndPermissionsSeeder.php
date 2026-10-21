<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Production-safe: defines roles and permissions from the enums. Idempotent; re-run after changes.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');
            $role->syncPermissions(array_map(fn ($p) => $p->value, $roleName->permissions()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
