<?php

namespace Tests\Feature\Extensions;

use App\Extensions\ViewSlotRegistry;
use App\Enums\InvoiceType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\FiscalYear;
use App\Models\User;
use App\View\Components\ExtensionSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class InvoiceExtensionSlotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('extension-tests', __DIR__.'/fixtures');
    }

    public function test_component_renders_registered_items_in_order_with_context_data(): void
    {
        $registry = app(ViewSlotRegistry::class);
        $registry->register('invoice.create.fields', 'beta', 'extension-tests::extension-stub', fn (): array => ['label' => 'second']);
        $registry->register('invoice.create.fields', 'alpha', 'extension-tests::extension-stub', fn (array $context): array => [
            'label' => 'first',
            'contextInvoice' => $context['invoice']?->getKey() ?? 'none',
        ]);

        $html = $this->renderSlot('invoice.create.fields');

        self::assertStringContainsString('first', $html);
        self::assertStringContainsString('second', $html);
        self::assertStringContainsString('data-invoice="none"', $html);
        self::assertLessThan(strpos($html, 'second'), strpos($html, 'first'));
    }

    public function test_component_passes_the_bound_invoice_to_data_callbacks(): void
    {
        $year = FiscalYear::factory()->create();
        $user = User::factory()->create();
        $user->fiscalYears()->attach($year);
        $this->actingAs($user);
        config(['active-fiscal-year-id' => $year->id]);
        View::share('errors', new ViewErrorBag);
        $invoice = Invoice::create([
            'number' => 9001,
            'date' => '2026-10-26',
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::PRE_INVOICE,
            'creator_id' => $user->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 0,
            'fiscal_year_id' => $year->id,
        ]);
        app(ViewSlotRegistry::class)->register('invoice.edit.fields.test', 'alpha', 'extension-tests::extension-stub', fn (array $context): array => [
            'contextInvoice' => $context['invoice']?->getKey() ?? 'none',
        ]);

        $html = $this->renderSlot('invoice.edit.fields.test', $invoice);

        self::assertStringContainsString('data-invoice="'.$invoice->id.'"', $html);
    }

    public function test_component_skips_items_with_null_data_or_missing_views(): void
    {
        $registry = app(ViewSlotRegistry::class);
        $registry->register('invoice.create.fields', 'kept', 'extension-tests::extension-stub', fn (): array => ['label' => 'kept-item']);
        $registry->register('invoice.create.fields', 'opted-out', 'extension-tests::extension-stub', fn (): ?array => null);
        $registry->register('invoice.create.fields', 'ghost-view', 'extension-tests::missing-view');

        $html = $this->renderSlot('invoice.create.fields');

        self::assertStringContainsString('kept-item', $html);
        self::assertStringNotContainsString('unlabeled', $html);
    }

    public function test_component_renders_nothing_for_an_empty_slot(): void
    {
        self::assertSame('', trim($this->renderSlot('invoice.show.panels')));
    }

    private function renderSlot(string $slot, ?Invoice $invoice = null): string
    {
        return (new ExtensionSlot(app(ViewSlotRegistry::class), $slot, $invoice))->render()->render();
    }
}
