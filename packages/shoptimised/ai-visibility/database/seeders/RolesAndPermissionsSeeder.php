<?php

namespace Shoptimised\AiVisibility\Database\Seeders;

use Illuminate\Database\Seeder;
use Shoptimised\AiVisibility\Enums\Role as RoleEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view_reports',
            'create_batches',
            'manage_batches',
            'approve_recommendations',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Reload the registrar's cache so the just-created permissions are
        // resolvable by name when syncing them onto roles below.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $matrix = [
            RoleEnum::ShoptimisedAdmin->value => $permissions,
            RoleEnum::ShoptimisedAnalyst->value => ['view_reports', 'create_batches', 'manage_batches', 'approve_recommendations'],
            RoleEnum::RetailerAdmin->value => ['view_reports', 'create_batches', 'manage_batches'],
            RoleEnum::RetailerViewer->value => ['view_reports'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($grants);
        }
    }
}
