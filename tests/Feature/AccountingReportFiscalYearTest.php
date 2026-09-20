<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccountingReportFiscalYearTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $subjectId;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->user = User::factory()->create();
        $company->users()->attach($this->user);
        $this->subjectId = DB::table('subjects')->insertGetId([
            'company_id' => $company->id,
            'code' => '001',
            'name' => 'Test subject',
            'type' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'reports.result']));

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $company->id]);
        config(['active-company-id' => $company->id]);
    }

    public function test_accounting_report_html_preview_and_csv_reject_dates_outside_the_active_fiscal_year(): void
    {
        foreach (['Journal', 'Ledger', 'subLedger', 'Document'] as $reportFor) {
            foreach ([null, 'preview', 'export_csv'] as $action) {
                $parameters = [
                    'report_for' => $reportFor,
                    'subject_id' => $this->subjectId,
                    'start_date' => '1404/12/29',
                    'end_date' => '1405/12/29',
                ];

                if ($action !== null) {
                    $parameters['action'] = $action;
                }

                $this->from(route('reports.result'))->get(route('reports.result', $parameters))
                    ->assertSessionHasErrors('start_date');

                $parameters['start_date'] = '1405/01/01';
                $parameters['end_date'] = '1406/01/01';

                $this->from(route('reports.result'))->get(route('reports.result', $parameters))
                    ->assertSessionHasErrors('end_date');
            }
        }
    }

    public function test_shared_accounting_csv_export_rejects_dates_outside_the_active_fiscal_year(): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'report']));

        foreach (['Journal', 'Ledger', 'subLedger', 'Document'] as $reportFor) {
            $base = [
                'export' => 'accounting_report_csv',
                'delivery' => 'download',
                'report_for' => $reportFor,
                'subject_id' => $this->subjectId,
            ];

            $this->post(route('report'), $base + [
                'start_date' => '1404/12/29',
                'end_date' => '1405/12/29',
            ])->assertSessionHasErrors('start_date');

            $this->post(route('report'), $base + [
                'start_date' => '1405/01/01',
                'end_date' => '1406/01/01',
            ])->assertSessionHasErrors('end_date');
        }
    }
}
