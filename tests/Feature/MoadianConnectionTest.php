<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\MoadianService;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Jooyeshgar\Moadian\Facades\Moadian;
use Jooyeshgar\Moadian\Http\Response;
use Mockery;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MoadianConnectionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create(['fiscal_year' => 1405, 'moadian_username' => 'company-user', 'private_key_path' => 'key.pem', 'certificate_path' => 'certificate.crt']);
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company);
        $this->actingAs($this->user);
        config(['active-company-id' => $this->company->id]);
        foreach (['companies.edit', 'companies.test-moadian-connection', 'invoices.moadian-check-status', 'invoices.moadian-histories.show', 'invoices.moadian-histories.index'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
        Storage::shouldReceive('exists')->with('key.pem')->andReturnTrue();
        Storage::shouldReceive('exists')->with('certificate.crt')->andReturnTrue();
        Storage::shouldReceive('get')->with('key.pem')->andReturn('private-key');
        Storage::shouldReceive('get')->with('certificate.crt')->andReturn('certificate');
    }

    private function client(): MockInterface
    {
        $client = Mockery::mock(\Jooyeshgar\Moadian\Moadian::class);
        Moadian::shouldReceive('for')->with('private-key', 'certificate', 'company-user')->andReturn($client);

        return $client;
    }

    private function invoice(): Invoice
    {
        $customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->company->id, 'name' => 'Test customer', 'type' => 'individual',
        ]);

        return Invoice::create([
            'company_id' => $this->company->id, 'customer_id' => $customerId,
            'number' => 419, 'date' => '2026-10-10', 'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED, 'subtraction' => 0,
            'vat' => 0, 'amount' => 0,
        ]);
    }

    private function response(array $body, int $status = 200): Response
    {
        return (new Response)->setResponse(new HttpResponse($status, [], json_encode($body)));
    }

    public function test_permission_migration_preserves_access_for_existing_company_editors(): void
    {
        $role = Role::create(['name' => 'Company editor']);
        $role->givePermissionTo('companies.edit');
        $migration = require database_path('migrations/2026_10_10_160000_add_moadian_connection_permission.php');
        $migration->up();
        $migration->up();
        $this->assertTrue($role->fresh()->hasPermissionTo('companies.test-moadian-connection'));
    }

    public function test_connection_calls_server_info_and_reports_success(): void
    {
        $this->client()->shouldReceive('getServerInfo')->once()->andReturn($this->response(['publicKeys' => []]));
        $this->get(route('companies.test-moadian-connection', $this->company))
            ->assertRedirect(route('companies.edit', $this->company))->assertSessionHas('success');
        $this->assertDatabaseCount('moadian_histories', 0);
    }

    public function test_connection_failure_does_not_expose_exception_details(): void
    {
        $this->client()->shouldReceive('getServerInfo')->once()->andThrow(new \RuntimeException('sensitive details'));
        $response = $this->get(route('companies.test-moadian-connection', $this->company));
        $response->assertSessionHas('error', __('Connection to Moadian failed. Please check the saved settings and try again.'));
    }

    public function test_unsuccessful_response_is_reported_as_connection_failure(): void
    {
        $this->client()->shouldReceive('getServerInfo')->once()->andReturn($this->response(['errors' => [['message' => 'error', 'code' => '1']]], 400));
        $this->get(route('companies.test-moadian-connection', $this->company))->assertSessionHas('error');
    }

    public function test_missing_credentials_do_not_call_moadian(): void
    {
        $this->company->update(['moadian_username' => null]);
        Moadian::shouldReceive('for')->never();
        $this->from(route('companies.edit', $this->company))
            ->get(route('companies.test-moadian-connection', $this->company))->assertSessionHasErrors('moadian');
    }

    public function test_company_access_and_permission_are_required(): void
    {
        Moadian::shouldReceive('for')->never();
        $otherCompany = Company::factory()->create();
        $this->get(route('companies.test-moadian-connection', $otherCompany))->assertForbidden();
        $this->user->revokePermissionTo('companies.test-moadian-connection');
        $this->get(route('companies.test-moadian-connection', $this->company))->assertForbidden();
    }

    public function test_edit_page_contains_a_separate_connection_form(): void
    {
        $this->get(route('companies.edit', $this->company))->assertOk()
            ->assertSee('form="test-moadian-connection"', false)
            ->assertSee(__('The connection test uses saved settings. Save any changes first.'));
    }

    public function test_status_checks_reuse_reference_after_an_error_and_preserve_it(): void
    {
        $invoice = $this->invoice();
        $invoice->moadianHistories()->create(['data' => ['referenceNumber' => 'ref-419', 'status' => 'PENDING']]);
        $invoice->moadianHistories()->create(['data' => ['status' => 'FAILED', 'error' => 'timeout']]);
        $this->client()->shouldReceive('inquiryByReferenceNumbers')->with('ref-419')->twice()
            ->andReturn($this->response([['status' => 'SUCCESS']]));
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('invoices.moadian-check-status', $invoice))->assertSessionHas('success');
        }
        $this->assertSame('ref-419', $invoice->moadianHistories()->orderByDesc('id')->first()->data['referenceNumber']);
    }

    public function test_status_failure_and_empty_response_keep_reference_for_retry(): void
    {
        $invoice = $this->invoice();
        $client = $this->client();
        $client->shouldReceive('inquiryByReferenceNumbers')->with('ref-419')->once()->andThrow(new \RuntimeException('timeout'));
        $data = app(MoadianService::class)->moadianStatus('ref-419', $invoice);
        $this->assertSame('FAILED', $data['status']);
        $this->assertSame('ref-419', $data['referenceNumber']);
        $client->shouldReceive('inquiryByReferenceNumbers')->with('ref-419')->once()->andReturn($this->response([]));
        $this->post(route('invoices.moadian-check-status', $invoice))->assertSessionHas('error');
        $this->assertSame('ref-419', $invoice->moadianReferenceNumber());
    }

    public function test_invoice_without_reference_is_not_queried(): void
    {
        Moadian::shouldReceive('for')->never();
        $this->post(route('invoices.moadian-check-status', $this->invoice()))->assertSessionHas('error');
    }

    public function test_status_button_is_available_for_pending_and_successful_invoices(): void
    {
        $invoice = $this->invoice();
        foreach (['PENDING', 'SUCCESS'] as $status) {
            $invoice->moadianHistories()->create(['data' => ['referenceNumber' => 'ref-419', 'status' => $status]]);
            $this->get(route('invoices.moadian-histories.show', $invoice))->assertOk()
                ->assertSee('action="'.route('invoices.moadian-check-status', $invoice).'"', false);
            $this->get(route('invoices.moadian-histories.index'))->assertOk()
                ->assertSee('action="'.route('invoices.moadian-check-status', $invoice).'"', false);
        }
        $this->user->revokePermissionTo('invoices.moadian-check-status');
        $this->get(route('invoices.moadian-histories.show', $invoice))->assertOk()
            ->assertDontSee('action="'.route('invoices.moadian-check-status', $invoice).'"', false);
        $this->post(route('invoices.moadian-check-status', $invoice))->assertForbidden();
    }

    public function test_status_cannot_be_checked_in_another_company(): void
    {
        $invoice = $this->invoice();
        $other = Company::factory()->create(['fiscal_year' => 1405]);
        $this->user->companies()->sync([$other->id]);
        Moadian::shouldReceive('for')->never();
        $this->post(route('invoices.moadian-check-status', $invoice))->assertForbidden();
    }
}
