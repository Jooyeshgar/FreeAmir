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

    public function test_invoice_csv_export_applies_registered_extension_query(): void
    {
        $year = FiscalYear::factory()->create();
        $user = User::factory()->create();
        $user->fiscalYears()->attach($year);
        $this->actingAs($user);
        config(['active-fiscal-year-id' => $year->id]);

        $matched = Invoice::create([
            'number' => 9001,
            'date' => '2026-10-26',
            'invoice_type' => InvoiceType::BUY,
            'status' => InvoiceStatus::PRE_INVOICE,
            'creator_id' => $user->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 0,
            'fiscal_year_id' => $year->id,
        ]);
        $unmatched = Invoice::create([
            'number' => 9002,
            'date' => '2026-10-26',
            'invoice_type' => InvoiceType::BUY,
            'status' => InvoiceStatus::PRE_INVOICE,
            'creator_id' => $user->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 0,
            'fiscal_year_id' => $year->id,
        ]);

        $extension = new class implements \App\Extensions\Contracts\InvoiceExtension {
            public function validate(array $input, ?\Illuminate\Database\Eloquent\Model $invoice = null): array { return []; }
            public function persist(\Illuminate\Database\Eloquent\Model $invoice, array $data): void {}
            public function extendQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Http\Request $request): void {
                if ($request->input('filter_key') === 'only_9001') {
                    $query->where('number', 9001);
                }
            }
        };

        $this->app->instance(get_class($extension), $extension);
        app(\App\Extensions\InvoiceExtensionRegistry::class)->register('test_filter', get_class($extension));

        $exportService = app(\App\Services\ReportExportService::class);
        $result = $exportService->generate('invoices_csv', ['filter_key' => 'only_9001']);

        self::assertArrayHasKey('content', $result);
        $csvContent = $result['content'];

        self::assertStringContainsString('9001', $csvContent);
        self::assertStringNotContainsString('9002', $csvContent);
    }

    private function renderSlot(string $slot, ?Invoice $invoice = null): string
    {
        return (new ExtensionSlot(app(ViewSlotRegistry::class), $slot, $invoice))->render()->render();
    }
}
