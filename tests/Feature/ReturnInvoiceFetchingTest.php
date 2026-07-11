<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReturnInvoiceFetchingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $activeCompany;

    private Company $previousCompany;

    private Company $inaccessibleCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'invoices.search']),
            Permission::firstOrCreate(['name' => 'invoices.get-items']),
        ]);

        $this->activeCompany = Company::factory()->create(['name' => 'Current fiscal year', 'fiscal_year' => 1405]);
        $this->previousCompany = Company::factory()->create(['name' => 'Previous fiscal year', 'fiscal_year' => 1404]);
        $this->inaccessibleCompany = Company::factory()->create(['name' => 'Hidden fiscal year', 'fiscal_year' => 1403]);

        $this->user->companies()->sync([$this->activeCompany->id, $this->previousCompany->id]);

        config(['active-company-id' => $this->activeCompany->id]);
        $this->actingAs($this->user);
    }

    public function test_return_invoice_search_can_include_accessible_previous_year_invoices(): void
    {
        $currentInvoice = $this->makeInvoice($this->activeCompany, InvoiceType::SELL, 1001, 'Shared return source current');
        $previousInvoice = $this->makeInvoice($this->previousCompany, InvoiceType::SELL, 1002, 'Shared return source previous');
        $blockedInvoice = $this->makeInvoice($this->inaccessibleCompany, InvoiceType::SELL, 1003, 'Shared return source blocked');

        $response = $this->getJson(route('invoices.search', ['invoice_type' => 'return_sell']).'?'.http_build_query([
            'q' => 'Shared return source',
            'includeLastYears' => true,
            'accessibleCompanyIds' => [
                $this->activeCompany->id,
                $this->previousCompany->id,
                $this->inaccessibleCompany->id,
            ],
        ]));

        $response->assertOk()
            ->assertJsonFragment(['id' => $currentInvoice->id, 'company_id' => $this->activeCompany->id])
            ->assertJsonFragment(['id' => $previousInvoice->id, 'company_id' => $this->previousCompany->id])
            ->assertJsonMissing(['id' => $blockedInvoice->id, 'company_id' => $this->inaccessibleCompany->id]);
    }

    public function test_return_invoice_search_stays_in_active_company_without_include_last_year(): void
    {
        $currentInvoice = $this->makeInvoice($this->activeCompany, InvoiceType::BUY, 2001, 'Buy source current');
        $previousInvoice = $this->makeInvoice($this->previousCompany, InvoiceType::BUY, 2002, 'Buy source previous');

        $response = $this->getJson(route('invoices.search', ['invoice_type' => 'return_buy']).'?'.http_build_query([
            'q' => 'Buy source',
            'includeLastYears' => false,
            'accessibleCompanyIds' => [$this->activeCompany->id, $this->previousCompany->id],
        ]));

        $response->assertOk()
            ->assertJsonFragment(['id' => $currentInvoice->id, 'company_id' => $this->activeCompany->id])
            ->assertJsonMissing(['id' => $previousInvoice->id, 'company_id' => $this->previousCompany->id]);
    }

    public function test_return_invoice_items_can_be_loaded_from_accessible_previous_year_invoice(): void
    {
        $invoice = $this->makeInvoice($this->previousCompany, InvoiceType::SELL, 3001, 'Previous invoice with items');
        $product = $this->makeProduct($this->previousCompany, 'Previous year product');
        $currentProduct = $this->makeProduct($this->activeCompany, 'Previous year product');

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => 3,
            'unit_price' => 250,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => 750,
            'description' => 'Fetched from previous year',
        ]);

        $response = $this->getJson(route('invoices.get-items', $invoice).'?'.http_build_query([
            'includeLastYears' => true,
            'accessibleCompanyIds' => [$this->previousCompany->id],
        ]));

        $response->assertOk()->assertJsonFragment([
            'product_id' => $currentProduct->id,
            'quantity' => 3,
            'unit' => 250,
            'desc' => 'Fetched from previous year',
        ]);
    }

    public function test_return_invoice_items_show_missing_current_company_products(): void
    {
        $invoice = $this->makeInvoice($this->previousCompany, InvoiceType::SELL, 3002, 'Previous invoice missing current product');
        $product = $this->makeProduct($this->previousCompany, 'Missing current product');

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => 2,
            'unit_price' => 100,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => 200,
            'description' => 'Cannot return without current product',
        ]);

        $response = $this->getJson(route('invoices.get-items', $invoice).'?'.http_build_query([
            'includeLastYears' => true,
            'accessibleCompanyIds' => [$this->previousCompany->id],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('returned_invoice_id')
            ->assertJsonFragment(['returned_invoice_id' => [
                'Products or services of this invoice are not available in current company: Missing current product. Create them in current company before returning this invoice.',
            ]]);
    }

    private function makeInvoice(Company $company, InvoiceType $type, int $number, string $title): Invoice
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'name' => "{$company->name} customer",
            'company_id' => $company->id,
        ]);

        return Invoice::withoutEvents(fn () => Invoice::withoutGlobalScopes()->create([
            'number' => $number,
            'date' => '2026-01-15',
            'invoice_type' => $type,
            'status' => InvoiceStatus::APPROVED,
            'customer_id' => $customer->id,
            'creator_id' => $this->user->id,
            'company_id' => $company->id,
            'title' => $title,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 0,
        ]));
    }

    private function makeProduct(Company $company, string $name): Product
    {
        return Product::withoutEvents(fn () => Product::withoutGlobalScopes()->create([
            'name' => $name,
            'company_id' => $company->id,
            'quantity' => 10,
            'quantity_warning' => 0,
            'oversell' => 0,
            'selling_price' => 250,
            'vat' => 0,
            'average_cost' => 100,
        ]));
    }
}
