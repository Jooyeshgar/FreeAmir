<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Company;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccountingReportFiscalYearTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private int $subjectId;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->company = $company;
        $this->user = User::factory()->create();
        $company->users()->attach($this->user);
        $subjectType = DB::connection()->getDriverName() === 'sqlite' ? 'both' : SubjectType::BOTH->value;
        $this->subjectId = DB::table('subjects')->insertGetId([
            'company_id' => $company->id,
            'code' => '001',
            'name' => 'Test subject',
            'type' => $subjectType,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'reports.result']));

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $company->id]);
        config(['active-company-id' => $company->id]);
    }

    public function test_missing_accounting_report_dates_default_to_the_fiscal_year_for_html_preview_and_csv(): void
    {
        [$fiscalStart, $fiscalEnd] = $this->company->fiscalYearRange();
        $before = $this->transaction($fiscalStart->copy()->subDay()->toDateString(), 10, 'before fiscal year');
        $inside = $this->transaction($fiscalStart->copy()->addDay()->toDateString(), 11, 'inside fiscal year');
        $after = $this->transaction($fiscalEnd->copy()->addDay()->toDateString(), 12, 'after fiscal year');

        $html = $this->get(route('reports.result', [
            'report_for' => 'Ledger',
            'subject_id' => $this->subjectId,
        ]));
        $html->assertOk();
        $htmlIds = $html->viewData('transactionsChunk')->flatten()->pluck('id');
        $this->assertTrue($htmlIds->contains($inside->id));
        $this->assertFalse($htmlIds->contains($before->id));
        $this->assertFalse($htmlIds->contains($after->id));

        $preview = $this->get(route('reports.result', [
            'report_for' => 'Ledger',
            'subject_id' => $this->subjectId,
            'action' => 'preview',
        ]));
        $preview->assertRedirect();
        parse_str((string) parse_url($preview->headers->get('Location'), PHP_URL_QUERY), $previewQuery);
        $this->assertSame(convertToJalali($fiscalStart, true), $previewQuery['start_date']);
        $this->assertSame(convertToJalali($fiscalEnd, true), $previewQuery['end_date']);

        $controllerCsv = $this->get(route('reports.result', [
            'report_for' => 'Ledger',
            'subject_id' => $this->subjectId,
            'action' => 'export_csv',
            'start_date' => convertToJalali($fiscalStart, true),
        ]))->streamedContent();
        $this->assertStringContainsString('inside fiscal year', $controllerCsv);
        $this->assertStringNotContainsString('before fiscal year', $controllerCsv);
        $this->assertStringNotContainsString('after fiscal year', $controllerCsv);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'report']));
        $sharedResponse = $this->post(route('report'), [
            'export' => 'accounting_report_csv',
            'delivery' => 'download',
            'report_for' => 'Ledger',
            'subject_id' => $this->subjectId,
            'end_date' => convertToJalali($fiscalEnd, true),
        ]);
        $sharedResponse->assertOk();
        $sharedCsv = $sharedResponse->streamedContent();
        $this->assertStringContainsString('inside fiscal year', $sharedCsv);
        $this->assertStringNotContainsString('before fiscal year', $sharedCsv);
        $this->assertStringNotContainsString('after fiscal year', $sharedCsv);
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

    private function transaction(string $date, int $number, string $description): Transaction
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'date' => $date,
            'number' => $number,
            'title' => $description,
        ]);

        return Transaction::create([
            'document_id' => $document->id,
            'subject_id' => $this->subjectId,
            'user_id' => $this->user->id,
            'value' => -100,
            'desc' => $description,
        ]);
    }
}
