<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\ServiceGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class ApiScopingTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private Company $company;

    private User $user;

    private Customer $customer;

    private array $permissions = [
        'api.access',
        'companies.index',
        'customers.index', 'customers.show', 'customers.store',
        'products.index', 'products.store',
        'services.index', 'services.store',
        'subjects.index', 'subjects.show',
        'documents.store', 'documents.show', 'documents.index', 'documents.approve',
        'hr.employees.index', 'hr.employees.store',
        'attendance.attendance-logs.index', 'attendance.attendance-logs.store',
        'configs.index',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);

        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id]);
        ServiceGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);

        $this->customer = Customer::factory()
            ->withGroup($customerGroup)
            ->withSubject()
            ->create(['company_id' => $this->company->id]);

        Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'انبار اصلی',
            'code' => 'MAIN',
        ]);

        foreach ($this->permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->user->createToken('test', $this->permissions)->plainTextToken,
        ];
    }

    private function scopedFiscalYearId(): int
    {
        return (int) $this->company->fiscalYear->id;
    }

    public function test_companies_index_lists_accessible_fiscal_year(): void
    {
        $this->getJson('/api/companies', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->company->id)
            ->assertJsonPath('data.0.fiscal_year_id', $this->scopedFiscalYearId())
            ->assertJsonMissingPath('data.0.company_id');
    }

    public function test_fiscal_years_index_uses_company_id_key(): void
    {
        $this->getJson("/api/companies/{$this->company->id}/fiscal-years", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.fiscal_years.0.company_id', $this->company->id)
            ->assertJsonPath('data.fiscal_years.0.id', $this->scopedFiscalYearId())
            ->assertJsonPath('data.meta.company_id', $this->company->id);
    }

    public function test_customers_endpoints_respect_fiscal_year_scope(): void
    {
        $this->getJson("/api/companies/{$this->company->id}/customers", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->customer->id);

        $otherFiscalYear = Company::factory()->create()->fiscalYear;
        DB::table('customers')->where('id', $this->customer->id)->update([
            'fiscal_year_id' => $otherFiscalYear->id,
        ]);

        $this->getJson("/api/companies/{$this->company->id}/customers/{$this->customer->id}", $this->headers())
            ->assertNotFound();
    }

    public function test_customer_store_assigns_company_and_fiscal_year(): void
    {
        $groupId = DB::table('customer_groups')
            ->where('fiscal_year_id', $this->scopedFiscalYearId())
            ->value('id');

        $response = $this->postJson("/api/companies/{$this->company->id}/customers", [
            'name' => 'مشتری تستی',
            'type' => 'individual',
            'group_id' => $groupId,
            'personal_code' => '1234567890',
        ], $this->headers())->assertCreated();

        $row = DB::table('customers')->find($response->json('data.id'));

        $this->assertSame($this->company->id, (int) $row->company_id);
        $this->assertSame($this->scopedFiscalYearId(), (int) $row->fiscal_year_id);
    }

    public function test_product_and_service_store_use_current_fiscal_year(): void
    {
        $productGroupId = DB::table('product_groups')->where('fiscal_year_id', $this->scopedFiscalYearId())->value('id');
        $serviceGroupId = DB::table('service_groups')->where('fiscal_year_id', $this->scopedFiscalYearId())->value('id');

        $this->postJson("/api/companies/{$this->company->id}/products", [
            'name' => 'کالای تستی',
            'code' => 'P-1',
            'group' => $productGroupId,
        ], $this->headers())->assertCreated();

        $this->postJson("/api/companies/{$this->company->id}/services", [
            'name' => 'خدمت تستی',
            'code' => 'S-1',
            'group' => $serviceGroupId,
        ], $this->headers())->assertCreated();

        foreach (['products' => 'P-1', 'services' => 'S-1'] as $table => $code) {
            $row = DB::table($table)->where('code', $code)->first();

            $this->assertNotNull($row, $table.' was not created');
            $this->assertSame($this->company->id, (int) $row->company_id);
            $this->assertSame($this->scopedFiscalYearId(), (int) $row->fiscal_year_id);
        }
    }

    public function test_subjects_config_documents_endpoints_respond(): void
    {
        $this->getJson("/api/companies/{$this->company->id}/subjects", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.code', '010');

        $this->getJson("/api/companies/{$this->company->id}/config?only_mapped=1", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.meta.fiscal_year_id', $this->scopedFiscalYearId());

        $document = $this->postJson("/api/companies/{$this->company->id}/documents", [
            'title' => 'سند تستی',
            'number' => 5001,
            'date' => now()->toDateString(),
            'transactions' => [
                ['subject_id' => 1, 'desc' => 'بدهکار', 'value' => -1000],
                ['subject_id' => 2, 'desc' => 'بستانکار', 'value' => 1000],
            ],
        ], $this->headers())->assertCreated();

        $this->getJson("/api/companies/{$this->company->id}/documents/{$document->json('data.id')}", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_balanced', true);

        $this->postJson(
            "/api/companies/{$this->company->id}/documents/{$document->json('data.id')}/status/approved",
            [],
            $this->headers()
        )->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_employee_and_attendance_store_without_explicit_company_id(): void
    {
        $workSite = WorkSite::create(['company_id' => $this->company->id, 'name' => 'کارگاه تستی', 'code' => 'WS-1']);
        $workShift = WorkShift::create([
            'company_id' => $this->company->id,
            'name' => 'شیفت تستی',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $employee = $this->postJson("/api/companies/{$this->company->id}/employees", [
            'code' => 'E-1',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'nationality' => 'iranian',
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ], $this->headers())->assertCreated();

        $row = DB::table('employees')->find($employee->json('data.id'));

        $this->assertSame($this->company->id, (int) $row->company_id);
        $this->assertSame($this->scopedFiscalYearId(), (int) $row->fiscal_year_id);

        $this->postJson("/api/companies/{$this->company->id}/attendance/logs", [
            'employee_id' => $row->id,
            'log_date' => now()->toDateString(),
            'entry_time' => '08:00',
            'exit_time' => '17:00',
        ], $this->headers())->assertCreated();

        $log = DB::table('attendance_logs')->where('employee_id', $row->id)->first();

        $this->assertNotNull($log);
        $this->assertSame($this->company->id, (int) $log->company_id);
        $this->assertSame($this->scopedFiscalYearId(), (int) $log->fiscal_year_id);

        $this->getJson(
            "/api/companies/{$this->company->id}/attendance/logs?employee_id={$row->id}&date_from=".now()->toDateString().'&date_to='.now()->toDateString(),
            $this->headers()
        )->assertOk()->assertJsonCount(1, 'data');
    }
}
