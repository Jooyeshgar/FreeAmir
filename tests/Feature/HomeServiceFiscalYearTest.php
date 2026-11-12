<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\HomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeServiceFiscalYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_statistics_group_fiscal_years_by_company_and_use_year_assignments(): void
    {
        $company = Company::factory()->create(['name' => 'Shared business']);
        $otherCompany = Company::factory()->create(['name' => 'Shared business']);
        $openYear = FiscalYear::query()->create(['company_id' => $company->id, 'year' => 1405]);
        $closedYear = FiscalYear::query()->create(['company_id' => $company->id, 'year' => 1404, 'closed_at' => now()]);
        $otherYear = FiscalYear::query()->create(['company_id' => $otherCompany->id, 'year' => 1405, 'closed_at' => now()]);

        $assigned = User::factory()->create(['created_at' => now()->subDay()]);
        $assigned->fiscalYears()->attach([$openYear->id, $closedYear->id]);
        User::factory()->create(['created_at' => now()->subDay()]);

        Document::withoutGlobalScopes()->create([
            'number' => 1,
            'date' => now()->toDateString(),
            'creator_id' => $assigned->id,
            'fiscal_year_id' => $closedYear->id,
            'created_at' => now(),
        ]);
        foreach ([$openYear, $otherYear] as $year) {
            Document::withoutGlobalScopes()->create([
                'number' => $year->id + 1,
                'date' => now()->toDateString(),
                'creator_id' => $assigned->id,
                'fiscal_year_id' => $year->id,
                'created_at' => now(),
            ]);
        }

        $dashboard = app(HomeService::class)->superAdminOverview();

        $this->assertSame(2, $dashboard['metrics']['businesses']);
        $this->assertSame(1, $dashboard['metrics']['activeBusinesses']);
        $this->assertSame(1, $dashboard['metrics']['openFiscalYears']);
        $this->assertSame(2, $dashboard['metrics']['closedFiscalYears']);
        $this->assertSame(1, $dashboard['metrics']['unassignedUsers']);
        $this->assertSame(50.0, $dashboard['metrics']['activationRate']);
        $this->assertSame(1, $dashboard['metrics']['firstDocumentNewUsers']);
        $this->assertSame(2, $dashboard['topUsageCompanies']->first()['documents']);
        $this->assertSame($company->id, $dashboard['topUsageCompanies']->first()['id']);
        $this->assertSame(2, $dashboard['topUsageCompanies']->count());
        $this->assertSame($otherCompany->id, $dashboard['topUsageCompanies']->last()['id']);
    }
}
