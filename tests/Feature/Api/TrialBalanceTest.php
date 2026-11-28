<?php

namespace Tests\Feature\Api;

use App\Enums\SubjectType;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrialBalanceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $fiscalYear;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fiscalYear = FiscalYear::factory()->create(['year' => 1404]);
        $this->user = User::factory()->create();
        $this->fiscalYear->users()->attach($this->user);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);

        foreach (['api.access', 'reports.trial-balance'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
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

    private function apiUrl(string $query = ''): string
    {
        return '/api/companies/'.$this->fiscalYear->id.'/reports/trial-balance'.$query;
    }

    public function test_trial_balance_requires_authentication(): void
    {
        $this->getJson($this->apiUrl())
            ->assertUnauthorized();
    }

    public function test_trial_balance_requires_permission(): void
    {
        $limitedUser = User::factory()->create();
        $this->fiscalYear->users()->attach($limitedUser);
        $limitedUser->givePermissionTo(Permission::firstOrCreate(['name' => 'api.access']));

        $this->getJson(
            $this->apiUrl(),
            $this->apiHeaders($limitedUser, ['api.access'])
        )->assertForbidden();
    }

    public function test_trial_balance_returns_subjects_structure(): void
    {
        Subject::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => '011',
            'name' => 'بانک ها',
            'parent_id' => null,
            'type' => SubjectType::BOTH,
        ]);

        $response = $this->getJson(
            $this->apiUrl(),
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
            ->assertJsonPath('data.meta.fiscal_year_id', $this->fiscalYear->id);
    }

    public function test_trial_balance_rejects_start_date_after_end_date(): void
    {
        $this->getJson(
            $this->apiUrl('?start_date=1403/05/10&end_date=1403/05/01'),
            $this->apiHeaders($this->user, ['api.access', 'reports.trial-balance'])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_trial_balance_rejects_invalid_date_format(): void
    {
        $this->getJson(
            $this->apiUrl('?start_date=not-a-date'),
            $this->apiHeaders($this->user, ['api.access', 'reports.trial-balance'])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_trial_balance_excludes_subjects_from_other_fiscal_years(): void
    {
        $subject = Subject::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => '011',
            'name' => 'بانک ها',
            'parent_id' => null,
            'type' => SubjectType::BOTH,
        ]);

        $otherYear = FiscalYear::create(['company_id' => $this->fiscalYear->company_id, 'year' => 1405]);

        DB::table('subjects')->where('id', $subject->id)->update([
            'fiscal_year_id' => $otherYear->id,
        ]);

        $this->getJson(
            $this->apiUrl(),
            $this->apiHeaders($this->user, ['api.access', 'reports.trial-balance'])
        )->assertOk()
            ->assertJsonCount(0, 'data.subjects')
            ->assertJsonPath('data.meta.subject_count', 0);
    }
}
