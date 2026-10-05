<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingFiscalYearScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_years_of_one_business_and_another_business_have_separate_records_and_balanced_reports(): void
    {
        $business = Company::factory()->withoutFiscalYear()->create(['name' => 'Shared business']);
        $otherBusiness = Company::factory()->withoutFiscalYear()->create(['name' => 'Other business']);
        $years = [
            FiscalYear::create(['company_id' => $business->id, 'year' => 1402]),
            FiscalYear::create(['company_id' => $business->id, 'year' => 1403]),
            FiscalYear::create(['company_id' => $otherBusiness->id, 'year' => 1403]),
        ];
        $user = User::factory()->create();
        $this->assertSame($years[0]->company_id, $years[1]->company_id);
        $this->assertNotSame($years[0]->company_id, $years[2]->company_id);

        foreach ($years as $index => $year) {
            $this->activate($year);
            $debit = Subject::create(['company_id' => $year->company_id, 'fiscal_year_id' => $year->id, 'parent_id' => null, 'code' => '100', 'name' => 'Debit']);
            $credit = Subject::create(['company_id' => $year->company_id, 'fiscal_year_id' => $year->id, 'parent_id' => null, 'code' => '200', 'name' => 'Credit']);
            Customer::create(['company_id' => $year->company_id, 'fiscal_year_id' => $year->id, 'name' => 'Customer '.$index]);
            Product::factory()->create(['company_id' => $year->company_id, 'fiscal_year_id' => $year->id]);

            $document = DocumentService::createDocument($user, [
                'company_id' => $year->company_id,
                'fiscal_year_id' => $year->id,
                'date' => '2024-06-01',
                'title' => 'Posting '.$index,
                'approved_at' => now(),
                'approver_id' => $user->id,
            ], [
                ['subject_id' => $debit->id, 'value' => -100 * ($index + 1)],
                ['subject_id' => $credit->id, 'value' => 100 * ($index + 1)],
            ]);
            $this->assertSame($year->id, $document->fiscal_year_id);
        }

        foreach ($years as $index => $year) {
            $this->activate($year);
            $this->assertSame(1, Document::count());
            $this->assertSame(2, Subject::count());
            $this->assertSame(1, Customer::count());
            $this->assertSame(1, Product::count());
            $this->assertSame('Customer '.$index, Customer::sole()->name);
            $this->assertEquals(0, Transaction::query()->whereHas('document')->sum('value'));
            $this->assertEquals(100 * ($index + 1), Transaction::query()->whereHas('document')->where('value', '>', 0)->sum('value'));
        }
    }

    public function test_posting_rejects_a_subject_from_another_fiscal_year(): void
    {
        $company = Company::factory()->withoutFiscalYear()->create(['name' => 'Shared business']);
        $first = FiscalYear::create(['company_id' => $company->id, 'year' => 1402]);
        $second = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        $user = User::factory()->create();
        $this->activate($second);
        $foreignSubject = Subject::create(['company_id' => $company->id, 'fiscal_year_id' => $second->id, 'parent_id' => null, 'code' => '100', 'name' => 'Foreign']);
        $this->activate($first);

        try {
            DocumentService::createDocument($user, [
                'company_id' => $company->id,
                'fiscal_year_id' => $first->id,
                'date' => '2024-06-01',
                'title' => 'Rejected',
            ], [['subject_id' => $foreignSubject->id, 'value' => -100]]);
            $this->fail('Posting across fiscal years must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subject_id', $exception->errors());
        }

        $this->assertSame(0, Document::withoutGlobalScopes()->count());
        $this->assertSame(0, Transaction::count());
    }

    public function test_subject_codes_and_parents_are_scoped_to_the_fiscal_year(): void
    {
        $company = Company::factory()->withoutFiscalYear()->create();
        $first = FiscalYear::create(['company_id' => $company->id, 'year' => 1402]);
        $second = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        $service = app(SubjectService::class);

        $this->activate($first);
        $priorYearRoot = $service->createSubject(['name' => 'Prior year', 'code' => '100']);

        $this->activate($second);
        $currentYearRoot = $service->createSubject(['name' => 'Current year', 'code' => '100']);
        $this->assertSame('100', $currentYearRoot->code);
        $this->assertNotSame($priorYearRoot->id, $currentYearRoot->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->createSubject(['name' => 'Invalid child', 'parent_id' => $priorYearRoot->id, 'code' => '001']);
    }

    private function activate(FiscalYear $year): void
    {
        config([
            'active-company-id' => $year->company_id,
            'active-fiscal-year-id' => $year->id,
        ]);
    }
}
