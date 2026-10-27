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
use App\Services\ReportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use PDF;
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

        if (! $draftLayout) {
            $printResponse->assertSee('<title>صورتحساب فروش کالا و خدمات</title>', false)
                ->assertSee('<span>نقدی</span> <input type="checkbox" />', false);
        }
        $this->get(route('invoices.print', $invoice))->assertOk()->assertSee('window.print()', false);
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

    public static function pdfLayouts(): array
    {
        return [
            'pre-invoice' => [InvoiceStatus::UNAPPROVED, true],
            'approved invoice' => [InvoiceStatus::APPROVED, false],
        ];
    }

    #[DataProvider('pdfLayouts')]
    public function test_pdf_embeds_styles_and_preserves_page_layout_without_browser_assets(InvoiceStatus $status, bool $draft): void
    {
        app()->setLocale('fa');
        $invoice = $this->invoice($status);
        $vite = $this->mock(Vite::class);
        $vite->shouldNotReceive('__invoke');
        $renderer = PDF::getFacadeRoot();
        $css = File::get(resource_path($draft ? 'css/invoice-draft.css' : 'css/invoice.css'));
        $orientation = $draft ? 'portrait' : 'landscape';

        PDF::shouldReceive('loadView')->once()->andReturnUsing(function (string $view, array $data, array $mergeData, array $config) use ($renderer, $css, $orientation, $draft) {
            $this->assertSame($draft ? 'invoices.pdf-draft' : 'invoices.pdf-download', $view);
            $html = view($view, $data)->render();
            $this->assertStringContainsString(str_replace('size: A4 '.$orientation.';', 'size: '.$orientation.';', $css), $html);
            $this->assertStringNotContainsString('<script', $html);
            $this->assertStringNotContainsString('<link', $html);
            $pdf = $renderer->loadHTML($html, $config);
            $mpdf = $pdf->getMpdf();
            $this->assertSame(1, $mpdf->page);
            $this->assertEqualsWithDelta($draft ? 210 : 297, $mpdf->w, 0.1);
            $this->assertEqualsWithDelta($draft ? 297 : 210, $mpdf->h, 0.1);
            $this->assertEqualsWithDelta(5, $mpdf->lMargin, 0.1);
            $this->assertEqualsWithDelta(5, $mpdf->rMargin, 0.1);

            return $pdf;
        });

        $file = app(ReportExportService::class)->generate('invoice_pdf', ['invoice_id' => $invoice->id]);
        $this->assertStringStartsWith('%PDF-', $file['content']);
        $this->assertMatchesRegularExpression('/\/BaseFont\s*\/[A-Z]+\+Sahel\b/', $file['content']);
        $this->assertMatchesRegularExpression('/\/BaseFont\s*\/[A-Z]+\+Sahel-Bold\b/', $file['content']);
        $this->assertStringContainsString('/FontFile2', $file['content']);
        $this->assertSame($vite, app(Vite::class));
    }

    public function test_pdf_view_failure_leaves_browser_assets_unchanged(): void
    {
        $invoice = $this->invoice();
        $vite = app(Vite::class);
        View::composer('invoices.pdf-draft', function () {
            throw new \RuntimeException('Invoice view failed');
        });

        try {
            app(ReportExportService::class)->generate('invoice_pdf', ['invoice_id' => $invoice->id]);
            $this->fail('The view rendering failure should propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Invoice view failed', $exception->getMessage());
        } finally {
            $this->assertSame($vite, app(Vite::class));
        }
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
