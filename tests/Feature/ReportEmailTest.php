<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ReportExportNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);
        config(['active-company-id' => $this->company->id]);
    }

    public function test_user_can_email_an_authorized_csv_export_to_their_own_address(): void
    {
        Notification::fake();
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'send-to-email']),
            Permission::firstOrCreate(['name' => 'products.export']),
        ]);
        Product::factory()->create(['company_id' => $this->company->id, 'name' => 'Emailed Widget', 'code' => 'EMAIL-1']);

        $response = $this->actingAs($this->user)->post(route('send-to-email'), [
            'export' => 'products_csv',
            'filters' => ['cols_submitted' => 1, 'columns' => ['code']],
            'email' => $this->user->email,
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        Notification::assertSentTo(
            $this->user,
            ReportExportNotification::class,
            function (ReportExportNotification $notification): bool {
                return str_ends_with($notification->filename, '.csv')
                    && str_starts_with($notification->mime, 'text/csv')
                    && str_contains($notification->content, 'Emailed Widget')
                    && str_contains($notification->content, 'EMAIL-1');
            }
        );
    }

    public function test_delivery_dialog_prefills_the_authenticated_users_email(): void
    {
        $this->actingAs($this->user);

        $html = Blade::render('<x-export-delivery-choice id="test-delivery" export="products_csv" />');

        $this->assertStringContainsString('Receive Report', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('value="'.$this->user->email.'"', $html);
        $this->assertStringContainsString('for example, recipient@example.com', $html);

        app()->setLocale('fa');
        $html = Blade::render('<x-export-delivery-choice id="fa-delivery" export="products_csv" />');
        $this->assertStringContainsString('دریافت گزارش', $html);
        $this->assertStringContainsString('برای مثال recipient@example.com', $html);
    }

    public function test_delivery_component_reuses_get_form_without_nesting_a_form_or_leaking_csrf_tokens(): void
    {
        $this->actingAs($this->user);

        $html = Blade::render('<form id="report-form" method="GET"><x-export-delivery-choice id="report-delivery" form="report-form" export="accounting_report_csv" /></form>');

        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertStringNotContainsString('<input type="hidden" name="_token"', $html);
        $this->assertSame(2, substr_count($html, 'name="_token"'));
        $this->assertStringContainsString('delivery=download', $html);
        $this->assertStringContainsString('delivery=email', $html);
    }

    public function test_product_quick_link_remains_a_direct_download_without_email_permission(): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'products.export']));
        $this->actingAs($this->user);

        $html = view('home._quick-access-link', ['link' => [
            'area' => 'export-products-link',
            'href' => route('products.export'),
            'label' => 'Receive Report',
            'style' => 'test-style',
        ]])->render();

        $this->assertStringContainsString('href="'.route('products.export').'"', $html);
        $this->assertStringNotContainsString('home-products-export-delivery', $html);
    }

    public function test_invoice_report_delivery_form_is_not_nested_in_the_get_filter_form(): void
    {
        $this->actingAs($this->user);
        view()->share('errors', new ViewErrorBag);

        $html = view('invoices.index.partials.search-form', [
            'invoiceType' => 'sell',
            'isSellWorkflow' => true,
            'showServiceBuy' => false,
            'showMoadian' => false,
            'showVoided' => false,
        ])->render();

        $filterFormEnd = strpos($html, '</form>');
        $deliveryDialogStart = strpos($html, '<dialog id="invoices-export-delivery"');

        $this->assertNotFalse($filterFormEnd);
        $this->assertNotFalse($deliveryDialogStart);
        $this->assertLessThan($deliveryDialogStart, $filterFormEnd);
        $this->assertStringContainsString('action="'.route('send-to-email').'" method="POST"', $html);
    }

    public function test_user_can_send_a_report_to_another_valid_email_address(): void
    {
        Notification::fake();
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'send-to-email']),
            Permission::firstOrCreate(['name' => 'products.export']),
        ]);

        $response = $this->actingAs($this->user)->post(route('send-to-email'), [
            'export' => 'products_csv',
            'delivery' => 'email',
            'email' => 'recipient@example.com',
        ]);

        $response->assertRedirect()->assertSessionHas('success', 'The report was sent to recipient@example.com.');
        Notification::assertSentOnDemand(
            ReportExportNotification::class,
            function (ReportExportNotification $notification, array $channels, object $notifiable): bool {
                return $notifiable->routes['mail'] === 'recipient@example.com'
                    && $notification->sentToAnotherRecipient
                    && $notification->requesterName === $this->user->name
                    && $notification->requesterEmail === $this->user->email;
            }
        );
        Notification::assertNotSentTo($this->user, ReportExportNotification::class);
    }

    public function test_email_delivery_requires_a_valid_recipient_address(): void
    {
        Notification::fake();
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'send-to-email']),
            Permission::firstOrCreate(['name' => 'products.export']),
        ]);

        $this->actingAs($this->user)->post(route('send-to-email'), [
            'export' => 'products_csv',
            'delivery' => 'email',
            'email' => 'not-an-email',
        ])->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_user_cannot_email_an_export_without_its_source_permission(): void
    {
        Notification::fake();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'send-to-email']));

        $this->actingAs($this->user)->post(route('send-to-email'), [
            'export' => 'products_csv',
            'email' => $this->user->email,
        ])->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_user_cannot_email_an_export_without_send_to_email_permission(): void
    {
        Notification::fake();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'products.export']));

        $this->actingAs($this->user)->post(route('send-to-email'), ['export' => 'products_csv', 'delivery' => 'email'])->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_download_uses_posted_filters_and_returns_an_attachment(): void
    {
        Notification::fake();
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'send-to-email']),
            Permission::firstOrCreate(['name' => 'products.export']),
        ]);
        Product::factory()->create(['company_id' => $this->company->id, 'name' => 'Private Filter Widget', 'code' => 'POST-1']);

        $response = $this->actingAs($this->user)->post(route('send-to-email', ['delivery' => 'download']), [
            'export' => 'products_csv',
            'email' => 'not-an-email',
            'filters' => ['cols_submitted' => 1, 'columns' => ['code']],
        ]);

        $response->assertOk()->assertHeader('Content-Disposition');
        $response->assertHeaderMissing('Location');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Private Filter Widget', $content);
        $this->assertStringContainsString('POST-1', $content);
        Notification::assertNothingSent();
    }

    public function test_original_product_export_route_still_downloads_the_shared_export(): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'products.export']));
        Product::factory()->create(['company_id' => $this->company->id, 'name' => 'Original Route Widget', 'code' => 'ROUTE-1']);

        $response = $this->actingAs($this->user)->get(route('products.export', [
            'cols_submitted' => 1,
            'columns' => ['code'],
        ]));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Original Route Widget', $content);
        $this->assertStringContainsString('ROUTE-1', $content);
    }

    public function test_unknown_export_type_is_rejected(): void
    {
        Notification::fake();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'send-to-email']));

        $this->actingAs($this->user)->post(route('send-to-email'), ['export' => 'arbitrary_file'])->assertSessionHasErrors('export');

        Notification::assertNothingSent();
    }

    public function test_report_notification_uses_the_custom_email_view(): void
    {
        $notification = new ReportExportNotification('report contents', 'report.csv', 'text/csv');

        $html = $notification->toMail($this->user)->render();

        $this->assertStringContainsString('Your report is ready', $html);
        $this->assertStringContainsString($this->user->name, $html);
        $this->assertStringContainsString('report.csv', $html);
        $this->assertStringContainsString('confidential business information', $html);
    }

    public function test_report_sent_to_another_recipient_identifies_the_requester(): void
    {
        $notification = new ReportExportNotification(
            'report contents',
            'report.csv',
            'text/csv',
            $this->user->name,
            $this->user->email,
            true,
        );

        $html = $notification->toMail($this->user)->render();

        $this->assertStringContainsString('This report was requested and sent to you by', $html);
        $this->assertStringContainsString($this->user->name, $html);
        $this->assertStringContainsString($this->user->email, $html);
    }

    public function test_accounting_report_can_be_downloaded_through_the_shared_delivery_flow(): void
    {
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'send-to-email']),
            Permission::firstOrCreate(['name' => 'reports.result']),
        ]);

        $response = $this->actingAs($this->user)->post(route('send-to-email'), [
            'export' => 'accounting_report_csv',
            'delivery' => 'download',
            'report_for' => 'Document',
        ]);

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Document Number', $response->streamedContent());
    }

    public function test_accounting_document_export_rejects_a_reversed_date_range(): void
    {
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'send-to-email']),
            Permission::firstOrCreate(['name' => 'reports.result']),
        ]);

        $this->actingAs($this->user)->post(route('send-to-email'), [
            'export' => 'accounting_report_csv',
            'delivery' => 'download',
            'report_for' => 'Document',
            'start_date' => '2026/12/31',
            'end_date' => '2026/01/01',
        ])->assertSessionHasErrors('start_date');
    }
}
