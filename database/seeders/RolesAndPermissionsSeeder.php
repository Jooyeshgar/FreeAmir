<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        Permission::create(['name' => 'home.*']);
        Permission::create(['name' => 'subjects.*']);
        Permission::create(['name' => 'documents.*']);
        Permission::create(['name' => 'products.*']);
        Permission::create(['name' => 'product-groups.*']);
        Permission::create(['name' => 'customers.*']);
        Permission::create(['name' => 'customer-groups.*']);
        Permission::create(['name' => 'companies.*']);
        Permission::create(['name' => 'change-company.*']);
        Permission::create(['name' => 'bank-accounts.*']);
        Permission::create(['name' => 'banks.*']);
        Permission::create(['name' => 'invoices.*']);
        Permission::create(['name' => 'management.*']);
        Permission::create(['name' => 'management.users.*']);
        Permission::create(['name' => 'management.permissions.*']);
        Permission::create(['name' => 'management.roles.*']);
        Permission::create(['name' => 'management.configs.*']);
        Permission::create(['name' => 'reports.*']);
        Permission::create(['name' => 'reports.ledger']);
        Permission::create(['name' => 'reports.journal']);
        Permission::create(['name' => 'reports.sub-ledger']);
        Permission::create(['name' => 'reports.result']);

        // create roles and assign created permissions
        $role = Role::create(['name' => 'Super-Admin']);
        $role->givePermissionTo(Permission::all());

        $role = Role::create(['name' => 'Acountant']);
        $role->givePermissionTo(Permission::where('name', 'NOT LIKE', '%management%')->get());

        $role = Role::create(['name' => 'Warehousekeeper']);
        $role->givePermissionTo(Permission::where('name', 'LIKE', '%products%')
            ->orWhere('name', 'LIKE', '%product-groups%')
            ->orWhere('name', 'LIKE', '%home%')
            ->get());

        $role = Role::create(['name' => 'Seller']);
        $role->givePermissionTo(Permission::where('name', 'LIKE', '%invoices%')
            ->orWhere('name', 'LIKE', '%home%')
            ->get());

        // Create admin user
        $admin = User::create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'), // Replace with a strong password
        ]);

        $admin->companies()->attach(1);

        $admin->assignRole('Super-Admin');
    }
}
