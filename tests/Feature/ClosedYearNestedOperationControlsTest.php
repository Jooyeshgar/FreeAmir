<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Middleware\CheckPermission;
use App\Models\AncillaryCost;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\MoadianHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClosedYearNestedOperationControlsTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $year;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->year = FiscalYear::factory()->create();
        Cache::forever('active_fiscal_year_id', $this->year->id);
        config(['active-fiscal-year-id' => $this->year->id]);
        $user = User::factory()->create();
        $user->fiscalYears()->attach($this->year);
        foreach (['ancillary-costs.show', 'ancillary-costs.delete', 'ancillary-costs.edit', 'ancillary-costs.approve', 'invoices.moadian-check-status', 'invoices.send-moadian'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }
        $this->actingAs($user)->withoutMiddleware(CheckPermission::class);
        $this->invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'fiscal_year_id' => $this->year->id,
            'invoice_type' => InvoiceType::BUY,
            'status' => InvoiceStatus::APPROVED,
            'number' => 1,
            'date' => now()->toDateString(),
            'amount' => 100,
            'vat' => 0,
        ]));
    }

    public function test_closed_ancillary_pages_disable_edit_delete_and_status_controls(): void
    {
        $product = Product::factory()->create();
        $this->invoice->items()->create([
            'itemable_type' => Product::class, 'itemable_id' => $product->id,
            'quantity' => 1, 'unit_price' => 100, 'unit_discount' => 0, 'amount' => 100, 'vat' => 0,
        ]);
        $cost = AncillaryCost::create([
            'fiscal_year_id' => $this->year->id, 'invoice_id' => $this->invoice->id,
            'customer_id' => Customer::factory()->create()->id,
            'number' => 1, 'type' => 1, 'status' => InvoiceStatus::UNAPPROVED,
            'date' => now()->toDateString(), 'amount' => 10, 'vat' => 0,
        ]);
        // Editing is available before closure.
        $this->get(route('ancillary-costs.index'))->assertOk()
            ->assertSee(route('invoices.ancillary-costs.edit', [$this->invoice, $cost]));
        $this->year->update(['closed_at' => now()]);

        foreach ([InvoiceStatus::UNAPPROVED, InvoiceStatus::APPROVED] as $status) {
            $cost->update(['status' => $status]);
            $action = $status->isApproved() ? __('Unapprove') : __('Approve');
            $this->assertControls(route('ancillary-costs.index'), [__('Edit'), __('Delete'), $action], true);
            $this->assertControls(route('invoices.ancillary-costs.show', [$this->invoice, $cost]), [__('Edit'), $action], true);
            $this->assertControls(route('invoices.show', $this->invoice), [__('Edit'), __('Delete'), $action], true,
                route('invoices.ancillary-costs.show', [$this->invoice, $cost]));
        }
    }

    public function test_moadian_history_pages_disable_writes_in_closed_year_and_preserve_open_year_actions(): void
    {
        MoadianHistory::create([
            'invoice_id' => $this->invoice->id,
            'data' => ['status' => 'FAILED', 'referenceNumber' => 'test-reference', 'uid' => 'test-uid'],
        ]);
        foreach ([false, true] as $closed) {
            $this->year->update(['closed_at' => $closed ? now() : null]);
            foreach ([route('invoices.moadian-histories.index'), route('invoices.moadian-histories.show', $this->invoice)] as $url) {
                $this->assertControls($url, [__('Check Status'), __('Send Again')], $closed);
                $this->get($url)->assertSee('test-reference')->assertSee('test-uid');
            }
        }
    }

    public function test_moadian_history_index_renders_when_invoice_is_outside_active_fiscal_year(): void
    {
        MoadianHistory::create([
            'invoice_id' => $this->invoice->id,
            'data' => ['status' => 'SUCCESS', 'uid' => 'scoped-invoice-history'],
        ]);
        $otherYear = FiscalYear::factory()->create();
        $this->invoice->update(['fiscal_year_id' => $otherYear->id]);

        $this->assertNull(MoadianHistory::first()->invoice);

        $this->get(route('invoices.moadian-histories.index'))
            ->assertOk()
            ->assertSee('scoped-invoice-history');
    }

    public function test_bulk_and_conflict_pages_disable_closed_year_writes_and_preserve_open_year_actions(): void
    {
        $this->invoice->update(['status' => InvoiceStatus::APPROVED_INACTIVE]);
        $pages = [
            [route('invoices.inactive'), __('Approve All')],
            [route('invoices.conflicts', $this->invoice), __('Delete All Documents')],
        ];

        foreach ([false, true] as $closed) {
            $this->year->update(['closed_at' => $closed ? now() : null]);
            foreach ($pages as [$url, $label]) {
                $this->assertControls($url, [$label], $closed);
                $response = $this->get($url)->assertOk();
                if ($closed) {
                    $response->assertSeeText(__('This fiscal year is already closed.'));
                } else {
                    $response->assertDontSeeText(__('This fiscal year is already closed.'));
                }
                $response->assertSee(formatDocumentNumber($this->invoice->number));
            }
        }

        foreach ([route('invoices.inactive.approve'), route('invoices.group-action', $this->invoice)] as $url) {
            $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
            $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $this->invoice->fresh()->status);
        }
    }

    private function assertControls(string $url, array $labels, bool $disabled, ?string $rowLink = null): void
    {
        $response = $this->get($url)->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $scope = $rowLink ? '//tr[.//a[@href="'.$rowLink.'"]]' : '';
        foreach ($labels as $label) {
            $buttons = $xpath->query($scope.'//button[normalize-space(.)="'.$label.'"]');
            $this->assertSame(1, $buttons->length, $url.' '.$label);
            $button = $buttons->item(0);
            $this->assertSame($disabled, $button->hasAttribute('disabled'), $url.' '.$label);
            $this->assertSame($disabled, in_array('btn-disabled', explode(' ', $button->getAttribute('class')), true), $url.' '.$label);
            if ($disabled) {
                $this->assertSame(__('This fiscal year is already closed.'), $button->getAttribute('title'));
            }
        }
        if ($disabled) {
            $this->assertSame(0, $xpath->query($scope.'//a[normalize-space(.)="'.__('Edit').'"]')->length);
        }
    }
}
