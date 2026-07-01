<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\PersonnelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['fiscal_year' => (int) toEnglish(jdate('Y'))]);
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);

        $this->withCookies(['active-company-id' => (string) $this->company->id]);
        $_COOKIE['active-company-id'] = (string) $this->company->id;
        config([
            'active-company-id' => $this->company->id,
            'active-company-fiscal-year' => $this->company->fiscal_year,
        ]);
    }

    public function test_home_is_operational_workspace_without_financial_summary_widgets(): void
    {
        $this->grant('home', 'documents.index', 'invoices.index', 'customers.index');

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'creator_id' => $this->user->id,
            'title' => 'Pending approval document',
            'approved_at' => null,
        ]);

        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Operational Customer',
            'marked' => true,
        ]);

        Invoice::withoutEvents(fn () => Invoice::create([
            'number' => 'INV-100',
            'date' => now(),
            'invoice_type' => InvoiceType::SELL,
            'customer_id' => $customer->id,
            'creator_id' => $this->user->id,
            'subtraction' => 0,
            'status' => InvoiceStatus::PRE_INVOICE,
            'vat' => 0,
            'title' => 'Draft invoice',
            'description' => 'Needs review',
            'amount' => 1000000,
            'company_id' => $this->company->id,
        ]));

        $response = $this->actingAs($this->user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Unapproved documents');
        $response->assertSee('Invoices needing attention');
        $response->assertSee('Recent Accounting Documents');
        $response->assertSee('Recent Invoices');
        $response->assertSee('Recent Customers');
        $response->assertSee($document->title);
        $response->assertSee('INV-100');
        $response->assertSee('Operational Customer');
        $response->assertDontSee('Total Bank Balance');
        $response->assertDontSee('Profit and loss');
        $response->assertDontSee('Cash and banks balances');
    }

    public function test_home_shows_approved_personnel_requests_when_hr_has_wip(): void
    {
        $this->grant('home', 'hr.personnel-requests.index');

        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        PersonnelRequest::factory()->approved()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'payroll_id' => null,
        ]);

        $response = $this->actingAs($this->user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Approved personnel requests');
        $response->assertSee('Approved HR requests not attached to payroll yet');
    }

    public function test_home_only_shows_widgets_allowed_by_explicit_permissions(): void
    {
        $this->grant('home', 'invoices.index');

        $response = $this->actingAs($this->user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Recent Invoices');
        $response->assertDontSee('Recent Accounting Documents');
        $response->assertDontSee('Recent Customers');
    }

    private function grant(string ...$permissions): void
    {
        $this->user->givePermissionTo(
            collect($permissions)->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission]))
        );
    }
}
