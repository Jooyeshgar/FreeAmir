<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['active-fiscal-year-id' => null]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::create(['name' => 'Super-Admin']));
        $this->actingAs($this->admin)->withSession(['interface_mode' => 'management']);
    }

    public function test_workspace_button_keeps_the_selected_historical_year_when_many_years_are_assigned(): void
    {
        $currentNumber = (int) toEnglish(jdate('Y'));
        $company = Company::factory()->create();
        foreach (range($currentNumber - 3, $currentNumber) as $number) {
            $year = FiscalYear::create(['company_id' => $company->id, 'year' => $number]);
            $year->users()->attach($this->admin);
        }
        $selected = $company->fiscalYears()->where('year', $currentNumber - 2)->firstOrFail();
        $this->withCookie('active-fiscal-year-id', (string) $selected->id);

        $this->assertWorkspaceButtonOpensHome();
        $this->assertSame($selected->id, config('active-fiscal-year-id'));
    }

    public function test_workspace_defaults_to_latest_assigned_year_when_there_is_no_current_year(): void
    {
        $number = (int) toEnglish(jdate('Y'));
        $company = Company::factory()->create();
        foreach (range($number - 3, $number - 1) as $yearNumber) {
            $latest = FiscalYear::create(['company_id' => $company->id, 'year' => $yearNumber]);
            $latest->users()->attach($this->admin);
        }

        $response = $this->get(route('fiscal-years.index'));
        $response->assertOk()->assertCookie('active-fiscal-year-id', (string) $latest->id);
        $this->assertSame($latest->id, config('active-fiscal-year-id'));
        $this->withCookie('active-fiscal-year-id', (string) $latest->id);
        $this->assertWorkspaceButtonOpensHome();
    }

    public function test_default_year_still_prefers_current_year_over_future_assigned_years(): void
    {
        $number = (int) toEnglish(jdate('Y'));
        $company = Company::factory()->create();
        $current = FiscalYear::create(['company_id' => $company->id, 'year' => $number]);
        $future = FiscalYear::create(['company_id' => $company->id, 'year' => $number + 1]);
        $this->admin->fiscalYears()->attach([$current->id, $future->id]);

        $this->get(route('fiscal-years.index'))->assertOk()
            ->assertCookie('active-fiscal-year-id', (string) $current->id);
        $this->assertSame($current->id, config('active-fiscal-year-id'));
    }

    public function test_unassigned_cookie_falls_back_to_an_assigned_historical_year(): void
    {
        $company = Company::factory()->create();
        $number = (int) toEnglish(jdate('Y'));
        $assigned = FiscalYear::create(['company_id' => $company->id, 'year' => $number - 1]);
        $assigned->users()->attach($this->admin);
        $unassigned = FiscalYear::create(['company_id' => $company->id, 'year' => $number]);

        $this->withCookie('active-fiscal-year-id', (string) $unassigned->id)
            ->get(route('fiscal-years.index'))->assertOk()
            ->assertCookie('active-fiscal-year-id', (string) $assigned->id);
        $this->assertSame($assigned->id, config('active-fiscal-year-id'));
    }

    public function test_admin_without_assigned_years_remains_in_management(): void
    {
        $response = $this->get(route('fiscal-years.index'))->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('management.dashboard'), '/').'"[^>]*data-testid="go-to-workspace"/',
            $response->getContent(),
        );
        $this->assertNull(config('active-fiscal-year-id'));
    }

    private function assertWorkspaceButtonOpensHome(): void
    {
        $management = $this->get(route('fiscal-years.index'))->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('home'), '/').'"[^>]*data-testid="go-to-workspace"/',
            $management->getContent(),
        );

        $this->get(route('home'))->assertOk()
            ->assertSee('id="workspace-drawer"', false)
            ->assertSessionHas('interface_mode', 'workspace');
    }
}
