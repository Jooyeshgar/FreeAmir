<?php

namespace Tests\Feature;

use App\Enums\AncillaryCostType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAncillaryCostDateSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_invoice_date_updates_all_of_its_ancillary_cost_dates(): void
    {
        $fiscalYear = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $fiscalYear->id]);

        $user = User::factory()->create();
        $customerGroup = CustomerGroup::factory()->withSubject()->create([
            'fiscal_year_id' => $fiscalYear->id,
        ]);
        $customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create([
            'name' => 'Test Customer',
            'fiscal_year_id' => $fiscalYear->id,
        ]);
        $invoice = Invoice::query()->create([
            'number' => 1001,
            'date' => '2026-02-04',
            'creator_id' => $user->id,
            'customer_id' => $customer->id,
            'invoice_type' => InvoiceType::BUY,
            'status' => InvoiceStatus::UNAPPROVED,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 0,
        ]);

        $ancillaryCosts = collect(['2026-02-05', '2026-02-06'])->map(
            fn (string $date, int $index) => AncillaryCost::query()->create([
                'number' => $index + 1,
                'type' => AncillaryCostType::Shipping,
                'amount' => 100,
                'vat' => 0,
                'date' => $date,
                'invoice_id' => $invoice->id,
                'status' => InvoiceStatus::UNAPPROVED,
                'fiscal_year_id' => $fiscalYear->id,
                'customer_id' => $customer->id,
            ]),
        );

        InvoiceService::updateInvoice($invoice->id, [
            'title' => 'Updated invoice',
            'date' => '2026-02-10',
            'invoice_type' => InvoiceType::BUY,
            'number' => $invoice->number,
            'customer_id' => $customer->id,
            'warehouse_id' => null,
            'subtraction' => 0,
        ]);

        $ancillaryCosts->each(function (AncillaryCost $ancillaryCost) {
            $this->assertDatabaseHas('ancillary_costs', [
                'id' => $ancillaryCost->id,
                'date' => '2026-02-10',
            ]);
        });
    }
}
