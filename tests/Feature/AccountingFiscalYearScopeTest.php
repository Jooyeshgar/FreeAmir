<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Product;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingFiscalYearScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_years_of_one_business_and_another_business_have_separate_records_and_balanced_reports(): void
    {
        $years = [
            Company::factory()->create(['name' => 'Shared business', 'fiscal_year' => 1402]),
            Company::factory()->create(['name' => 'Shared business', 'fiscal_year' => 1403]),
            Company::factory()->create(['name' => 'Other business', 'fiscal_year' => 1403]),
        ];
        $user = User::factory()->create();
        $this->assertSame($years[0]->fiscalYear->company_identity_id, $years[1]->fiscalYear->company_identity_id);
        $this->assertNotSame($years[0]->fiscalYear->company_identity_id, $years[2]->fiscalYear->company_identity_id);

        foreach ($years as $index => $company) {
            $this->activate($company);
            $debit = Subject::create(['company_id' => $company->id, 'parent_id' => null, 'code' => '100', 'name' => 'Debit']);
            $credit = Subject::create(['company_id' => $company->id, 'parent_id' => null, 'code' => '200', 'name' => 'Credit']);
            Customer::create(['company_id' => $company->id, 'name' => 'Customer '.$index]);
            Product::factory()->create(['company_id' => $company->id]);

            $document = DocumentService::createDocument($user, [
                'company_id' => $company->id,
                'date' => '2024-06-01',
                'title' => 'Posting '.$index,
                'approved_at' => now(),
                'approver_id' => $user->id,
            ], [
                ['subject_id' => $debit->id, 'value' => -100 * ($index + 1)],
                ['subject_id' => $credit->id, 'value' => 100 * ($index + 1)],
            ]);
            $this->assertSame($company->fiscalYear->id, $document->fiscal_year_id);
        }

        foreach ($years as $index => $company) {
            $this->activate($company);
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
        $first = Company::factory()->create(['name' => 'Shared business', 'fiscal_year' => 1402]);
        $second = Company::factory()->create(['name' => 'Shared business', 'fiscal_year' => 1403]);
        $user = User::factory()->create();
        $this->activate($second);
        $foreignSubject = Subject::create(['company_id' => $second->id, 'parent_id' => null, 'code' => '100', 'name' => 'Foreign']);
        $this->activate($first);

        try {
            DocumentService::createDocument($user, [
                'company_id' => $first->id,
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

    private function activate(Company $company): void
    {
        config([
            'active-company-id' => $company->fiscalYear->company_identity_id,
            'active-legacy-company-id' => $company->id,
            'active-fiscal-year-id' => $company->fiscalYear->id,
        ]);
    }
}
