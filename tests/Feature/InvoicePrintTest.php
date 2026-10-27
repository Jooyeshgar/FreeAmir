<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ReportExportNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoicePrintTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $fiscalYear;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('en');
        $this->fiscalYear = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);
        $this->withCookies(['active-fiscal-year-id' => (string) $this->fiscalYear->id]);
        $this->user = User::factory()->create();
        $this->fiscalYear->users()->attach($this->user);
        foreach (['invoices.show', 'invoices.print', 'report'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
        $this->actingAs($this->user);
    }

    public static function printableInvoices(): array
    {
        $cases = [];
        foreach (InvoiceStatus::cases() as $status) {
            $cases[$status->name] = [$status, InvoiceType::SELL];
        }
        $cases['draft beginning inventory'] = [InvoiceStatus::UNAPPROVED, InvoiceType::BEGINNING_INVENTORY];
        $cases['approved beginning inventory'] = [InvoiceStatus::APPROVED, InvoiceType::BEGINNING_INVENTORY];

        return $cases;
    }

    #[DataProvider('printableInvoices')]
    public function test_print_flow_offers_all_actions_and_produces_html_and_pdfs(InvoiceStatus $status, InvoiceType $type): void
    {
        Notification::fake();
        $invoice = $this->invoice($status, $type);
        $draftLayout = $type->isBeginningInventory() || ! $status->isApprovedOrSettled();
        $response = $this->get(route('invoices.show', $invoice))->assertOk();
        $response->assertSee('id="invoice-pdf-delivery-'.$invoice->id.'"', false)
            ->assertSee('Send to email')->assertSee('Download')->assertSee('Cancel')
            ->assertSee('window.open(', false)->assertSee('autoprint=1', false);
        $this->assertSame(2, substr_count($response->getContent(), '>Print</button>'));
        $printResponse = $this->get(route('invoices.print', [$invoice, 'autoprint' => 1]))
            ->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertViewIs($draftLayout ? 'invoices.draft' : 'invoices.print')
            ->assertSee('window.print()', false)->assertSee('Print Widget');

        $printResponse->assertSee($draftLayout ? 'size: A4 portrait' : 'size: A4 landscape', false)
            ->assertSee('font-family: sahel', false);
        if (! $draftLayout) {
            $printResponse->assertSee('<title>صورتحساب فروش کالا و خدمات</title>', false)
                ->assertSee('print-color-adjust: exact', false)
                ->assertSee('<span>نقدی</span> <input type="checkbox" />', false);
        }
        $this->get(route('invoices.print', $invoice))->assertOk()->assertDontSee('window.print()', false);
        Notification::assertNothingSent();

        $filters = ['invoice_id' => $invoice->id];
        $response = $this->post(route('report'), [
            'export' => 'invoice_pdf', 'filters' => $filters, 'delivery' => 'download', 'email' => 'invalid',
        ])->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/pdf');
        $content = $response->streamedContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $content), 'A short invoice must fit on one PDF page.');
        Notification::assertNothingSent();
        $this->post(route('report'), [
            'export' => 'invoice_pdf', 'filters' => $filters, 'delivery' => 'email', 'email' => $this->user->email,
        ])->assertRedirect()->assertSessionHas('success');
        Notification::assertSentTo($this->user, ReportExportNotification::class,
            fn (ReportExportNotification $notification): bool => $notification->filename === 'invoice-'.formatDocumentNumber($invoice->number).'.pdf'
                && $notification->mime === 'application/pdf' && str_starts_with($notification->content, '%PDF-')
        );
        $this->assertSame($status, $invoice->fresh()->status);
    }

    public function test_print_and_pdf_export_require_print_permission(): void
    {
        Notification::fake();
        $invoice = $this->invoice();
        $this->user->revokePermissionTo('invoices.print');
        $this->get(route('invoices.print', $invoice))->assertForbidden();
        foreach (['download', 'email'] as $delivery) {
            $this->post(route('report'), [
                'export' => 'invoice_pdf', 'filters' => ['invoice_id' => $invoice->id],
                'delivery' => $delivery, 'email' => $this->user->email,
            ])->assertForbidden();
        }
        Notification::assertNothingSent();
    }

    public function test_other_fiscal_year_invoices_cannot_be_printed_downloaded_or_emailed(): void
    {
        Notification::fake();
        $invoice = $this->invoice();
        $invoice->updateQuietly(['fiscal_year_id' => FiscalYear::factory()->create()->id]);
        $this->get(route('invoices.print', $invoice))->assertNotFound();
        foreach (['download', 'email'] as $delivery) {
            $this->post(route('report'), [
                'export' => 'invoice_pdf', 'filters' => ['invoice_id' => $invoice->id],
                'delivery' => $delivery, 'email' => $this->user->email,
            ])->assertNotFound();
        }
        Notification::assertNothingSent();
    }

    private function invoice(InvoiceStatus $status = InvoiceStatus::UNAPPROVED, InvoiceType $type = InvoiceType::SELL): Invoice
    {
        $customer = $type->isBeginningInventory() ? null : Customer::create([
            'name' => 'Print Customer', 'fiscal_year_id' => $this->fiscalYear->id,
        ]);
        $invoice = Invoice::create([
            'number' => 443, 'date' => '2026-10-27', 'invoice_type' => $type, 'status' => $status,
            'customer_id' => $customer?->id, 'creator_id' => $this->user->id,
            'subtraction' => 0, 'vat' => 0, 'amount' => 100, 'description' => 'Print test',
        ]);
        $product = Product::factory()->create(['fiscal_year_id' => $this->fiscalYear->id, 'name' => 'Print Widget']);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'itemable_type' => Product::class, 'itemable_id' => $product->id,
            'quantity' => 1, 'unit_price' => 100, 'unit_discount' => 0, 'vat' => 0, 'amount' => 100,
        ]);

        return $invoice->refresh();
    }
}
