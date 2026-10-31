<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoiceNumberSortPermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    public function test_upgrade_installs_the_permission_without_seeding_and_refreshes_the_cache(): void
    {
        Permission::findByName('invoices.sort-numbers')->delete();
        app(PermissionRegistrar::class)->getPermissions();

        $migration = require database_path('migrations/2026_10_31_090000_add_invoice_number_sort_permission.php');
        $migration->up();

        $this->assertSame('web', Permission::findByName('invoices.sort-numbers')->guard_name);
    }

    public function test_upgrade_grants_existing_roles_without_changing_other_permissions(): void
    {
        $allInvoices = Permission::findOrCreate('invoices.*');
        $sortDocuments = Permission::findOrCreate('documents.sort-numbers');
        $customSorter = Role::create(['name' => 'Custom Sorter']);
        $customSorter->givePermissionTo($sortDocuments);
        $readInvoices = Permission::findOrCreate('invoices.index');
        $invoiceManager = Role::create(['name' => 'Invoice Manager']);
        $invoiceManager->givePermissionTo($allInvoices);
        $reader = Role::create(['name' => 'Invoice Reader']);
        $reader->givePermissionTo($readInvoices);
        $otherGuard = Role::create(['name' => 'Accountant', 'guard_name' => 'api']);
        $defaults = collect(['Super-Admin', __('Admin'), __('Accountant'), __('Seller')])->map(fn (string $name) => Role::create(['name' => $name, 'guard_name' => 'web']));

        $migration = require database_path('migrations/2026_10_31_090000_add_invoice_number_sort_permission.php');
        $migration->up();
        $migration->up();

        foreach ($defaults->push($invoiceManager, $customSorter) as $role) {
            $this->assertTrue($role->fresh()->hasPermissionTo('invoices.sort-numbers'));
        }
        $this->assertTrue($reader->fresh()->hasPermissionTo('invoices.index'));
        $this->assertFalse($reader->fresh()->hasPermissionTo('invoices.sort-numbers'));
        $this->assertTrue($customSorter->fresh()->hasPermissionTo('documents.sort-numbers'));
        $this->assertCount(0, $otherGuard->fresh()->permissions);
        $this->assertSame(1, Permission::where('name', 'invoices.sort-numbers')->where('guard_name', 'web')->count());
    }

    public function test_upgrade_preserves_direct_and_custom_assignments_and_clears_cached_permissions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::findOrCreate('documents.sort-numbers'),
            Permission::findOrCreate('invoices.*'),
        ]);
        $customUser = User::factory()->create();
        $customUser->givePermissionTo(Permission::findOrCreate('invoices.sort-numbers'));
        $reader = User::factory()->create();
        $reader->givePermissionTo(Permission::findOrCreate('invoices.index'));
        // Warm the permission cache before the migration writes assignments directly.
        $this->assertFalse($reader->hasPermissionTo('invoices.sort-numbers'));

        $migration = require database_path('migrations/2026_10_31_090000_add_invoice_number_sort_permission.php');
        $migration->up();
        $migration->up();

        $this->assertTrue($user->fresh()->hasDirectPermission('invoices.sort-numbers'));
        $this->assertTrue($customUser->fresh()->hasDirectPermission('invoices.sort-numbers'));
        $this->assertFalse($reader->fresh()->hasPermissionTo('invoices.sort-numbers'));
        $this->assertCount(3, $user->fresh()->permissions);
        $migration->down();
        $this->assertTrue($customUser->fresh()->hasDirectPermission('invoices.sort-numbers'));
    }
}
