<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContextualUserGuideTest extends TestCase
{
    use RefreshDatabase;

    private const GUIDE_PATH = 'management/system/companies/getting-started-fiscal-year.html';

    public function test_user_guide_component_builds_a_public_html_url_with_accessible_new_tab_attributes(): void
    {
        config(['app.user_guide_url' => 'https://guides.example.test/base/']);
        app()->setLocale('fa');

        $html = Blade::render('<x-user-guide-link source="/management/system/companies/getting-started-fiscal-year.md" />');

        $this->assertStringContainsString('href="https://guides.example.test/base/'.self::GUIDE_PATH.'"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('aria-label="راهنما"', $html);
        $this->assertStringNotContainsString('FiscalYearExportImport', $html);
    }

    public function test_user_guide_component_uses_the_english_counterpart_for_english_locale(): void
    {
        config(['app.user_guide_url' => 'https://guides.example.test/base/']);
        app()->setLocale('en');

        $html = Blade::render('<x-user-guide-link source="/invoices/sells/moadian-histories/how-to-use-moadian.md" />');

        $this->assertStringContainsString(
            'href="https://guides.example.test/base/invoices/sells/moadian-histories/how-to-use-moadian.en.html"',
            $html,
        );
    }

    public function test_financial_report_pages_reference_their_contextual_user_guides(): void
    {
        $guideViews = [
            'reports/accounting/documents/accounting-reports.md' => [
                'reports/documents.blade.php',
                'reports/journal.blade.php',
                'reports/ledger.blade.php',
                'reports/subLedger.blade.php',
                'reports/trialBalance.blade.php',
            ],
            'reports/company-overview/company-overview.md' => ['reports/company-overview.blade.php'],
            'reports/cost-income/cost-income.md' => ['reports/cost-income/index.blade.php'],
            'reports/budgets/budgets.md' => ['monthly-budgets/index.blade.php'],
        ];

        foreach ($guideViews as $guideSource => $views) {
            $guidePath = substr($guideSource, strpos($guideSource, '/') + 1);
            $guideIndex = file_get_contents(base_path('docs/'.strtok($guideSource, '/').'/README.md'));

            $this->assertFileExists(base_path('docs/'.$guideSource));
            $this->assertStringContainsString(']('.$guidePath.')', $guideIndex);

            foreach ($views as $view) {
                $this->assertStringContainsString(
                    'source="'.$guideSource.'"',
                    file_get_contents(resource_path('views/'.$view)),
                    $view,
                );
            }
        }
    }

    public function test_warehouse_pages_reference_their_split_contextual_user_guides(): void
    {
        $guideViews = [
            'warehouse/products/products.md' => [
                'products/index.blade.php',
                'services/index.blade.php',
                'productGroups/index.blade.php',
                'serviceGroups/index.blade.php',
            ],
            'warehouse/warehouses/warehouses.md' => [
                'warehouses/index.blade.php',
                'warehouses/transfer.blade.php',
            ],
            'warehouse/dashboard/dashboard.md' => ['warehouse/dashboard.blade.php'],
        ];

        foreach ($guideViews as $guideSource => $views) {
            $guidePath = substr($guideSource, strpos($guideSource, '/') + 1);
            $guideIndex = file_get_contents(base_path('docs/'.strtok($guideSource, '/').'/README.md'));

            $this->assertFileExists(base_path('docs/'.$guideSource));
            $this->assertStringContainsString(']('.$guidePath.')', $guideIndex);

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
            'active-fiscal-year-id' => null,
        ]);
        app()->setLocale('fa');

        $user = User::factory()->create();
        $company = FiscalYear::factory()->create();
        $company->users()->syncWithoutDetaching([$user->id]);
        $user->givePermissionTo(...collect([
            'home',
            'documents.show',
            'companies.index',
            'companies.create',
            'companies.edit',
            'companies.closing-wizard',
        ])->map(fn (string $name) => Permission::firstOrCreate(['name' => $name]))->all());

        config(['active-fiscal-year-id' => $company->id]);

        $this->actingAs($user)->withCookie('active-fiscal-year-id', (string) $company->id);

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

    public function test_moadian_setup_and_history_views_link_to_the_bilingual_guide(): void
    {
        $source = 'invoices/sells/moadian-histories/how-to-use-moadian.md';

        $this->assertFileExists(base_path('docs/'.$source));
        $this->assertFileExists(base_path('docs/'.str_replace('.md', '.en.md', $source)));

        foreach ([
            'companies/create.blade.php',
            'companies/edit.blade.php',
            'moadian-histories/index.blade.php',
            'moadian-histories/show.blade.php',
        ] as $view) {
            $this->assertStringContainsString(
                'source="'.$source.'"',
                file_get_contents(resource_path('views/'.$view)),
                $view,
            );
        }
    }

    private function guideLink(): string
    {
        return 'href="https://guides.example.test/base/'.self::GUIDE_PATH.'"';
    }
}
