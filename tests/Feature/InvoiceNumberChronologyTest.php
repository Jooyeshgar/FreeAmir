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

class InvoiceNumberChronologyTest extends TestCase
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

    public function test_earlier_date_with_higher_number_is_rejected_across_types_and_names_the_conflict(): void
    {
        $this->invoice(InvoiceType::SELL, 1381, '2026-11-24');

        $message = $this->numberError(InvoiceType::BUY, 1382, '2026-11-23');

        $this->assertNotNull($message);
        $this->assertStringContainsString(formatDocumentNumber(1381), $message);
        $this->assertStringContainsString(formatDate('2026-11-24'), $message);
        $this->assertNull($this->numberError(InvoiceType::BUY, 1382, '2026-11-24'));
    }

    public function test_unused_number_between_neighbours_and_gaps_are_accepted(): void
    {
        $this->invoice(InvoiceType::SELL, 1380, '2026-11-23');
        $this->invoice(InvoiceType::BUY, 1385, '2026-11-25');

        foreach ([1381, 1382, 1383, 1384] as $number) {
            $this->assertNull($this->numberError(InvoiceType::RETURN_SELL, $number, '2026-11-24'));
        }
        $this->assertNotNull($this->numberError(InvoiceType::SELL, 1386, '2026-11-24'));
    }

    public function test_duplicate_numbers_across_types_are_rejected_but_own_invoice_is_ignored(): void
    {
        $sale = $this->invoice(InvoiceType::SELL, 10, '2026-11-24');

        $this->assertNotNull($this->numberError(InvoiceType::BUY, 10, '2026-11-24'));
        $this->assertNull($this->numberError(InvoiceType::SELL, 10, '2026-11-24', $sale));
        // Editing the date of an invoice only conflicts with the other invoices.
        $this->invoice(InvoiceType::BUY, 11, '2026-11-26');
        $this->assertNotNull($this->numberError(InvoiceType::SELL, 10, '2026-11-27', $sale));
    }

    public function test_other_fiscal_years_are_ignored(): void
    {
        $other = FiscalYear::factory()->create(['year' => 1404, 'company_id' => $this->year->company_id]);
        config(['active-fiscal-year-id' => $other->id]);
        $this->invoice(InvoiceType::SELL, 50, '2030-01-01');
        config(['active-fiscal-year-id' => $this->year->id]);

        $this->assertNull($this->numberError(InvoiceType::SELL, 50, '2026-01-01'));
        $this->assertCount(0, app(InvoiceNumberService::class)->existingConflicts());
    }

    public function test_existing_conflicts_flag_both_sides_and_count_each_invoice_once(): void
    {
        $a = $this->invoice(InvoiceType::SELL, 1381, '2026-11-24');
        $b = $this->invoice(InvoiceType::BUY, 1382, '2026-11-23');
        $this->invoice(InvoiceType::SELL, 1390, '2026-11-30');

        $conflicts = app(InvoiceNumberService::class)->existingConflicts();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $conflicts->pluck('invoice.id')->all());

        // A third invoice conflicting with both is counted once per invoice.
        $c = $this->invoice(InvoiceType::BUY, 1383, '2026-11-22');
        $conflicts = app(InvoiceNumberService::class)->existingConflicts();
        $this->assertCount(3, $conflicts);
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], $conflicts->pluck('invoice.id')->all());
    }

    public function test_existing_duplicates_are_flagged_as_duplicates(): void
    {
        $sale = $this->invoice(InvoiceType::SELL, 5, '2026-11-24');
        $purchase = $this->invoice(InvoiceType::BUY, 5, '2026-11-24');

        $conflicts = app(InvoiceNumberService::class)->existingConflicts()->keyBy('invoice.id');
        $this->assertCount(2, $conflicts);
        $this->assertSame(InvoiceNumberService::REASON_DUPLICATE, $conflicts[$sale->id]['reason']);
        $this->assertSame(InvoiceNumberService::REASON_DUPLICATE, $conflicts[$purchase->id]['reason']);
    }

    public function test_same_date_with_distinct_numbers_is_not_a_conflict(): void
    {
        $this->invoice(InvoiceType::SELL, 1, '2026-11-24');
        $this->invoice(InvoiceType::BUY, 2, '2026-11-24');

        $this->assertCount(0, app(InvoiceNumberService::class)->existingConflicts());
    }

    public function test_sort_route_renumbers_one_chronological_sequence_across_types(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('invoices.sort-numbers'));
        $late = $this->invoice(InvoiceType::SELL, 1, '2026-11-30');
        $early = $this->invoice(InvoiceType::BUY, 1, '2026-11-20');
        $middle = $this->invoice(InvoiceType::RETURN_SELL, 7, '2026-11-25');

        $this->actingAs($this->user)->post(route('invoices.sort-numbers'))->assertRedirect();

        $this->assertEquals([1, 2, 3], [$early->refresh()->number, $middle->refresh()->number, $late->refresh()->number]);
        $this->assertCount(0, app(InvoiceNumberService::class)->existingConflicts());
    }

    public function test_sort_route_requires_permission_and_rejects_closed_year(): void
    {
        Permission::findOrCreate('invoices.sort-numbers');
        Permission::findOrCreate('invoices.*');
        $invoice = $this->invoice(InvoiceType::SELL, 25, '2026-11-24');
        $this->actingAs($this->user)->post(route('invoices.sort-numbers'))->assertForbidden();

        $this->year->update(['closed_at' => now()]);
        $this->expectException(ValidationException::class);
        app(InvoiceNumberService::class)->sort();
        $this->assertEquals(25, $invoice->refresh()->number);
    }

    public function test_all_invoices_list_shows_every_type_and_status_with_conflict_count(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('invoices.index'));
        $this->invoice(InvoiceType::SELL, 1381, '2026-11-24');
        $this->invoice(InvoiceType::BUY, 1382, '2026-11-23', InvoiceStatus::APPROVED_INACTIVE);
        $this->invoice(InvoiceType::SELL, 1390, '2026-11-30', InvoiceStatus::PRE_INVOICE);

        $response = $this->actingAs($this->user)->get(route('invoices.index'))->assertOk();
        $response->assertSeeText(__('Numbering conflicts').': '.localizeNumber(2));
        $this->assertCount(3, $response->viewData('invoices'));

        $filtered = $this->actingAs($this->user)->get(route('invoices.index', ['status' => 'pre_invoice']))->assertOk();
        $this->assertCount(1, $filtered->viewData('invoices'));
        $filtered = $this->actingAs($this->user)->get(route('invoices.index', ['invoice_type' => 'sell']))->assertOk();
        $this->assertCount(2, $filtered->viewData('invoices'));
    }

    public function test_all_invoices_list_can_be_sorted_by_number(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('invoices.index'));
        $this->invoice(InvoiceType::SELL, 2, '2026-11-24');
        $this->invoice(InvoiceType::BUY, 3, '2026-11-25');
        $this->invoice(InvoiceType::SELL, 1, '2026-11-23');

        $asc = $this->actingAs($this->user)->get(route('invoices.index', ['sort' => 'number', 'direction' => 'asc']))->assertOk();
        $this->assertSame([1, 2, 3], $asc->viewData('invoices')->pluck('number')->map(fn ($n) => (int) $n)->all());
        $desc = $this->actingAs($this->user)->get(route('invoices.index', ['sort' => 'number', 'direction' => 'desc']))->assertOk();
        $this->assertSame([3, 2, 1], $desc->viewData('invoices')->pluck('number')->map(fn ($n) => (int) $n)->all());
    }

    private function numberError(InvoiceType $type, int $number, string $date, ?Invoice $editing = null): ?string
    {
        $request = StoreInvoiceRequest::create('/invoices', 'POST', ['invoice_type' => $type->valueName(), 'date' => $date]);
        if ($editing) {
            $request->setRouteResolver(fn () => new class($editing)
            {
                public function __construct(private Invoice $invoice) {}

                public function parameter($name, $default = null)
                {
                    return $name === 'invoice' ? $this->invoice : $default;
                }
            });
        }
        $validator = Validator::make(['invoice_number' => $number, 'date' => $date], ['invoice_number' => $request->rules()['invoice_number']]);

        return $validator->fails() ? $validator->errors()->first('invoice_number') : null;
    }

    private function invoice(InvoiceType $type, int $number, string $date, InvoiceStatus $status = InvoiceStatus::PENDING): Invoice
    {
        return Invoice::create([
            'invoice_type' => $type,
            'number' => $number,
            'date' => $date,
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'status' => $status,
            'subtraction' => 0,
            'vat' => 0,
        ]);
    }
}
