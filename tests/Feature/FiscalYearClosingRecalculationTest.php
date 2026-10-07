<?php

namespace Tests\Feature;

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
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.closing-wizard.recreate-opening']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.closing-wizard']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'change-company']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'documents.show']));
        $fiscalYear = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $fiscalYear->id]);

        $cash = Subject::factory()->create(['name' => 'Cash', 'is_permanent' => true]);
        $revenue = Subject::factory()->create(['name' => 'Revenue', 'is_permanent' => false]);
        $expense = Subject::factory()->create(['name' => 'Expense', 'is_permanent' => false]);
        $retainedProfit = Subject::factory()->create(['name' => __('Accumulated Profit and Loss'), 'is_permanent' => true]);

        $this->createDocument($fiscalYear, $user, 1, [
            $cash->id => 100,
            $revenue->id => -100,
        ]);

        $profitAndLossDocument = FiscalYearService::closeTemporaryAccounts($fiscalYear, $user);
        $currentProfit = Subject::where('name', __('Current Profit and Loss Summary'))->firstOrFail();
        $this->createDocument($fiscalYear, $user, 3, [
            $currentProfit->id => 100,
            $retainedProfit->id => -100,
        ]);

        $nextFiscalYear = FiscalYearService::stepThreeCloseAndOpenNewYear($fiscalYear, $user);
        $fiscalYear = $fiscalYear->fresh();
        $closingDocumentId = $fiscalYear->closing_document_id;
        $openingDocument = Document::withoutGlobalScopes()
            ->where('fiscal_year_id', $nextFiscalYear->id)
            ->where('number', 1)
            ->firstOrFail();
        $openingValues = $openingDocument->transactions()->pluck('value', 'subject_id')->all();

        $changedDocument = $this->createDocument($fiscalYear, $user, 5, [
            $expense->id => 20,
            $cash->id => -20,
        ]);

        $response = $this->actingAs($user)
            ->post(route('companies.closing-wizard.recalculate', $fiscalYear));

        $response->assertRedirect(route('companies.closing-wizard', $fiscalYear));
        $response->assertSessionHas('success', __('Closing recalculation started. Complete all three closing steps again.'));

        $fiscalYear = $fiscalYear->fresh();
        $this->assertNull($fiscalYear->closed_at);
        $this->assertSame(1, $fiscalYear->closing_recalculation_step);
        $this->assertDatabaseHas('documents', ['id' => $profitAndLossDocument->id]);
        $this->assertDatabaseHas('documents', ['id' => $closingDocumentId]);
        $this->assertDatabaseHas('documents', ['id' => $openingDocument->id]);

        $recalculatedProfitAndLoss = FiscalYearService::closeTemporaryAccounts($fiscalYear, $user);

        $this->assertSame($profitAndLossDocument->id, $recalculatedProfitAndLoss->id);
        $this->assertSame(2, $fiscalYear->fresh()->closing_recalculation_step);
        $this->assertEquals(20, FiscalYearService::getIncomeSummaryBalance($fiscalYear));

        DocumentService::deleteDocument($changedDocument->id);

        $restartResponse = $this->post(route('companies.closing-wizard.recalculate', $fiscalYear));

        $restartResponse->assertRedirect(route('companies.closing-wizard', $fiscalYear));
        $this->assertSame(1, $fiscalYear->fresh()->closing_recalculation_step);

        FiscalYearService::closeTemporaryAccounts($fiscalYear->fresh(), $user);

        $this->assertSame(2, $fiscalYear->fresh()->closing_recalculation_step);
        $this->assertSame(0.0, FiscalYearService::getIncomeSummaryBalance($fiscalYear));

        $recalculatedFiscalYear = FiscalYearService::stepThreeCloseAndOpenNewYear($fiscalYear, $user);

        $fiscalYear = $fiscalYear->fresh();
        $this->assertSame($fiscalYear->id, $recalculatedFiscalYear->id);
        $this->assertSame($closingDocumentId, $fiscalYear->closing_document_id);
        $this->assertNull($fiscalYear->closing_recalculation_step);
        $this->assertNotNull($fiscalYear->closed_at);
        $this->assertSame(2, FiscalYear::count());

        $closingValues = Document::findOrFail($closingDocumentId)->transactions()->pluck('value', 'subject_id');
        $this->assertEquals(-100, $closingValues[$cash->id]);
        $this->assertEquals(100, $closingValues[$retainedProfit->id]);
        $this->assertSame(
            $openingValues,
            Document::withoutGlobalScopes()->findOrFail($openingDocument->id)
                ->transactions()->pluck('value', 'subject_id')->all()
        );

        $wizard = $this->get(route('companies.closing-wizard', $fiscalYear));
        $wizard->assertOk()
            ->assertSee(route('change-company', ['company' => $nextFiscalYear, 'document' => $openingDocument->id]), false)
            ->assertSee(route('companies.closing-wizard.recreate-opening', $fiscalYear), false);

        $this->get(route('change-company', ['company' => $nextFiscalYear, 'document' => $openingDocument->id]))
            ->assertRedirect(route('documents.show', $openingDocument));
        config(['active-fiscal-year-id' => $nextFiscalYear->id]);
        $this->get(route('documents.show', $openingDocument))->assertOk();
        config(['active-fiscal-year-id' => $fiscalYear->id]);

        $closingDocument = Document::findOrFail($closingDocumentId);
        $closingDocument->transactions()->where('subject_id', $cash->id)->update(['value' => -120]);
        $closingDocument->transactions()->where('subject_id', $retainedProfit->id)->update(['value' => 120]);

        $response = $this->post(route('companies.closing-wizard.recreate-opening', $fiscalYear));
        $response->assertRedirect(route('companies.closing-wizard', $fiscalYear));
        $response->assertSessionHas('success', __('Opening Document recreated successfully.'));

        $this->assertDatabaseMissing('documents', ['id' => $openingDocument->id]);
        $rebuiltOpening = Document::withoutGlobalScopes()->where('fiscal_year_id', $nextFiscalYear->id)->where('number', 1)->firstOrFail();
        $this->assertNotSame($openingDocument->id, $rebuiltOpening->id);
        $rebuiltValues = $rebuiltOpening->transactions()->pluck('value', 'subject_id')->all();
        $this->assertNotEquals($openingValues, $rebuiltValues);
        $nextCash = Subject::withoutGlobalScopes()->where('fiscal_year_id', $nextFiscalYear->id)->where('code', $cash->code)->firstOrFail();
        $nextRetainedProfit = Subject::withoutGlobalScopes()->where('fiscal_year_id', $nextFiscalYear->id)->where('code', $retainedProfit->code)->firstOrFail();
        $this->assertEquals(120, $rebuiltValues[$nextCash->id]);
        $this->assertEquals(-120, $rebuiltValues[$nextRetainedProfit->id]);

        $nextCash->update(['code' => 'MISSING']);
        $this->post(route('companies.closing-wizard.recreate-opening', $fiscalYear))
            ->assertSessionHasErrors('fiscal_year');
        $this->assertDatabaseHas('documents', ['id' => $rebuiltOpening->id]);
        $this->assertEquals($rebuiltValues, $rebuiltOpening->fresh()->transactions()->pluck('value', 'subject_id')->all());

        $nextCash->update(['code' => $cash->code]);
        $rebuiltOpening->update(['title' => 'Manual document']);
        $this->post(route('companies.closing-wizard.recreate-opening', $fiscalYear))
            ->assertSessionHasErrors('fiscal_year');
        $this->assertDatabaseHas('documents', ['id' => $rebuiltOpening->id, 'title' => 'Manual document']);
    }

    public function test_it_rejects_recalculation_for_an_open_fiscal_year(): void
    {
        $user = User::factory()->create();
        $fiscalYear = FiscalYear::factory()->create();

        $this->expectException(ValidationException::class);

        FiscalYearService::recalculateClosingDocument($fiscalYear, $user);
    }

    public function test_opening_recreation_requires_completed_closing(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.closing-wizard.recreate-opening']));
        $fiscalYear = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $fiscalYear->id]);

        $this->actingAs($user)->post(route('companies.closing-wizard.recreate-opening', $fiscalYear))
            ->assertRedirect(route('companies.closing-wizard', $fiscalYear))
            ->assertSessionHasErrors('fiscal_year');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_the_recalculation_endpoint_reports_a_missing_closing_document(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'companies.closing-wizard.recalculate',
        ]));
        $fiscalYear = FiscalYear::factory()->create([
            'closed_at' => now(),
            'closed_by' => $user->id,
            'closing_document_id' => null,
        ]);
        config(['active-fiscal-year-id' => $fiscalYear->id]);

        $response = $this->actingAs($user)
            ->post(route('companies.closing-wizard.recalculate', $fiscalYear));

        $response->assertRedirect(route('companies.closing-wizard', $fiscalYear));
        $response->assertSessionHasErrors('fiscal_year');
    }

    private function createDocument(FiscalYear $fiscalYear, User $user, int $number, array $values): Document
    {
        $document = Document::create([
            'number' => $number,
            'date' => now(),
            'title' => 'Test document',
            'creator_id' => $user->id,
            'fiscal_year_id' => $fiscalYear->id,
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
