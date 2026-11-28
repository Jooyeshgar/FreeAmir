<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApprovedDocumentEditReviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $approved): array
    {
        app()->setLocale('en');
        $year = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $year->id]);
        $user = User::factory()->create(['name' => 'مریم احمدی']);
        $year->users()->attach($user);
        $user->givePermissionTo(Permission::findOrCreate('documents.*'));
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => (string) $year->id]);
        $expense = Subject::create(['parent_id' => null, 'code' => '040', 'name' => 'هزینه پست', 'type' => SubjectType::DEBTOR, 'is_permanent' => false]);
        $cash = Subject::create(['parent_id' => null, 'code' => '011', 'name' => 'صندوق', 'type' => SubjectType::BOTH, 'is_permanent' => true]);
        $document = Document::factory()->create(['date' => '2026-04-10', 'approved_at' => $approved ? '2026-04-11' : null, 'approver_id' => $approved ? $user->id : null]);
        $debit = Transaction::factory()->create(['document_id' => $document->id, 'subject_id' => $expense->id, 'value' => -100]);
        $credit = Transaction::factory()->create(['document_id' => $document->id, 'subject_id' => $cash->id, 'value' => 100]);

        return [$document, [
            'document_id' => $document->id, 'number' => $document->number, 'date' => '2026/04/12', 'title' => 'هزینه پست ویرایش‌شده',
            'transactions' => [
                ['subject_id' => $expense->id, 'transaction_id' => $debit->id, 'debit' => 200, 'credit' => 0, 'desc' => 'هزینه پست'],
                ['subject_id' => $cash->id, 'transaction_id' => $credit->id, 'debit' => 0, 'credit' => 200, 'desc' => 'پرداخت صندوق'],
            ],
        ]];
    }

    public function test_unbalanced_edit_of_an_approved_document_rolls_back_header_and_rows(): void
    {
        [$document, $payload] = $this->fixture(true);
        $beforeHeader = $document->fresh()->getAttributes();
        $beforeRows = $document->transactions()->orderBy('id')->get()->map->getAttributes()->all();
        $payload['transactions'][1]['credit'] = 190;
        $this->putJson(route('documents.update', $document), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('transactions');
        $this->assertSame($beforeHeader, $document->fresh()->getAttributes());
        $this->assertSame($beforeRows, $document->transactions()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame('0.00', $document->transactions()->sum('value'));
    }

    public function test_balanced_edit_keeps_the_existing_approval_metadata(): void
    {
        [$document, $payload] = $this->fixture(true);
        $beforeApproval = $document->fresh()->getAttributes()['approved_at'];
        $this->putJson(route('documents.update', $document), $payload)->assertRedirect(route('documents.index'));
        $this->assertSame($beforeApproval, $document->fresh()->getAttributes()['approved_at']);
        $this->assertSame('0.00', $document->transactions()->sum('value'));
        $this->assertDatabaseHas('transactions', ['id' => $payload['transactions'][0]['transaction_id'], 'value' => '-200.00']);
        $this->assertDatabaseHas('transactions', ['id' => $payload['transactions'][1]['transaction_id'], 'value' => '200.00']);
    }

    public function test_unbalanced_draft_edit_keeps_the_existing_draft_behavior(): void
    {
        [$document, $payload] = $this->fixture(false);
        $payload['transactions'][1]['credit'] = 190;
        $this->putJson(route('documents.update', $document), $payload)->assertRedirect(route('documents.index'));
        $this->assertNull($document->fresh()->approved_at);
        $this->assertSame('-10.00', $document->transactions()->sum('value'));
        $this->postJson(route('documents.change-status', $document))->assertUnprocessable()->assertJsonValidationErrors('transactions');
        $this->assertNull($document->fresh()->approved_at);
    }
}
