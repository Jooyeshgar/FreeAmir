<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FiscalYearInvoiceItemImportTest extends TestCase
{
    use RefreshDatabase;

    public static function discountPayloads(): array
    {
        return [
            'legacy zero' => [['unit_discount' => 0], 0],
            'legacy fractional' => [['unit_discount' => 50.50], 50.50],
            'current zero' => [['discount' => 0], 0],
            'current fractional' => [['discount' => 50.50], 50.50],
            'both keys' => [['unit_discount' => 90, 'discount' => 50.50], 50.50],
            'explicit zero wins' => [['unit_discount' => 90, 'discount' => 0], 0],
        ];
    }

    #[DataProvider('discountPayloads')]
    public function test_backup_import_preserves_invoice_item_discount_vat_and_amount(array $discountAttributes, float $discount): void
    {
        $source = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $source->id]);
        $user = User::factory()->create();
        $source->users()->attach($user);
        $this->actingAs($user);

        $customer = Customer::factory()->withGroup()->withSubject()->create(['type' => CustomerType::INDIVIDUAL]);
        $product = Product::factory()->withGroup()->withSubjects()->create();
        $service = Service::factory()->withGroup()->withSubject()->create();
        $vat = (1000 - $discount) * 0.1;
        $amount = 1000 - $discount + $vat;
        $invoice = Invoice::create([
            'fiscal_year_id' => $source->id,
            'creator_id' => $user->id,
            'customer_id' => $customer->id,
            'number' => 1,
            'date' => '2026-06-01',
            'invoice_type' => InvoiceType::BUY,
            'status' => InvoiceStatus::UNAPPROVED,
            'subtraction' => 0,
            'vat' => 2 * $vat,
            'amount' => 2 * $amount,
        ]);
        foreach ([$product, $service] as $itemable) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'itemable_type' => $itemable::class,
                'itemable_id' => $itemable->id,
                'quantity' => 2,
                'unit_price' => 500,
                'discount' => $discount,
                'vat' => $vat,
                'amount' => $amount,
            ]);
        }

        $payload = FiscalYearService::exportData($source->id, ['subjects', 'customers', 'products', 'services', 'invoices']);
        $this->assertCount(2, $payload['invoice_items']);
        foreach ($payload['invoice_items'] as &$item) {
            unset($item['discount']);
            $item = array_merge($item, $discountAttributes);
        }
        unset($item);

        // Real backups pass through JSON before import.
        $payload = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $target = FiscalYearService::importData($payload, ['name' => 'Restored discount backup', 'fiscal_year' => 1406]);
        $restored = Invoice::withoutGlobalScopes()->where('fiscal_year_id', $target->id)->sole();
        $this->assertNotSame($invoice->id, $restored->id);
        $this->assertEqualsWithDelta(2 * $vat, $restored->vat, 0.001);
        $this->assertEqualsWithDelta(2 * $amount, $restored->amount, 0.001);
        $this->assertCount(2, $restored->items);
        foreach ($restored->items as $item) {
            $this->assertEquals($discount, $item->discount);
            $this->assertEquals($vat, $item->vat);
            $this->assertEquals($amount, $item->amount);
            $this->assertArrayNotHasKey('unit_discount', $item->getAttributes());
            $itemable = $item->itemable()->withoutGlobalScopes()->firstOrFail();
            $this->assertSame($target->id, $itemable->fiscal_year_id);
        }
    }
}
