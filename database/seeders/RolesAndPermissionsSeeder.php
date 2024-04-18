<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::create(['name' => 'setting manager']);
        Permission::create(['name' => 'accounting reporter']);
        Permission::create(['name' => 'seller']);
        Permission::create(['name' => 'moadian manager']);
        Permission::create(['name' => 'accountant']);

        // create roles and assign created permissions
        $role = Role::create(['name' => __('seller')]);
        $role->givePermissionTo('seller');

        $role = Role::create(['name' => __('reporter')]);
        $role->givePermissionTo('accounting reporter');

        $role = Role::create(['name' => __('accountant')])
            ->givePermissionTo(['accountant', 'moadian manager']);

        $role = Role::create(['name' => __('manager')]);
        $role->givePermissionTo(Permission::all());
    }
}
