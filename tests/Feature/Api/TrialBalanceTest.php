<?php

namespace Tests\Feature\Api;

use App\Enums\SubjectType;
use App\Models\Company;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrialBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);

        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);

        foreach (['api.access', 'reports.trial-balance'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
    }

    /**
     * Build the Authorization header for a Sanctum token that carries the given abilities.
     */
    private function apiHeaders(User $user, array $abilities): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test', $abilities)->plainTextToken,
        ];
    }

    public function test_trial_balance_requires_authentication(): void
    {
        $this->getJson("/api/companies/{$this->company->id}/reports/trial-balance")
            ->assertUnauthorized();
    }

    public function test_trial_balance_requires_permission(): void
    {
        $limitedUser = User::factory()->create();
        $this->company->users()->attach($limitedUser);
        $limitedUser->givePermissionTo(Permission::firstOrCreate(['name' => 'api.access', 'guard_name' => 'web']));

        $this->getJson(
            "/api/companies/{$this->company->id}/reports/trial-balance",
            $this->apiHeaders($limitedUser, ['api.access'])
        )->assertForbidden();
    }

    public function test_trial_balance_returns_subjects_structure(): void
    {
        Subject::create([
            'company_id' => $this->company->id,
            'code' => '011',
            'name' => 'بانک ها',
            'parent_id' => null,
            'type' => SubjectType::BOTH,
        ]);

        $response = $this->getJson(
            "/api/companies/{$this->company->id}/reports/trial-balance",
            $this->apiHeaders($this->user, ['api.access', 'reports.trial-balance'])
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'subjects' => [
                        '*' => [
                            'id', 'code', 'name', 'parent_id', 'depth',
                            'opening', 'turnover_debit', 'turnover_credit', 'balance',
                        ],
                    ],
                    'filters' => [
                        'subject_name', 'parent_id', 'include_children',
                        'start_date', 'end_date',
                        'start_document_number', 'end_document_number',
                    ],
                ],
            ])
            ->assertJsonPath('data.subjects.0.code', '011')
            ->assertJsonPath('data.meta.company_id', $this->company->id)
            ->assertJsonPath('data.meta.fiscal_year_id', $this->company->fiscalYear->id);
    }

    public function test_trial_balance_rejects_start_date_after_end_date(): void
    {
        $this->getJson(
            "/api/companies/{$this->company->id}/reports/trial-balance?start_date=1403/05/10&end_date=1403/05/01",
            $this->apiHeaders($this->user, ['api.access', 'reports.trial-balance'])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_trial_balance_rejects_invalid_date_format(): void
    {
        $this->getJson(
            "/api/companies/{$this->company->id}/reports/trial-balance?start_date=not-a-date",
            $this->apiHeaders($this->user, ['api.access', 'reports.trial-balance'])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_trial_balance_excludes_subjects_from_other_fiscal_years(): void
    {
        $subject = Subject::create([
            'company_id' => $this->company->id,
            'code' => '011',
            'name' => 'بانک ها',
            'parent_id' => null,
            'type' => SubjectType::BOTH,
        ]);

        $otherFiscalYear = Company::factory()->create()->fiscalYear;

        DB::table('subjects')->where('id', $subject->id)->update([
            'fiscal_year_id' => $otherFiscalYear->id,
        ]);

        $this->getJson(
            "/api/companies/{$this->company->id}/reports/trial-balance",
            $this->apiHeaders($this->user, ['api.access', 'reports.trial-balance'])
        )->assertOk()
            ->assertJsonCount(0, 'data.subjects')
            ->assertJsonPath('data.meta.subject_count', 0);
    }
}
