<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ManagementCompanyOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['app.email_verification' => false]);
    }

    public function test_super_admin_sees_grouped_fiscal_year_usage_users_roles_and_actions(): void
    {
        $admin = $this->platformAdmin(['fiscal-years.edit', 'users.impersonate']);
        $newFiscalYear = $this->fiscalYear(1404);
        $newFiscalYear->company->update(['name' => 'Grouped Business']);
        $oldFiscalYear = $this->fiscalYear(1403, ['company_id' => $newFiscalYear->company_id, 'closed_at' => now()]);
        $otherFiscalYear = $this->fiscalYear(1404);
        $accountant = User::factory()->create(['name' => 'Grouped Accountant', 'email' => 'accountant@grouped.test']);
        $accountant->assignRole(Role::create(['name' => 'Business Accountant']));
        $accountant->fiscalYears()->attach([$newFiscalYear->id, $oldFiscalYear->id]);
        $rolelessUser = User::factory()->create(['name' => 'Roleless Operator', 'email' => 'roleless@grouped.test']);
        $rolelessUser->fiscalYears()->attach($oldFiscalYear);
        $outsider = User::factory()->create(['name' => 'Outside User']);
        $outsider->fiscalYears()->attach($otherFiscalYear);

        $this->document($newFiscalYear, $admin, 1);
        $this->document($newFiscalYear, $admin, 2);
        $this->document($oldFiscalYear, $admin, 3);
        $this->invoice($newFiscalYear, 101);
        $this->invoice($oldFiscalYear, 102);

        $response = $this->actingAs($admin)->get(route('companies.show', $oldFiscalYear->company));

        $response->assertOk()
            ->assertViewHas('business', fn (Company $company): bool => $company->is($newFiscalYear->company))
            ->assertViewHas('fiscalYears', fn ($companies): bool => $companies->pluck('id')->all() === [$newFiscalYear->id, $oldFiscalYear->id]
                && (int) $companies->firstWhere('id', $newFiscalYear->id)->documents_count === 2
                && (int) $companies->firstWhere('id', $oldFiscalYear->id)->invoices_count === 1)
            ->assertViewHas('metrics', [
                'fiscalYears' => 2,
                'openFiscalYears' => 1,
                'users' => 2,
                'documents' => 3,
                'invoices' => 2,
            ])
            ->assertSee('Grouped Business')
            ->assertSee(localizeNumber(1404))
            ->assertSee(localizeNumber(1403))
            ->assertSee('Grouped Accountant')
            ->assertSee('Business Accountant')
            ->assertSee('Roleless Operator')
            ->assertSee(__('No roles assigned'))
            ->assertSee(route('users.show', $accountant), false)
            ->assertSee(route('users.impersonate', $accountant), false)
            ->assertSee(route('fiscal-years.edit', $newFiscalYear), false)
            ->assertDontSee('Outside User');
    }

    public function test_company_overview_handles_a_business_without_users_or_usage(): void
    {
        $admin = $this->platformAdmin();
        $fiscalYear = $this->fiscalYear(1404);
        $fiscalYear->company->update(['name' => 'Empty Business']);

        $this->actingAs($admin)->get(route('companies.show', $fiscalYear->company))
            ->assertOk()
            ->assertSee(__('No users are assigned to this business.'))
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['users'] === 0
                && $metrics['documents'] === 0
                && $metrics['invoices'] === 0);
    }

    public function test_company_overview_is_for_platform_administrators_only(): void
    {
        $fiscalYear = $this->fiscalYear(1404);
        $companyAdmin = User::factory()->create();
        $companyAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.show']));
        $companyAdmin->fiscalYears()->attach($fiscalYear);

        $this->actingAs($companyAdmin)->get(route('companies.show', $fiscalYear->company))->assertForbidden();
    }

    public function test_management_company_references_link_to_the_grouped_overview(): void
    {
        $admin = $this->platformAdmin(['companies.index']);
        $fiscalYear = $this->fiscalYear(1404);
        $target = User::factory()->create(['name' => 'Linked User']);
        $target->fiscalYears()->attach($fiscalYear);
        $this->document($fiscalYear, $admin, 1);
        Activity::create([
            'log_name' => 'request',
            'description' => 'Viewed linked business',
            'event' => 'get',
            'user_id' => $admin->id,
            'properties' => [
                'route' => 'companies.index',
                'fiscal_year_id' => $fiscalYear->id,
            ],
        ]);
        $overviewUrl = route('companies.show', $fiscalYear->company);

        $this->actingAs($admin)->withSession(['interface_mode' => 'management'])
            ->get(route('companies.index'))->assertOk()->assertSee($overviewUrl, false);
        $this->get(route('management.dashboard'))->assertOk()->assertSee($overviewUrl, false);
        $this->get(route('management.activity-logs.index'))->assertOk()->assertSee($overviewUrl, false);
        $this->get(route('users.show', $target))->assertOk()->assertSee($overviewUrl, false);
    }

    /** @param array<int, string> $extraPermissions */
    private function platformAdmin(array $extraPermissions = []): User
    {
        $admin = User::factory()->create();

        foreach (['access-super-admin-panel', 'companies.show', ...$extraPermissions] as $permission) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        return $admin;
    }

    private function fiscalYear(int $fiscalYear, array $attributes = []): FiscalYear
    {
        $year = FiscalYear::factory()->create([
            'year' => $fiscalYear,
            ...$attributes,
        ]);
        $year->users()->detach();

        return $year;
    }

    private function document(FiscalYear $fiscalYear, User $creator, int $number): void
    {
        Document::withoutGlobalScopes()->create([
            'number' => $number,
            'date' => now()->toDateString(),
            'creator_id' => $creator->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);
    }

    private function invoice(FiscalYear $fiscalYear, int $number): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Customer '.$number,
            'fiscal_year_id' => $fiscalYear->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoices')->insert([
            'number' => $number,
            'date' => now()->toDateString(),
            'fiscal_year_id' => $fiscalYear->id,
            'customer_id' => $customerId,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 100,
            'invoice_type' => InvoiceType::SELL->value,
            'status' => InvoiceStatus::APPROVED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
