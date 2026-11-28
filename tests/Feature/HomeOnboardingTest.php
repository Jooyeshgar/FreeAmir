<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HomeOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $fiscalYear;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $company = Company::factory()->create(['name' => 'Onboarding Test Co']);
        $this->fiscalYear = FiscalYear::query()->create([
            'company_id' => $company->id,
            'year' => 1405,
        ]);

        $this->user = User::factory()->create();
        $this->user->fiscalYears()->attach($this->fiscalYear->id);

        foreach ([
            'home',
            'documents.index',
            'documents.show',
            'documents.create',
            'customers.index',
            'customers.create',
            'products.index',
            'products.create',
            'services.index',
            'services.create',
            'invoices.index',
            'invoices.create',
            'bank-accounts.index',
            'bank-accounts.create',
        ] as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
            $this->user->givePermissionTo($permissionName);
        }
    }

    public function test_fresh_company_shows_onboarding_checklist_with_action_buttons(): void
    {
        $response = $this->actingAs($this->user)
            ->withCookie('active-fiscal-year-id', (string) $this->fiscalYear->id)
            ->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-onboarding-checklist', false);
        $response->assertSee('data-onboarding-hide-now', false);
        $response->assertSee('data-onboarding-dismiss', false);
        $response->assertSee('data-onboarding-step="company"', false);
        $response->assertSee('data-onboarding-step="customer"', false);
        $response->assertSee('data-onboarding-step="catalog"', false);
        $response->assertSee('data-onboarding-step="transaction"', false);
        $response->assertSee('data-onboarding-step="bank_account"', false);
        $response->assertSee(route('customers.create'), false);
        $response->assertSee(route('products.create'), false);
        $response->assertSee(route('services.create'), false);
        $response->assertSee(route('invoices.create'), false);
        $response->assertSee(route('documents.create'), false);
        $response->assertSee(route('bank-accounts.create'), false);
    }

    public function test_steps_complete_automatically_and_hide_when_all_done(): void
    {
        $this->actingAs($this->user);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);

        $service = app(OnboardingService::class);
        $initial = $service->forUser($this->user);

        $this->assertNotNull($initial);
        $this->assertSame(1, $initial['completedCount']);
        $this->assertSame(5, $initial['totalCount']);
        $this->assertTrue($initial['steps'][1]['current']); // customer is first incomplete

        $customerGroup = CustomerGroup::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        Customer::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'group_id' => $customerGroup->id,
        ]);

        // Creating a service (without a product) should satisfy the catalog step.
        $serviceGroup = ServiceGroup::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        Service::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'group' => $serviceGroup->id,
        ]);

        // Creating a document (without an invoice) should satisfy the transaction step.
        Document::withoutGlobalScopes()->create([
            'number' => 1,
            'date' => now()->toDateString(),
            'creator_id' => $this->user->id,
            'fiscal_year_id' => $this->fiscalYear->id,
        ]);

        $mid = $service->forUser($this->user);
        $this->assertNotNull($mid);
        $this->assertSame(4, $mid['completedCount']);
        $this->assertTrue($mid['steps'][4]['current']); // bank_account is now current

        $bank = Bank::query()->create([
            'name' => 'Melli',
            'fiscal_year_id' => $this->fiscalYear->id,
        ]);
        BankAccount::query()->create([
            'name' => 'Main account',
            'number' => '1234567890',
            'type' => BankAccountType::cases()[0],
            'bank_id' => $bank->id,
        ]);

        // All 5 steps completed -> checklist disappears automatically.
        $this->assertNull($service->forUser($this->user));

        // Even if the user deletes those test records afterwards, completed steps stay persisted.
        Document::withoutGlobalScopes()->delete();
        $this->user->refresh();
        $this->assertNull($service->forUser($this->user));

        $response = $this->actingAs($this->user)
            ->withCookie('active-fiscal-year-id', (string) $this->fiscalYear->id)
            ->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('data-onboarding-checklist', false);
    }

    public function test_user_can_permanently_dismiss_onboarding_checklist(): void
    {
        $dismissResponse = $this->actingAs($this->user)
            ->postJson(route('home.onboarding.dismiss'));

        $dismissResponse->assertOk()->assertJson(['dismissed' => true]);

        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_dismissed_at);

        $homeResponse = $this->actingAs($this->user)
            ->withCookie('active-fiscal-year-id', (string) $this->fiscalYear->id)
            ->get(route('home'));

        $homeResponse->assertOk();
        $homeResponse->assertDontSee('data-onboarding-checklist', false);
    }

    public function test_onboarding_respects_user_permissions(): void
    {
        $limitedUser = User::factory()->create();
        $limitedUser->fiscalYears()->attach($this->fiscalYear->id);
        $limitedUser->givePermissionTo(['home', 'customers.index', 'customers.create']);

        $this->actingAs($limitedUser);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);

        $state = app(OnboardingService::class)->forUser($limitedUser);

        $this->assertNotNull($state);
        $this->assertSame(['company', 'customer'], array_column($state['steps'], 'key'));
    }

    public function test_core_index_pages_show_onboarding_empty_states_and_switch_to_filtered_state(): void
    {
        $cookie = (string) $this->fiscalYear->id;

        foreach ([
            route('customers.index') => route('customers.create'),
            route('products.index') => route('products.create'),
            route('services.index') => route('services.create'),
            route('documents.index') => route('documents.create'),
            route('bank-accounts.index') => route('bank-accounts.create'),
            route('invoices.index', ['invoice_type' => 'sell']) => route('invoices.create', ['invoice_type' => 'sell']),
        ] as $indexUrl => $createUrl) {
            $response = $this->actingAs($this->user)
                ->withCookie('active-fiscal-year-id', $cookie)
                ->get($indexUrl);

            $response->assertOk();
            $response->assertSee('data-empty-state="onboarding"', false);
            $response->assertSee($createUrl, false);
        }

        // When a search filter is active on an empty list, show the filtered empty state instead.
        $filteredResponse = $this->actingAs($this->user)
            ->withCookie('active-fiscal-year-id', $cookie)
            ->get(route('customers.index', ['name' => 'NonExistent']));

        $filteredResponse->assertOk();
        $filteredResponse->assertSee('data-empty-state="filtered"', false);
        $filteredResponse->assertDontSee('data-empty-state="onboarding"', false);
    }

    public function test_invoice_empty_state_shows_live_prerequisites_with_safe_return_to_links(): void
    {
        $cookie = (string) $this->fiscalYear->id;
        $sellUrl = route('invoices.index', ['invoice_type' => 'sell']);

        $response = $this->actingAs($this->user)
            ->withCookie('active-fiscal-year-id', $cookie)
            ->get($sellUrl);

        $response->assertOk();
        $response->assertSee('data-empty-state-prerequisites', false);
        $response->assertSee('data-prerequisite-item="customer" data-prerequisite-done="0"', false);
        $response->assertSee('data-prerequisite-item="catalog" data-prerequisite-done="0"', false);
        $response->assertSee('return_to=%2Finvoices%3Finvoice_type%3Dsell', false);

        // Create a customer and verify the prerequisite flips to done.
        $customerGroup = CustomerGroup::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        Customer::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'group_id' => $customerGroup->id,
        ]);

        $afterCustomer = $this->actingAs($this->user)
            ->withCookie('active-fiscal-year-id', $cookie)
            ->get($sellUrl);

        $afterCustomer->assertOk();
        $afterCustomer->assertSee('data-prerequisite-item="customer" data-prerequisite-done="1"', false);
        $afterCustomer->assertSee('data-prerequisite-item="catalog" data-prerequisite-done="0"', false);

        // Verify safeReturnUrl rejects external/protocol-relative URLs and allows internal paths.
        $this->assertSame('/invoices?invoice_type=sell', OnboardingService::safeReturnUrl('/invoices?invoice_type=sell'));
        $this->assertNull(OnboardingService::safeReturnUrl('https://evil.example/phish'));
        $this->assertNull(OnboardingService::safeReturnUrl('//evil.example/phish'));
        $this->assertNull(OnboardingService::safeReturnUrl('/\\evil.example'));
    }
}
