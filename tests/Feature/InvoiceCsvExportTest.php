<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoiceCsvExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'invoices.export'])
        );

        $this->withCookies(['active-company-id' => $this->companyId]);
        config(['active-company-id' => $this->companyId]);
        App::setLocale('en');

        $this->customer = Customer::create(['name' => 'Alpha Customer', 'company_id' => $this->companyId]);
    }

    public function test_export_uses_filters_and_writes_the_nine_requested_columns(): void
    {
        $document = Document::create([
            'number' => 7001,
            'date' => '2026-06-10',
            'creator_id' => $this->user->id,
            'company_id' => $this->companyId,
        ]);

        $included = $this->invoice([
            'number' => 1001,
            'date' => '2026-06-10',
            'document_id' => $document->id,
            'amount' => 1040,
            'subtraction' => 20,
        ]);
        $this->item($included, quantity: 2, unitPrice: 500, discount: 50, vat: 90);

        $otherCustomer = Customer::create(['name' => 'Other Customer', 'company_id' => $this->companyId]);
        $excludedByText = $this->invoice(['number' => 1002, 'customer_id' => $otherCustomer->id]);
        $this->item($excludedByText, quantity: 1, unitPrice: 300);

        $excludedByType = $this->invoice(['number' => 1003, 'invoice_type' => InvoiceType::BUY]);
        $this->item($excludedByType, quantity: 1, unitPrice: 400);

        $response = $this->actingAs($this->user)->get(route('invoices.export', [
            'invoice_type' => 'sell',
            'status' => 'approved',
            'text' => 'Alpha',
            'start_date' => '2026/06/01',
            'end_date' => '2026/06/30',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $rows = $this->csvRows($response->streamedContent());

        $this->assertSame([
            'Invoice Number',
            'Customer Name',
            'Date',
            'Document Number',
            'Before discounts and tax',
            'Total deductions',
            'Collected tax',
            'Payable amount',
            'amount - discounts',
        ], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertEquals(1001, (float) $rows[1][0]);
        $this->assertSame('Alpha Customer', $rows[1][1]);
        $this->assertSame('2026/06/10', $rows[1][2]);
        $this->assertEquals(7001, (float) $rows[1][3]);
        $this->assertEquals(1000, (float) $rows[1][4]);
        $this->assertEquals(50, (float) $rows[1][5]);
        $this->assertEquals(90, (float) $rows[1][6]);
        $this->assertEquals(1020, (float) $rows[1][7]);
        $this->assertEquals(950, (float) $rows[1][8]);
    }

    public function test_export_translates_column_titles(): void
    {
        App::setLocale('fa');

        $response = $this->actingAs($this->user)->get(route('invoices.export', ['invoice_type' => 'sell']));

        $headers = $this->csvRows($response->streamedContent())[0];

        $this->assertSame([
            'شماره فاکتور',
            'نام مشتری',
            'تاریخ',
            'شماره',
            'قبل از تخفیف و مالیات',
            'مجموع کسورات',
            'مالیات جمع آوری شده',
            'مبلغ قابل پرداخت',
            'مبلغ پس از تخفیف',
        ], $headers);
    }

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'number' => random_int(2000, 9000),
            'date' => '2026-06-10',
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 0,
        ], $attributes));
    }

    private function item(Invoice $invoice, float $quantity, float $unitPrice, float $discount = 0, float $vat = 0): InvoiceItem
    {
        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_discount' => $discount,
            'vat' => $vat,
            'amount' => ($quantity * $unitPrice) - $discount + $vat,
        ]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvRows(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));

        return array_map('str_getcsv', $lines);
    }
}
