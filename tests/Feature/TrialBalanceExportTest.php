<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class TrialBalanceExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private TrialBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);

        foreach (['reports.trial-balance', 'reports.trial-balance.export-csv'] as $perm) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
        config(['active-company-id' => $this->company->id]);

        $this->service = app(TrialBalanceService::class);
    }

    public function test_trial_balance_export_returns_streamed_response(): void
    {
        $response = $this->service->exportCsv(request());

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_trial_balance_export_contains_csv_headers(): void
    {
        Subject::create(['company_id' => $this->company->id, 'code' => '011', 'name' => 'بانک ها', 'parent_id' => null, 'type' => 'both']);

        $response = $this->service->exportCsv(request());

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString(__('Account Code'), $csv);
        $this->assertStringContainsString(__('Account Name'), $csv);
        $this->assertStringContainsString(__('Sum Debit'), $csv);
        $this->assertStringContainsString(__('Sum Credit'), $csv);
        $this->assertStringContainsString(__('Remaining Debit'), $csv);
        $this->assertStringContainsString(__('Remaining Credit'), $csv);
    }

    public function test_trial_balance_export_includes_root_subjects_with_balances(): void
    {
        $root = Subject::create(['company_id' => $this->company->id, 'code' => '011', 'name' => 'بانک ها', 'parent_id' => null, 'type' => 'both']);
        $child = Subject::create(['company_id' => $this->company->id, 'code' => '011004', 'name' => 'پاسارگاد', 'parent_id' => $root->id, 'type' => 'both']);

        $doc = Document::factory()->create(['company_id' => $this->company->id, 'number' => 5, 'date' => '2026-01-10']);
        Transaction::create(['document_id' => $doc->id, 'subject_id' => $child->id, 'value' => 1000000, 'user_id' => $this->user->id]);
        Transaction::create(['document_id' => $doc->id, 'subject_id' => $child->id, 'value' => -500000, 'user_id' => $this->user->id]);

        $response = $this->service->exportCsv(request());

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('011', $csv);
        $this->assertStringContainsString('بانک ها', $csv);
        $this->assertStringContainsString(csvNumber(1000000), $csv);
        $this->assertStringContainsString(csvNumber(500000), $csv);
    }

    public function test_trial_balance_export_remain_bed_and_bes_reflect_net_balance(): void
    {
        $root = Subject::create(['company_id' => $this->company->id, 'code' => '011', 'name' => 'بانک', 'parent_id' => null, 'type' => 'both']);
        $doc = Document::factory()->create(['company_id' => $this->company->id, 'number' => 3, 'date' => '2026-01-01']);
        // Net debit: value=-300 (debit) + value=100 (credit) → net = -200 → RemainBed=200
        Transaction::create(['document_id' => $doc->id, 'subject_id' => $root->id, 'value' => -300, 'user_id' => $this->user->id]);
        Transaction::create(['document_id' => $doc->id, 'subject_id' => $root->id, 'value' => 100, 'user_id' => $this->user->id]);

        $response = $this->service->exportCsv(request());

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $rows = array_map('str_getcsv', array_filter(explode("\n", $csv)));
        $dataRow = $rows[1]; // first data row after header

        $this->assertSame(csvNumber(300), $dataRow[2], 'SumBed should be 300');
        $this->assertSame(csvNumber(100), $dataRow[3], 'SumBes should be 100');
        $this->assertSame(csvNumber(200), $dataRow[4], 'RemainBed should be 200 (net debit balance)');
        $this->assertSame(csvNumber(0), $dataRow[5], 'RemainBes should be 0');
    }

    public function test_trial_balance_export_route_returns_csv(): void
    {
        $response = $this->get(route('reports.trial-balance.export-csv'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_trial_balance_export_route_requires_permission(): void
    {
        $guest = User::factory()->create();
        $this->actingAs($guest);

        $response = $this->get(route('reports.trial-balance.export-csv'));

        $response->assertForbidden();
    }
}
