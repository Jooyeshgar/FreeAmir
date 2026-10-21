<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoiceBookmarkTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $fiscalYear;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->fiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        $this->user = $this->viewer('invoices.index', 'invoices.show', 'invoices.export');
        $this->withCookies(['active-fiscal-year-id' => (string) $this->fiscalYear->id]);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);
        $this->app->setLocale('en');
        $this->customer = Customer::create(['name' => 'Bookmark customer', 'fiscal_year_id' => $this->fiscalYear->id]);
    }

    public static function invoiceTypes(): array
    {
        $types = [];
        foreach (InvoiceType::cases() as $type) {
            $types[$type->valueName()] = [$type, false];
        }
        $types['service_buy'] = [InvoiceType::BUY, true];
        $types['return_service_buy'] = [InvoiceType::RETURN_BUY, true];

        return $types;
    }

    #[DataProvider('invoiceTypes')]
    public function test_viewers_share_bookmarks_from_index_and_detail_for_every_invoice_type(InvoiceType $type, bool $serviceBuy): void
    {
        $invoice = $this->invoice($type, $serviceBuy);
        $original = $invoice->getAttributes();
        $documents = Document::count();
        $transactions = Transaction::count();
        $indexUrl = route('invoices.index', ['invoice_type' => $type->valueName(), 'service_buy' => $serviceBuy ? 1 : null]);
        $detailUrl = route('invoices.show', $invoice);

        $this->actingAs($this->user)->get($indexUrl)->assertOk()
            ->assertSee(route('invoices.bookmark', $invoice), false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('name="bookmarked"', false);

        $this->from($indexUrl)->patch(route('invoices.bookmark', $invoice), ['is_bookmarked' => 1])
            ->assertRedirect($indexUrl);
        $this->assertTrue($invoice->fresh()->is_bookmarked);

        $otherViewer = $this->viewer('invoices.show');
        $this->actingAs($otherViewer)->get($detailUrl)->assertOk()
            ->assertSee(route('invoices.bookmark', $invoice), false)
            ->assertSee('aria-pressed="true"', false);
        $this->from($detailUrl)->patch(route('invoices.bookmark', $invoice), ['is_bookmarked' => 0])
            ->assertRedirect($detailUrl);

        $this->actingAs($this->user)->get($indexUrl)->assertOk()
            ->assertSee('aria-pressed="false"', false);
        $this->assertFalse($invoice->fresh()->is_bookmarked);
        $this->assertSame(Arr::except($original, ['is_bookmarked', 'updated_at']),
            Arr::except($invoice->fresh()->getAttributes(), ['is_bookmarked', 'updated_at']));
        $this->assertSame($documents, Document::count());
        $this->assertSame($transactions, Transaction::count());
    }

    #[DataProvider('invoiceTypes')]
    public function test_filter_returns_only_bookmarked_invoices_and_filters_totals(InvoiceType $type, bool $serviceBuy): void
    {
        $included = $this->invoice($type, $serviceBuy);
        $included->is_bookmarked = true;
        $included->save();
        $this->invoice($type, $serviceBuy);

        $response = $this->actingAs($this->user)->get(route('invoices.index', [
            'invoice_type' => $type->valueName(), 'bookmarked' => 1, 'service_buy' => $serviceBuy ? 1 : null,
        ]));

        $response->assertOk()->assertViewHas('invoices', fn ($invoices) => $invoices->pluck('id')->all() === [$included->id]
            && (float) $invoices->totalAmount === 100.0);
        $response->assertViewHas('statusCounts', fn ($counts) => $counts->sum() === 1);
    }

    public function test_index_only_viewer_can_set_a_bookmark_idempotently(): void
    {
        $invoice = $this->invoice();
        $viewer = $this->viewer('invoices.index');

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($viewer)->patch(route('invoices.bookmark', $invoice), ['is_bookmarked' => 1])->assertRedirect();
        }

        $this->assertTrue($invoice->fresh()->is_bookmarked);
    }

    public function test_disabling_the_filter_shows_both_states_and_status_navigation_preserves_it(): void
    {
        $bookmarked = $this->invoice();
        $bookmarked->is_bookmarked = true;
        $bookmarked->save();
        $this->invoice();

        $this->actingAs($this->user)->get(route('invoices.index', ['invoice_type' => 'sell', 'bookmarked' => 0]))
            ->assertOk()->assertViewHas('invoices', fn ($invoices) => $invoices->total() === 2);

        $this->get(route('invoices.index', ['invoice_type' => 'sell', 'bookmarked' => 1, 'status' => 'unapproved']))
            ->assertOk()->assertSee(route('invoices.index', ['invoice_type' => 'sell', 'bookmarked' => 1]))
            ->assertViewHas('invoices', fn ($invoices) => $invoices->pluck('id')->all() === [$bookmarked->id]);
    }

    public function test_guest_and_user_without_viewing_access_cannot_bookmark(): void
    {
        $invoice = $this->invoice();
        $this->patch(route('invoices.bookmark', $invoice), ['is_bookmarked' => 1])->assertRedirect(route('login'));
        $nonViewer = $this->viewer('invoices.edit');
        $this->actingAs($nonViewer)->patch(route('invoices.bookmark', $invoice), ['is_bookmarked' => 1])->assertForbidden();
        $this->assertFalse($invoice->fresh()->is_bookmarked);
    }

    public function test_bookmark_requires_a_valid_explicit_state(): void
    {
        $invoice = $this->invoice();
        foreach ([[], ['is_bookmarked' => 'invalid']] as $data) {
            $this->actingAs($this->user)->patch(route('invoices.bookmark', $invoice), $data)
                ->assertSessionHasErrors('is_bookmarked');
        }
        $this->assertFalse($invoice->fresh()->is_bookmarked);
    }

    public function test_invoice_in_another_fiscal_year_cannot_be_bookmarked_or_listed(): void
    {
        $otherYear = FiscalYear::factory()->create(['company_id' => $this->fiscalYear->company_id, 'year' => 1404]);
        config(['active-fiscal-year-id' => $otherYear->id]);
        $otherInvoice = $this->invoice();
        $otherInvoice->is_bookmarked = true;
        $otherInvoice->save();
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);

        $this->actingAs($this->user)->patch(route('invoices.bookmark', $otherInvoice), ['is_bookmarked' => 0])->assertNotFound();
        $this->get(route('invoices.index', ['invoice_type' => 'sell', 'bookmarked' => 1]))
            ->assertOk()->assertViewHas('invoices', fn ($invoices) => $invoices->isEmpty());
        $this->assertTrue($otherInvoice->fresh()->is_bookmarked);
    }

    public function test_user_outside_the_fiscal_year_cannot_bookmark(): void
    {
        $invoice = $this->invoice();
        $outsider = $this->viewer('invoices.show');
        $outsider->fiscalYears()->detach();

        $this->actingAs($outsider)->patch(route('invoices.bookmark', $invoice), ['is_bookmarked' => 1])->assertForbidden();
        $this->assertFalse($invoice->fresh()->is_bookmarked);
    }

    public function test_bookmark_filter_is_preserved_in_pagination_and_export(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $invoice = $this->invoice();
            $invoice->is_bookmarked = true;
            $invoice->save();
        }
        $unbookmarked = $this->invoice();
        $this->actingAs($this->user)->get(route('invoices.index', ['invoice_type' => 'sell', 'bookmarked' => 1]))
            ->assertOk()->assertSee('bookmarked=1&amp;page=2', false)
            ->assertViewHas('invoices', fn ($invoices) => $invoices->total() === 26);

        $response = $this->get(route('invoices.export', ['invoice_type' => 'sell', 'bookmarked' => 1]));
        $response->assertOk();
        $lines = preg_split('/\r\n|\n|\r/', trim($response->streamedContent()));
        $this->assertCount(27, $lines);
        $numbers = array_map(fn ($line) => str_getcsv($line)[0], array_slice($lines, 1));
        $this->assertNotContains((string) $unbookmarked->number, $numbers);
    }

    public function test_closed_fiscal_year_keeps_its_mutation_protection(): void
    {
        $invoice = $this->invoice();
        $this->fiscalYear->update(['closed_at' => now()]);
        $this->actingAs($this->user)->patch(route('invoices.bookmark', $invoice), ['is_bookmarked' => 1])
            ->assertSessionHasErrors('fiscal_year');
        $this->assertFalse($invoice->fresh()->is_bookmarked);
    }

    private function viewer(string ...$permissions): User
    {
        $user = User::factory()->create();
        $this->fiscalYear->users()->attach($user);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $user;
    }

    private function invoice(InvoiceType $type = InvoiceType::SELL, bool $serviceBuy = false): Invoice
    {
        $invoice = Invoice::create([
            'number' => Invoice::withoutGlobalScopes()->count() + 1000,
            'date' => '2026-10-21',
            'invoice_type' => $type,
            'status' => InvoiceStatus::UNAPPROVED,
            'customer_id' => $type->isBeginningInventory() ? null : $this->customer->id,
            'creator_id' => $this->user->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 100,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => $serviceBuy ? Service::class : Product::class,
            'quantity' => 1,
            'unit_price' => 100,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => 100,
        ]);

        return $invoice->refresh();
    }
}
