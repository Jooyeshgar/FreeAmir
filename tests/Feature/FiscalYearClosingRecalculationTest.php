<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FiscalYearClosingRecalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculation_repeats_all_three_steps_without_changing_the_next_year_opening_document(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'companies.closing-wizard.recalculate',
        ]));
        $company = Company::factory()->withoutFiscalYear()->create();
        $year = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        config(['active-company-id' => $company->id, 'active-fiscal-year-id' => $year->id]);
        $company->users()->attach($user);
        $year->users()->attach($user);
        $this->withCookies(['active-fiscal-year-id' => (string) $year->id]);

        $cash = Subject::factory()->create(['company_id' => $company->id, 'fiscal_year_id' => $year->id, 'name' => 'Cash', 'is_permanent' => true]);
        $revenue = Subject::factory()->create(['company_id' => $company->id, 'fiscal_year_id' => $year->id, 'name' => 'Revenue', 'is_permanent' => false]);
        $expense = Subject::factory()->create(['company_id' => $company->id, 'fiscal_year_id' => $year->id, 'name' => 'Expense', 'is_permanent' => false]);
        $retainedProfit = Subject::factory()->create(['company_id' => $company->id, 'fiscal_year_id' => $year->id, 'name' => __('Accumulated Profit and Loss'), 'is_permanent' => true]);

        $this->createDocument($year, $user, 1, [
            $cash->id => 100,
            $revenue->id => -100,
        ]);

        $profitAndLossDocument = FiscalYearService::closeTemporaryAccounts($year, $user);
        $currentProfit = Subject::where('name', __('Current Profit and Loss Summary'))->firstOrFail();
        $this->createDocument($year, $user, 3, [
            $currentProfit->id => 100,
            $retainedProfit->id => -100,
        ]);

        $nextFiscalYear = FiscalYearService::stepThreeCloseAndOpenNewYear($year, $user);
        $year = $year->fresh();
        $closingDocumentId = $year->closing_document_id;
        $this->assertSame($company->id, $nextFiscalYear->company_id);
        $this->assertNotSame($year->id, $nextFiscalYear->id);
        $openingDocument = Document::withoutGlobalScopes()
            ->where('fiscal_year_id', $nextFiscalYear->id)
            ->where('number', 1)
            ->firstOrFail();
        $this->assertSame($nextFiscalYear->id, $openingDocument->fiscal_year_id);
        $openingValues = $openingDocument->transactions()->pluck('value', 'subject_id')->all();

        $changedDocument = $this->createDocument($year, $user, 5, [
            $expense->id => 20,
            $cash->id => -20,
        ]);

        $response = $this->actingAs($user)
            ->post(route('companies.closing-wizard.recalculate', $year));

        $response->assertRedirect(route('companies.closing-wizard', $year));
        $response->assertSessionHas('success', __('Closing recalculation started. Complete all three closing steps again.'));

        $year = $year->fresh();
        $this->assertNull($year->closed_at);
        $this->assertSame(1, $year->closing_recalculation_step);
        $this->assertDatabaseHas('documents', ['id' => $profitAndLossDocument->id]);
        $this->assertDatabaseHas('documents', ['id' => $closingDocumentId]);
        $this->assertDatabaseHas('documents', ['id' => $openingDocument->id]);

        $recalculatedProfitAndLoss = FiscalYearService::closeTemporaryAccounts($year, $user);

        $this->assertSame($profitAndLossDocument->id, $recalculatedProfitAndLoss->id);
        $this->assertSame(2, $year->fresh()->closing_recalculation_step);
        $this->assertEquals(20, FiscalYearService::getIncomeSummaryBalance($year));

        DocumentService::deleteDocument($changedDocument->id);

        $restartResponse = $this->post(route('companies.closing-wizard.recalculate', $year));

        $restartResponse->assertRedirect(route('companies.closing-wizard', $year));
        $this->assertSame(1, $year->fresh()->closing_recalculation_step);

        FiscalYearService::closeTemporaryAccounts($year->fresh(), $user);

        $this->assertSame(2, $year->fresh()->closing_recalculation_step);
        $this->assertSame(0.0, FiscalYearService::getIncomeSummaryBalance($year));

        $recalculatedFiscalYear = FiscalYearService::stepThreeCloseAndOpenNewYear($year, $user);

        $year = $year->fresh();
        $this->assertSame($year->id, $recalculatedFiscalYear->id);
        $this->assertSame($closingDocumentId, $year->closing_document_id);
        $this->assertNull($year->closing_recalculation_step);
        $this->assertNotNull($year->closed_at);
        $this->assertSame(1, Company::count());
        $this->assertSame(2, FiscalYear::count());

        $closingValues = Document::findOrFail($closingDocumentId)->transactions()->pluck('value', 'subject_id');
        $this->assertEquals(-100, $closingValues[$cash->id]);
        $this->assertEquals(100, $closingValues[$retainedProfit->id]);
        $this->assertSame(
            $openingValues,
            Document::withoutGlobalScopes()->findOrFail($openingDocument->id)
                ->transactions()->pluck('value', 'subject_id')->all()
        );
    }

    public function test_it_rejects_recalculation_for_an_open_fiscal_year(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->withoutFiscalYear()->create();
        $year = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);

        $this->expectException(ValidationException::class);

        FiscalYearService::recalculateClosingDocument($year, $user);
    }

    public function test_the_recalculation_endpoint_reports_a_missing_closing_document(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'companies.closing-wizard.recalculate',
        ]));
        $company = Company::factory()->withoutFiscalYear()->create();
        $year = FiscalYear::create([
            'company_id' => $company->id,
            'year' => 1403,
            'closed_at' => now(),
            'closed_by' => $user->id,
            'closing_document_id' => null,
        ]);
        $company->users()->attach($user);
        $year->users()->attach($user);
        config(['active-company-id' => $company->id, 'active-fiscal-year-id' => $year->id]);

        $response = $this->actingAs($user)
            ->post(route('companies.closing-wizard.recalculate', $year));

        $response->assertRedirect(route('companies.closing-wizard', $year));
        $response->assertSessionHasErrors('company');
    }

    private function createDocument(FiscalYear $year, User $user, int $number, array $values): Document
    {
        $document = Document::create([
            'number' => $number,
            'date' => now(),
            'title' => 'Test document',
            'creator_id' => $user->id,
            'company_id' => $year->company_id,
            'fiscal_year_id' => $year->id,
            'approved_at' => now(),
            'approver_id' => $user->id,
        ]);

        foreach ($values as $subjectId => $value) {
            Transaction::create([
                'subject_id' => $subjectId,
                'document_id' => $document->id,
                'user_id' => $user->id,
                'value' => $value,
            ]);
        }

        return $document;
    }
}
