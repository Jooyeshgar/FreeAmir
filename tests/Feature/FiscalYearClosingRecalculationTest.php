<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
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
        $company = Company::factory()->create([
            'fiscal_year' => 1403,
        ]);
        config(['active-company-id' => $company->id]);

        $cash = Subject::factory()->create(['name' => 'Cash', 'is_permanent' => true]);
        $revenue = Subject::factory()->create(['name' => 'Revenue', 'is_permanent' => false]);
        $expense = Subject::factory()->create(['name' => 'Expense', 'is_permanent' => false]);
        $retainedProfit = Subject::factory()->create(['name' => __('Accumulated Profit and Loss'), 'is_permanent' => true]);

        $this->createDocument($company, $user, 1, [
            $cash->id => 100,
            $revenue->id => -100,
        ]);

        $profitAndLossDocument = FiscalYearService::closeTemporaryAccounts($company, $user);
        $currentProfit = Subject::where('name', __('Current Profit and Loss Summary'))->firstOrFail();
        $this->createDocument($company, $user, 3, [
            $currentProfit->id => 100,
            $retainedProfit->id => -100,
        ]);

        $nextFiscalYear = FiscalYearService::stepThreeCloseAndOpenNewYear($company, $user);
        $company = $company->fresh();
        $closingDocumentId = $company->closing_document_id;
        $openingDocument = Document::withoutGlobalScopes()
            ->where('company_id', $nextFiscalYear->id)
            ->where('number', 1)
            ->firstOrFail();
        $openingValues = $openingDocument->transactions()->pluck('value', 'subject_id')->all();

        $changedDocument = $this->createDocument($company, $user, 5, [
            $expense->id => 20,
            $cash->id => -20,
        ]);

        $response = $this->actingAs($user)
            ->post(route('companies.closing-wizard.recalculate', $company));

        $response->assertRedirect(route('companies.closing-wizard', $company));
        $response->assertSessionHas('success', __('Closing recalculation started. Complete all three closing steps again.'));

        $company = $company->fresh();
        $this->assertNull($company->closed_at);
        $this->assertSame(1, $company->closing_recalculation_step);
        $this->assertDatabaseHas('documents', ['id' => $profitAndLossDocument->id]);
        $this->assertDatabaseHas('documents', ['id' => $closingDocumentId]);
        $this->assertDatabaseHas('documents', ['id' => $openingDocument->id]);

        $recalculatedProfitAndLoss = FiscalYearService::closeTemporaryAccounts($company, $user);

        $this->assertSame($profitAndLossDocument->id, $recalculatedProfitAndLoss->id);
        $this->assertSame(2, $company->fresh()->closing_recalculation_step);
        $this->assertEquals(20, FiscalYearService::getIncomeSummaryBalance($company));

        DocumentService::deleteDocument($changedDocument->id);

        $restartResponse = $this->post(route('companies.closing-wizard.recalculate', $company));

        $restartResponse->assertRedirect(route('companies.closing-wizard', $company));
        $this->assertSame(1, $company->fresh()->closing_recalculation_step);

        FiscalYearService::closeTemporaryAccounts($company->fresh(), $user);

        $this->assertSame(2, $company->fresh()->closing_recalculation_step);
        $this->assertSame(0.0, FiscalYearService::getIncomeSummaryBalance($company));

        $recalculatedFiscalYear = FiscalYearService::stepThreeCloseAndOpenNewYear($company, $user);

        $company = $company->fresh();
        $this->assertSame($company->id, $recalculatedFiscalYear->id);
        $this->assertSame($closingDocumentId, $company->closing_document_id);
        $this->assertNull($company->closing_recalculation_step);
        $this->assertNotNull($company->closed_at);
        $this->assertSame(2, Company::count());

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
        $company = Company::factory()->create(['closed_at' => null]);

        $this->expectException(ValidationException::class);

        FiscalYearService::recalculateClosingDocument($company, $user);
    }

    public function test_the_recalculation_endpoint_reports_a_missing_closing_document(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'companies.closing-wizard.recalculate',
        ]));
        $company = Company::factory()->create([
            'closed_at' => now(),
            'closed_by' => $user->id,
            'closing_document_id' => null,
        ]);
        config(['active-company-id' => $company->id]);

        $response = $this->actingAs($user)
            ->post(route('companies.closing-wizard.recalculate', $company));

        $response->assertRedirect(route('companies.closing-wizard', $company));
        $response->assertSessionHasErrors('company');
    }

    private function createDocument(Company $company, User $user, int $number, array $values): Document
    {
        $document = Document::create([
            'number' => $number,
            'date' => now(),
            'title' => 'Test document',
            'creator_id' => $user->id,
            'company_id' => $company->id,
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
