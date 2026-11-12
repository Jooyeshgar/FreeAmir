<?php

namespace Tests\Feature\Api;

use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->fiscalYear = FiscalYear::factory()->create();
        $this->user = User::factory()->create();
        $this->fiscalYear->users()->attach($this->user);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);
    }

    private function headers(array $abilities): array
    {
        return [
            'Authorization' => 'Bearer '.$this->user->createToken('api-test', $abilities)->plainTextToken,
        ];
    }

    private function grant(string $permission): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]));
    }

    public function test_trial_balance_requires_authentication(): void
    {
        $this->getJson('/api/companies/'.$this->fiscalYear->id.'/reports/trial-balance')
            ->assertUnauthorized();
    }

    public function test_trial_balance_requires_permission(): void
    {
        $this->grant('api.access');

        $this->getJson(
            '/api/companies/'.$this->fiscalYear->id.'/reports/trial-balance',
            $this->headers(['api.access'])
        )->assertForbidden();
    }

    public function test_trial_balance_returns_the_api_response_structure(): void
    {
        $this->grant('api.access');
        $this->grant('reports.trial-balance');

        $this->getJson(
            '/api/companies/'.$this->fiscalYear->id.'/reports/trial-balance',
            $this->headers(['api.access', 'reports.trial-balance'])
        )->assertOk()->assertJsonStructure([
            'data' => [
                'subjects',
                'filters',
                'conventions',
                'meta' => ['fiscal_year_id', 'subject_count', 'has_non_zero_balance'],
                'pagination',
            ],
        ])->assertJsonPath('data.meta.fiscal_year_id', $this->fiscalYear->id);
    }
}
