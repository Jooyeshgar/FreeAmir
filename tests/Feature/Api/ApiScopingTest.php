<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\ProductGroup;
use App\Models\ServiceGroup;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class ApiScopingTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private FiscalYear $fiscalYear;

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
        'configs.index',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fiscalYear = FiscalYear::factory()->create(['year' => 1404]);
        $this->user = User::factory()->create();
        $this->fiscalYear->users()->attach($this->user);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);

        $this->importSubjects($this->fiscalYear->id);
        $this->importConfigs($this->fiscalYear->id);

        ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        ServiceGroup::factory()->withSubject()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['fiscal_year_id' => $this->fiscalYear->id]);

        $this->customer = Customer::factory()
            ->withGroup($customerGroup)
            ->withSubject()
            ->create();

        Warehouse::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => 'انبار اصلی',
            'code' => 'MAIN',
        ]);

        foreach ($this->permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->user->createToken('test', $this->permissions)->plainTextToken,
        ];
    }

    private function apiUrl(string $path): string
    {
        return '/api/companies/'.$this->fiscalYear->id.$path;
    }

    public function test_companies_index_lists_accessible_fiscal_year(): void
    {
        $this->getJson('/api/companies', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->fiscalYear->id)
            ->assertJsonPath('data.0.fiscal_year', (int) $this->fiscalYear->year)
            ->assertJsonMissingPath('data.0.company_id');
    }

    public function test_fiscal_years_index_uses_company_id_key(): void
    {
        $this->getJson($this->apiUrl('/fiscal-years'), $this->headers())
            ->assertOk()
            ->assertJsonPath('data.fiscal_years.0.id', $this->fiscalYear->id)
            ->assertJsonPath('data.fiscal_years.0.company_id', $this->fiscalYear->company_id)
            ->assertJsonPath('data.meta.company_id', $this->fiscalYear->company_id)
            ->assertJsonPath('data.meta.current_fiscal_year_id', $this->fiscalYear->id);
    }

    public function test_customers_endpoints_respect_fiscal_year_scope(): void
    {
        $this->getJson($this->apiUrl('/customers'), $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->customer->id);

        $otherYear = FiscalYear::create(['company_id' => $this->fiscalYear->company_id, 'year' => 1405]);
        $this->user->fiscalYears()->syncWithoutDetaching([$otherYear->id]);

        DB::table('customers')->where('id', $this->customer->id)->update([
            'fiscal_year_id' => $otherYear->id,
        ]);

        $this->getJson($this->apiUrl("/customers/{$this->customer->id}"), $this->headers())
            ->assertNotFound();
    }

    public function test_customer_store_assigns_fiscal_year(): void
    {
        $groupId = DB::table('customer_groups')
            ->where('fiscal_year_id', $this->fiscalYear->id)
            ->value('id');

        $response = $this->postJson($this->apiUrl('/customers'), [
            'name' => 'مشتری تستی',
            'type' => 'individual',
            'group_id' => $groupId,
            'personal_code' => '1234567890',
        ], $this->headers())->assertCreated();

        $row = DB::table('customers')->find($response->json('data.id'));

        $this->assertSame($this->fiscalYear->id, (int) $row->fiscal_year_id);
    }

    public function test_product_and_service_store_use_current_fiscal_year(): void
    {
        $productGroupId = DB::table('product_groups')->where('fiscal_year_id', $this->fiscalYear->id)->value('id');
        $serviceGroupId = DB::table('service_groups')->where('fiscal_year_id', $this->fiscalYear->id)->value('id');

        $this->postJson($this->apiUrl('/products'), [
            'name' => 'کالای تستی',
            'code' => 'P-1',
            'group' => $productGroupId,
        ], $this->headers())->assertCreated();

        $this->postJson($this->apiUrl('/services'), [
            'name' => 'خدمت تستی',
            'code' => 'S-1',
            'group' => $serviceGroupId,
        ], $this->headers())->assertCreated();

        foreach (['products' => 'P-1', 'services' => 'S-1'] as $table => $code) {
            $row = DB::table($table)->where('code', $code)->first();

            $this->assertNotNull($row, $table.' was not created');
            $this->assertSame($this->fiscalYear->id, (int) $row->fiscal_year_id);
        }
    }

    public function test_subjects_config_documents_endpoints_respond(): void
    {
        $this->getJson($this->apiUrl('/subjects'), $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.code', '010');

        $this->getJson($this->apiUrl('/config?only_mapped=1'), $this->headers())
            ->assertOk()
            ->assertJsonPath('data.meta.fiscal_year_id', $this->fiscalYear->id);

        $document = $this->postJson($this->apiUrl('/documents'), [
            'title' => 'سند تستی',
            'date' => now()->toDateString(),
            'transactions' => [
                ['subject_id' => 1, 'desc' => 'بدهکار', 'value' => -1000],
                ['subject_id' => 2, 'desc' => 'بستانکار', 'value' => 1000],
            ],
        ], $this->headers())->assertCreated();

        $this->getJson($this->apiUrl("/documents/{$document->json('data.id')}"), $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_balanced', true);

        $this->postJson(
            $this->apiUrl("/documents/{$document->json('data.id')}/status/approved"),
            [],
            $this->headers()
        )->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_product_and_service_store_reuse_identity_and_reject_code_conflicts(): void
    {
        $productGroupId = DB::table('product_groups')->where('fiscal_year_id', $this->fiscalYear->id)->value('id');
        $serviceGroupId = DB::table('service_groups')->where('fiscal_year_id', $this->fiscalYear->id)->value('id');

        $productPayload = ['name' => 'کالای تستی', 'code' => 'P-1', 'group' => $productGroupId];
        $servicePayload = ['name' => 'خدمت تستی', 'code' => 'S-1', 'group' => $serviceGroupId];

        $createdProduct = $this->postJson($this->apiUrl('/products'), $productPayload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('reused', false);

        $createdService = $this->postJson($this->apiUrl('/services'), $servicePayload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('reused', false);

        // Same identity is reused instead of creating a duplicate
        $this->postJson($this->apiUrl('/products'), $productPayload, $this->headers())
            ->assertOk()
            ->assertJsonPath('reused', true)
            ->assertJsonPath('data.id', $createdProduct->json('data.id'));

        $this->postJson($this->apiUrl('/services'), $servicePayload, $this->headers())
            ->assertOk()
            ->assertJsonPath('reused', true)
            ->assertJsonPath('data.id', $createdService->json('data.id'));

        // Same code with a different identity is rejected with 409
        $this->postJson($this->apiUrl('/products'), ['name' => 'کالای دیگر', 'code' => 'P-1', 'group' => $productGroupId], $this->headers())
            ->assertStatus(409);

        $this->postJson($this->apiUrl('/services'), ['name' => 'خدمت دیگر', 'code' => 'S-1', 'group' => $serviceGroupId], $this->headers())
            ->assertStatus(409);

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('services', 1);
    }

    public function test_product_and_service_store_validate_group_in_fiscal_year(): void
    {
        $otherYear = FiscalYear::create(['company_id' => $this->fiscalYear->company_id, 'year' => 1405]);
        $foreignGroup = ProductGroup::factory()->create(['fiscal_year_id' => $otherYear->id]);

        $this->postJson($this->apiUrl('/products'), [
            'name' => 'کالای خارج از سال',
            'code' => 'P-FORBIDDEN',
            'group' => $foreignGroup->id,
        ], $this->headers())->assertStatus(422)->assertJsonValidationErrors(['group']);

        $this->postJson($this->apiUrl('/services'), [
            'name' => 'خدمت با گروه نامعتبر',
            'code' => 'S-FORBIDDEN',
            'group' => 999999,
        ], $this->headers())->assertStatus(422)->assertJsonValidationErrors(['group']);
    }

    public function test_document_store_requires_ledger_confirmation_when_commercial_ledger_covers_the_date(): void
    {
        \App\Models\CommercialLedgerExport::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'creator_id' => $this->user->id,
            'from_date' => now()->subDays(5)->toDateString(),
            'to_date' => now()->addDays(5)->toDateString(),
            'format' => 'pdf',
            'seal_tracking_code' => 'TEST-0001',
            'ledger_type' => \App\Enums\CommercialLedgerType::ALL_GENERAL,
            'status' => 'ready',
            'file_path' => 'commercial-ledgers/test.pdf',
        ]);

        $payload = [
            'title' => 'سند تستی',
            'date' => now()->toDateString(),
            'transactions' => [
                ['subject_id' => 1, 'desc' => 'بدهکار', 'value' => -1000],
                ['subject_id' => 2, 'desc' => 'بستانکار', 'value' => 1000],
            ],
        ];

        $this->postJson($this->apiUrl('/documents'), $payload, $this->headers())
            ->assertStatus(409)
            ->assertJsonPath('needs_confirmation', true);

        $this->assertDatabaseCount('documents', 0);

        $this->postJson($this->apiUrl('/documents'), array_merge($payload, ['ledger_confirmation' => true]), $this->headers())
            ->assertCreated();

        $this->assertDatabaseCount('documents', 1);
    }
}
