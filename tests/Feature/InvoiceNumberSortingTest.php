<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoiceNumberSortingTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $year;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->year = FiscalYear::factory()->create(['year' => 1405]);
        $this->user = User::factory()->create();
        $this->year->users()->attach($this->user);
        config(['active-fiscal-year-id' => $this->year->id]);
        $this->withCookies(['active-fiscal-year-id' => $this->year->id]);
        $this->customer = Customer::create(['name' => 'Customer', 'fiscal_year_id' => $this->year->id]);
    }

    public function test_explicit_sort_reorders_a_gapless_sequence_and_is_idempotent(): void
    {
        $late = $this->invoice(InvoiceType::SELL, 1, '2026-06-20');
        $early = $this->invoice(InvoiceType::SELL, 2, '2026-06-01');
        $middle = $this->invoice(InvoiceType::SELL, 3, '2026-06-10');
        $this->user->givePermissionTo(Permission::findOrCreate('invoices.sort-numbers'));

        $this->actingAs($this->user)->from('/invoices?invoice_type=sell')
            ->post(route('invoices.sort-numbers'))->assertRedirect('/invoices?invoice_type=sell');

        $this->assertNumbers([$early, $middle, $late], [1, 2, 3]);
        app(InvoiceNumberService::class)->sort();
        $this->assertNumbers([$early, $middle, $late], [1, 2, 3]);
    }

    public function test_returns_and_voids_follow_their_reference_in_separate_sequences(): void
    {
        $sale = $this->invoice(InvoiceType::SELL, 70, '2026-06-01');
        $otherSale = $this->invoice(InvoiceType::SELL, 80, '2026-06-03');
        $lateReturn = $this->invoice(InvoiceType::RETURN_SELL, 70, '2026-06-20', $sale);
        $earlyReturn = $this->invoice(InvoiceType::RETURN_SELL, 80, '2026-06-10', $sale);
        $void = $this->invoice(InvoiceType::VOID, 70, '2026-06-30', $otherSale);
        $purchase = $this->invoice(InvoiceType::BUY, 70, '2026-06-01');
        $otherPurchase = $this->invoice(InvoiceType::BUY, 80, '2026-06-02');
        $purchaseReturn = $this->invoice(InvoiceType::RETURN_BUY, 70, '2026-06-20', $purchase);
        $inventory = $this->invoice(InvoiceType::BEGINNING_INVENTORY, 70, '2026-05-01');
        $unlinked = $this->invoice(InvoiceType::RETURN_BUY, 80, '2026-06-25');

        app(InvoiceNumberService::class)->sort();

        $this->assertNumbers([$sale, $earlyReturn, $lateReturn, $otherSale, $void], [1, 2, 3, 4, 5]);
        $this->assertNumbers([$purchase, $purchaseReturn, $otherPurchase, $unlinked], [1, 2, 3, 4]);
        $this->assertNumbers([$inventory], [1]);
        $this->assertSame($sale->id, $earlyReturn->refresh()->returned_invoice_id);
        $this->assertSame('2026-06-10', $earlyReturn->date->toDateString());
        $this->assertSame(InvoiceStatus::PENDING, $earlyReturn->status);
    }

    public function test_sort_does_not_change_another_year_or_company(): void
    {
        $activeYear = $this->year->id;
        $otherYear = FiscalYear::factory()->create(['year' => 1404, 'company_id' => $this->year->company_id]);
        config(['active-fiscal-year-id' => $otherYear->id]);
        $previous = $this->invoice(InvoiceType::SELL, 10, '2025-06-01');
        $otherCompany = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $otherCompany->id]);
        $foreign = $this->invoice(InvoiceType::SELL, 20, '2026-06-01');
        config(['active-fiscal-year-id' => $activeYear]);
        $active = $this->invoice(InvoiceType::SELL, 30, '2026-06-01');

        app(InvoiceNumberService::class)->sort();

        $this->assertNumbers([$active, $previous, $foreign], [1, 10, 20]);
    }

    public function test_date_edits_and_earlier_creation_preserve_existing_numbers(): void
    {
        $original = $this->invoice(InvoiceType::SELL, 25, '2026-06-20');
        $earlier = $this->invoice(InvoiceType::SELL, 40, '2026-06-01');
        $original->update(['date' => '2026-05-01']);
        $this->assertNumbers([$original, $earlier], [25, 40]);
    }

    public function test_sort_requires_permission(): void
    {
        Permission::findOrCreate('invoices.sort-numbers');
        Permission::findOrCreate('invoices.*');
        $invoice = $this->invoice(InvoiceType::SELL, 25, '2026-06-01');
        $this->actingAs($this->user)->post(route('invoices.sort-numbers'))->assertForbidden();
        $this->assertNumbers([$invoice], [25]);
    }

    public function test_invoice_list_displays_the_sort_action_for_authorized_users(): void
    {
        $this->user->givePermissionTo([
            Permission::findOrCreate('invoices.index'),
            Permission::findOrCreate('invoices.sort-numbers'),
        ]);

        $this->actingAs($this->user)->get(route('invoices.index', ['invoice_type' => 'sell']))
            ->assertOk()
            ->assertSee(route('invoices.sort-numbers'), false)
            ->assertSeeText(__('Sort invoice numbers'));
    }

    public function test_closed_year_cannot_be_sorted(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, 25, '2026-06-01');
        $this->year->update(['closed_at' => now()]);
        try {
            app(InvoiceNumberService::class)->sort();
            $this->fail('A closed fiscal year must reject renumbering.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fiscal_year', $exception->errors());
        }
        $this->assertNumbers([$invoice], [25]);
    }

    public function test_sorting_an_empty_year_is_allowed(): void
    {
        $this->assertSame(['sales' => 0, 'purchases' => 0, 'beginning_inventory' => 0], app(InvoiceNumberService::class)->sort());
    }

    public function test_manual_numbers_keep_existing_type_specific_validation_after_sorting(): void
    {
        $this->invoice(InvoiceType::SELL, 25, '2026-06-01');
        app(InvoiceNumberService::class)->sort();

        $request = StoreInvoiceRequest::create('/invoices', 'POST', ['invoice_type' => 'sell']);
        $rules = ['invoice_number' => $request->rules()['invoice_number']];
        $this->assertTrue(Validator::make(['invoice_number' => 77], $rules)->passes());
        $this->assertFalse(Validator::make(['invoice_number' => 1], $rules)->passes());
        $this->assertFalse(Validator::make(['invoice_number' => 1.5], $rules)->passes());
        $this->assertFalse(Validator::make([], $rules)->passes());

        $purchaseRequest = StoreInvoiceRequest::create('/invoices', 'POST', ['invoice_type' => 'buy']);
        $this->assertTrue(Validator::make(['invoice_number' => 1], [
            'invoice_number' => $purchaseRequest->rules()['invoice_number'],
        ])->passes());
    }

    public function test_failed_sort_rolls_back_all_numbers(): void
    {
        $first = $this->invoice(InvoiceType::SELL, 20, '2026-06-01');
        $second = $this->invoice(InvoiceType::SELL, 30, '2026-06-02');
        Invoice::updating(function (Invoice $invoice) use ($second): void {
            if ($invoice->id === $second->id && (int) $invoice->number === 2) {
                throw new \RuntimeException('Simulated write failure');
            }
        });

        try {
            app(InvoiceNumberService::class)->sort();
            $this->fail('The simulated write failure should propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated write failure', $exception->getMessage());
        }

        $this->assertNumbers([$first, $second], [20, 30]);
    }

    private function invoice(InvoiceType $type, int $number, string $date, ?Invoice $reference = null): Invoice
    {
        return Invoice::create([
            'invoice_type' => $type,
            'number' => $number,
            'date' => $date,
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'status' => InvoiceStatus::PENDING,
            'subtraction' => 0,
            'vat' => 0,
            'returned_invoice_id' => $reference?->id,
        ]);
    }

    private function assertNumbers(array $invoices, array $numbers): void
    {
        foreach ($invoices as $index => $invoice) {
            $this->assertEquals($numbers[$index], $invoice->refresh()->number);
        }
    }
}
