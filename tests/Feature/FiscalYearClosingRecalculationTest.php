<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FiscalYearClosingRecalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replaces_the_previous_closing_document_with_recalculated_balances(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'fiscal_year' => 1403,
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);
        config(['active-company-id' => $company->id]);

        $cash = Subject::factory()->create(['name' => 'Cash', 'is_permanent' => true]);
        $capital = Subject::factory()->create(['name' => 'Capital', 'is_permanent' => true]);

        $this->createDocument($company, $user, 1, [
            $cash->id => 100,
            $capital->id => -100,
        ]);
        $oldClosingDocument = $this->createDocument($company, $user, 2, [
            $cash->id => -100,
            $capital->id => 100,
        ]);
        $company->update(['closing_document_id' => $oldClosingDocument->id]);

        $adjustment = $this->createDocument($company, $user, 3, [
            $cash->id => 25,
            $capital->id => -25,
        ]);

        $newClosingDocument = FiscalYearService::recalculateClosingDocument($company, $user);

        $this->assertDatabaseMissing('documents', ['id' => $oldClosingDocument->id]);
        $this->assertDatabaseMissing('transactions', ['document_id' => $oldClosingDocument->id]);
        $this->assertDatabaseHas('documents', ['id' => $adjustment->id]);
        $this->assertSame($newClosingDocument->id, $company->fresh()->closing_document_id);
        $this->assertEquals(4, $newClosingDocument->number);
        $this->assertNotNull($newClosingDocument->approved_at);
        $values = $newClosingDocument->transactions()->pluck('value', 'subject_id');
        $this->assertEquals(-125, $values[$cash->id]);
        $this->assertEquals(125, $values[$capital->id]);
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
