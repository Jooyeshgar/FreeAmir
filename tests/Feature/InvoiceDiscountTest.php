<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\MoadianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class InvoiceDiscountTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $fiscalYearId;

    protected int $nextInvoiceNumber = 9500;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->app->setLocale('en');
        $year = FiscalYear::factory()->create(['year' => 1405]);
        $this->fiscalYearId = $year->id;
        config(['active-fiscal-year-id' => $year->id]);
        $this->withCookies(['active-fiscal-year-id' => (string) $year->id]);
        $this->user = User::factory()->create();
        $this->user->fiscalYears()->attach($year);
        foreach (['invoices.store', 'invoices.update', 'invoices.show', 'invoices.print', 'invoices.export'] as $name) {
            $this->user->givePermissionTo(Permission::findOrCreate($name));
        }
        $this->actingAs($this->user);
        $this->withSession(['locale' => 'en']);
        $this->importSubjects($year->id);
        $this->importConfigs($year->id);
        ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => $year->id]);
        $group = CustomerGroup::factory()->withSubject()->create(['fiscal_year_id' => $year->id]);
        $this->customer = Customer::factory()->withGroup($group)->withSubject()->create(['fiscal_year_id' => $year->id, 'type' => CustomerType::INDIVIDUAL]);
    }

    public static function discountCases(): array
    {
        $cases = [];
        foreach ([InvoiceType::BUY, InvoiceType::SELL, InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL] as $type) {
            foreach ([0, 50] as $discount) {
                $cases[$type->name.' '.$discount] = [$type, $discount];
            }
        }

        return $cases;
    }

    #[DataProvider('discountCases')]
    public function test_discount_is_a_line_amount_in_totals_vat_and_balanced_postings(InvoiceType $type, float $discount): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $purchase = $this->buy([$this->productItem($product, 10, 500)])['invoice'];
        $item = [...$this->productItem($product, 2, 500), 'discount' => $discount, 'vat' => 10];
        $invoice = match ($type) {
            InvoiceType::BUY => $this->buy([$item])['invoice'],
            InvoiceType::SELL => $this->sell([$item])['invoice'],
            InvoiceType::RETURN_BUY => $this->returnBuy([$item], $purchase->id)['invoice'],
            InvoiceType::RETURN_SELL => $this->returnSell([$item], $this->sell([$this->productItem($product, 4, 500)])['invoice']->id)['invoice'],
        };
        $line = $invoice->items()->sole();
        $vat = (1000 - $discount) * 0.1;
        $this->assertEquals($discount, $line->discount);
        $this->assertEquals($vat, $line->vat);
        $this->assertEquals(1000 - $discount + $vat, $line->amount);
        $this->assertEquals($line->amount, $invoice->amount);
        $this->assertEquals($vat, $invoice->vat);
        $this->assertEqualsWithDelta(0, $invoice->document->transactions()->sum('value'), 0.001);
        if ($discount > 0) {
            $subject = config($type->isSell() ? 'amir.sell_discount' : 'amir.buy_discount');
            $sign = in_array($type, [InvoiceType::SELL, InvoiceType::RETURN_BUY]) ? -1 : 1;
            $this->assertEquals($sign * $discount, $invoice->document->transactions()->where('subject_id', $subject)->sum('value'));
        }
        $this->assertEquals($discount, InvoiceService::prepareTransactions($invoice, 'edit')->sole()['off']);
    }

    public static function requestDiscountCases(): array
    {
        return [['discount', 0], ['discount', 50], ['off', 50]];
    }

    #[DataProvider('requestDiscountCases')]
    public function test_requests_create_update_and_validate_discount(string $field, float $initialDiscount): void
    {
        $product = $this->createProduct();
        $payload = [
            'title' => 'Discount invoice',
            'date' => convertToJalali('2026-06-10', true),
            'invoice_type' => 'buy',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $product->warehouseStocks()->value('warehouse_id'),
            'invoice_number' => 9600,
            'document_number' => 9600,
            'transactions' => [[
                'item_id' => 'product-'.$product->id,
                'quantity' => 2, 'unit' => 500, $field => $initialDiscount, 'vat' => 10, 'total' => (1000 - $initialDiscount) * 1.1,
            ]],
        ];
        $this->post(route('invoices.store'), $payload)->assertSessionHasNoErrors();
        $invoice = Invoice::where('number', 9600)->sole();
        $payload['invoice_id'] = $invoice->id;
        $this->assertEquals($initialDiscount, $invoice->items()->sole()->discount);
        $this->assertEqualsWithDelta((1000 - $initialDiscount) * 1.1, $invoice->amount, 0.001);

        foreach ([0, 100] as $discount) {
            $payload['transactions'][0]['discount'] = $discount;
            $payload['transactions'][0]['vat'] = (1000 - $discount) * 0.1;
            $payload['transactions'][0]['total'] = (1000 - $discount) * 1.1;
            $this->put(route('invoices.update', $invoice), $payload)->assertSessionHasNoErrors();
            $this->assertEquals($discount, $invoice->items()->sole()->discount);
            $this->assertEqualsWithDelta((1000 - $discount) * 1.1, $invoice->fresh()->amount, 0.001);
        }
        foreach ([-1, 1001] as $discount) {
            $payload['transactions'][0]['discount'] = $discount;
            $this->put(route('invoices.update', $invoice), $payload)->assertSessionHasErrors('transactions.0.discount');
            $this->assertEquals(100, $invoice->items()->sole()->discount);
        }
    }

    public function test_print_templates_and_tax_export_keep_line_discount_amounts(): void
    {
        $product = $this->createProduct(['quantity' => 10, 'average_cost' => 100, 'sstid' => '1234567890123']);
        $invoice = $this->sell([[...$this->productItem($product, 2, 500), 'discount' => 50, 'vat' => 10]])['invoice'];
        $invoice->update(['description' => 'Discount test']);
        foreach (['invoices.print', 'invoices.pdf-print', 'invoices.draft', 'invoices.pdf-draft'] as $template) {
            $html = view($template, ['invoice' => $invoice, 'autoPrint' => false])->render();
            $this->assertMatchesRegularExpression('/<td[^>]*>\\s*'.preg_quote(formatNumber(50), '/').'\\s*<\\/td>/', $html);
            $this->assertStringContainsString(formatNumber(str_ends_with($template, 'draft') ? 950 : 1045), $html);
        }
        $this->get(route('invoices.print', $invoice))->assertOk()->assertSee(formatNumber(50));
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee(formatNumber(50));

        $service = new MoadianService;
        (new ReflectionProperty($service, 'moadian_username'))->setValue($service, 'ABC123');
        (new ReflectionProperty($service, 'taxID'))->setValue($service, '1234567890');
        (new ReflectionMethod($service, 'moadianData'))->invoke($service, $invoice);
        $export = $service->moadianInvoice->toArray();
        $this->assertEquals(50, $export['header']['tdis']);
        $this->assertEquals(1000, $export['header']['tprdis']);
        $this->assertEquals(950, $export['header']['tadis']);
        $this->assertEquals(1045, $export['header']['tbill']);
        $this->assertEquals(50, $export['body'][0]['dis']);
        $this->assertEquals(950, $export['body'][0]['adis']);
        $this->assertEquals(95, $export['body'][0]['vam']);
    }

    public function test_migration_preserves_existing_values_in_both_directions(): void
    {
        $item = InvoiceItem::factory()->create(['discount' => 1234.56]);
        $migration = require database_path('migrations/2026_10_28_000001_rename_invoice_item_discount.php');
        $migration->down();
        try {
            $this->assertTrue(Schema::hasColumn('invoice_items', 'unit_discount'));
            $this->assertEquals(1234.56, DB::table('invoice_items')->where('id', $item->id)->value('unit_discount'));
        } finally {
            $migration->up();
        }
        $this->assertFalse(Schema::hasColumn('invoice_items', 'unit_discount'));
        $this->assertEquals(1234.56, $item->fresh()->discount);
    }
}
