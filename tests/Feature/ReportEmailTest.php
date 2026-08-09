<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ReportExportNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            'email' => 'attacker@example.com',
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

    public function test_user_cannot_email_an_export_without_its_source_permission(): void
    {
        Notification::fake();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'send-to-email']));

        $this->actingAs($this->user)->post(route('send-to-email'), ['export' => 'products_csv'])->assertForbidden();

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

        $response = $this->actingAs($this->user)->post(route('send-to-email'), [
            'export' => 'products_csv',
            'delivery' => 'download',
            'filters' => ['cols_submitted' => 1, 'columns' => ['code']],
        ]);

        $response->assertOk()->assertHeader('Content-Disposition');
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
}
