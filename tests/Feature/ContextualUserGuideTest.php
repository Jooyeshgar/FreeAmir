<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContextualUserGuideTest extends TestCase
{
    use RefreshDatabase;

    private const GUIDE_PATH = 'user/getting-started-fiscal-year.html';

    public function test_user_guide_component_builds_a_public_html_url_with_accessible_new_tab_attributes(): void
    {
        config(['app.user_guide_url' => 'https://guides.example.test/base/']);
        app()->setLocale('fa');

        $html = Blade::render('<x-user-guide-link source="/user/getting-started-fiscal-year.md" />');

        $this->assertStringContainsString('href="https://guides.example.test/base/'.self::GUIDE_PATH.'"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('aria-label="راهنما"', $html);
        $this->assertStringNotContainsString('FiscalYearExportImport', $html);
    }

    public function test_financial_report_pages_reference_their_contextual_user_guides(): void
    {
        $guideViews = [
            'user/accounting-reports.md' => [
                'reports/documents.blade.php',
                'reports/journal.blade.php',
                'reports/ledger.blade.php',
                'reports/subLedger.blade.php',
                'reports/trialBalance.blade.php',
            ],
            'user/company-overview.md' => ['reports/company-overview.blade.php'],
            'user/cost-income-dashboard.md' => ['reports/cost-income/index.blade.php'],
            'user/monthly-income-expense-forecasting.md' => ['monthly-budgets/index.blade.php'],
        ];
        $guideIndex = file_get_contents(base_path('docs/user/README.md'));

        foreach ($guideViews as $guideSource => $views) {
            $guideName = basename($guideSource);

            $this->assertFileExists(base_path('docs/'.$guideSource));
            $this->assertStringContainsString('['.$guideName.']('.$guideName.')', $guideIndex);

            foreach ($views as $view) {
                $this->assertStringContainsString(
                    'source="'.$guideSource.'"',
                    file_get_contents(resource_path('views/'.$view)),
                    $view,
                );
            }
        }
    }

    public function test_company_and_fiscal_year_pages_show_the_contextual_user_guide(): void
    {
        config([
            'app.user_guide_url' => 'https://guides.example.test/base',
            'active-company-id' => null,
        ]);
        app()->setLocale('fa');

        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->syncWithoutDetaching([$user->id]);
        $user->givePermissionTo(...collect([
            'home',
            'documents.show',
            'companies.index',
            'companies.create',
            'companies.edit',
            'companies.closing-wizard',
        ])->map(fn (string $name) => Permission::firstOrCreate(['name' => $name]))->all());

        config(['active-company-id' => $company->id]);

        $this->actingAs($user)->withCookie('active-company-id', (string) $company->id);

        foreach ([
            route('home'),
            route('companies.index'),
            route('companies.create'),
            route('companies.edit', $company),
            route('companies.closing-wizard', $company),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee($this->guideLink(), false);
        }
    }

    public function test_management_company_list_and_first_company_form_show_the_same_user_guide(): void
    {
        config(['app.user_guide_url' => 'https://guides.example.test/base/']);
        app()->setLocale('fa');

        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(
            Permission::firstOrCreate(['name' => 'access-super-admin-panel']),
            Permission::firstOrCreate(['name' => 'companies.index']),
        );

        $this->actingAs($superAdmin)
            ->withSession(['interface_mode' => 'management'])
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee($this->guideLink(), false);

        $newUser = User::factory()->create();

        $this->actingAs($newUser)
            ->get(route('registered-user.company.create'))
            ->assertOk()
            ->assertSee($this->guideLink(), false);
    }

    private function guideLink(): string
    {
        return 'href="https://guides.example.test/base/'.self::GUIDE_PATH.'"';
    }
}
