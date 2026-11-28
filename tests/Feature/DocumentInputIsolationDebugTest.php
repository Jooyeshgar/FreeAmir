<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentInputIsolationDebugTest extends TestCase
{
    use RefreshDatabase;

    public static function malformedRows(): array
    {
        return [
            'rows must be an array' => ['invalid', 'transactions'],
            'row must be an array' => [['invalid'], 'transactions.0'],
            'missing description' => [[['debit' => 100, 'credit' => 0]], 'transactions.0.desc'],
        ];
    }

    #[DataProvider('malformedRows')]
    public function test_malformed_manual_document_returns_validation_errors_without_writing($rows, string $field): void
    {
        $this->authorizeLocalYear();
        $this->postJson(route('documents.store'), [
            'date' => '2026/04/10', 'title' => 'آزمون ورودی ناقص', 'transactions' => $rows,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_balanced_manual_document_accepts_current_year_subjects_without_optional_row_id(): void
    {
        $this->authorizeLocalYear();
        $expense = Subject::create(['parent_id' => null, 'code' => '040', 'name' => 'هزینه پست', 'type' => SubjectType::DEBTOR, 'is_permanent' => false]);
        $cash = Subject::create(['parent_id' => null, 'code' => '011', 'name' => 'صندوق', 'type' => SubjectType::BOTH, 'is_permanent' => true]);
        $this->postJson(route('documents.store'), [
            'date' => '2026/04/10', 'title' => 'هزینه پست فرضی',
            'transactions' => [
                ['subject_id' => $expense->id, 'debit' => '۱۰۰٫۲۵', 'credit' => '۰', 'desc' => 'هزینه پست'],
                ['subject_id' => $cash->id, 'debit' => '۰', 'credit' => '۱۰۰٫۲۵', 'desc' => 'پرداخت صندوق'],
            ],
        ])->assertRedirect(route('documents.index'));
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', ['subject_id' => $expense->id, 'value' => '-100.25']);
        $this->assertDatabaseHas('transactions', ['subject_id' => $cash->id, 'value' => '100.25']);
    }

    public function test_empty_draft_keeps_the_existing_unapproved_behavior(): void
    {
        $this->authorizeLocalYear();
        $this->postJson(route('documents.store'), [
            'date' => '2026/04/10', 'title' => 'پیش‌نویس خالی',
        ])->assertRedirect(route('documents.index'));
        $this->assertDatabaseHas('documents', ['title' => 'پیش‌نویس خالی', 'approved_at' => null]);
        $this->assertDatabaseCount('transactions', 0);
    }

    private function authorizeLocalYear(): void
    {
        app()->setLocale('en');
        $year = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $year->id]);
        $user = User::factory()->create(['name' => 'رضا محمدی']);
        $year->users()->attach($user);
        $user->givePermissionTo(Permission::findOrCreate('documents.*'));
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => (string) $year->id]);
    }

    private function editableDocument(): array
    {
        $expense = Subject::create(['parent_id' => null, 'code' => '040', 'name' => 'هزینه پست', 'type' => SubjectType::DEBTOR, 'is_permanent' => false]);
        $cash = Subject::create(['parent_id' => null, 'code' => '011', 'name' => 'صندوق', 'type' => SubjectType::BOTH, 'is_permanent' => true]);
        $document = Document::factory()->create(['date' => '2026-04-10']);
        $debit = Transaction::factory()->create(['document_id' => $document->id, 'subject_id' => $expense->id, 'value' => -100]);
        $credit = Transaction::factory()->create(['document_id' => $document->id, 'subject_id' => $cash->id, 'value' => 100]);

        return [$document, [
            'document_id' => $document->id, 'number' => $document->number, 'date' => '2026/04/10', 'title' => 'ویرایش هزینه فرضی',
            'transactions' => [
                ['subject_id' => $expense->id, 'transaction_id' => $debit->id, 'debit' => 100, 'credit' => 0, 'desc' => 'هزینه پست'],
                ['subject_id' => $cash->id, 'transaction_id' => $credit->id, 'debit' => 0, 'credit' => 100, 'desc' => 'پرداخت صندوق'],
            ],
        ]];
    }

    public function test_manual_update_cannot_take_a_row_from_another_document(): void
    {
        $this->authorizeLocalYear();
        [$document, $payload] = $this->editableDocument();
        $other = Document::factory()->create();
        $foreignRow = Transaction::factory()->create(['document_id' => $other->id, 'subject_id' => $payload['transactions'][0]['subject_id'], 'value' => -500]);
        $before = $foreignRow->fresh()->getAttributes();
        $payload['transactions'][0]['transaction_id'] = $foreignRow->id;
        $this->putJson(route('documents.update', $document), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('transactions.0.transaction_id');
        $this->assertSame($before, $foreignRow->fresh()->getAttributes());
        $this->assertSame(2, $document->transactions()->count());
    }

    public function test_manual_update_only_changes_editable_header_fields(): void
    {
        $this->authorizeLocalYear();
        [$document, $payload] = $this->editableDocument();
        $before = $document->fresh()->getAttributes();
        $payload['fiscal_year_id'] = FiscalYear::factory()->create()->id;
        $payload['approved_at'] = '2026-04-10';
        $payload['approver_id'] = auth()->id();
        $payload['creator_id'] = User::factory()->create()->id;
        $payload['documentable_type'] = 'App\\Models\\Invoice';
        $payload['documentable_id'] = 999;
        $this->putJson(route('documents.update', $document), $payload)->assertRedirect(route('documents.index'));
        $actual = Document::withoutGlobalScopes()->findOrFail($document->id);
        foreach (['fiscal_year_id', 'creator_id', 'approved_at', 'approver_id', 'documentable_type', 'documentable_id'] as $field) {
            $this->assertSame($before[$field], $actual->getAttributes()[$field], $field);
        }
        $this->assertSame($payload['title'], $actual->title);
        $this->assertSame(2, $actual->transactions()->count());
    }

    public function test_editor_with_update_permission_can_save_without_create_permission(): void
    {
        $this->authorizeLocalYear();
        [$document, $payload] = $this->editableDocument();
        auth()->user()->syncPermissions(Permission::findOrCreate('documents.update'));
        $this->putJson(route('documents.update', $document), $payload)->assertRedirect(route('documents.index'));
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'title' => $payload['title']]);
    }

    public function test_update_cannot_bypass_number_uniqueness_with_a_forged_hidden_document_id(): void
    {
        $this->authorizeLocalYear();
        [$document, $payload] = $this->editableDocument();
        $other = Document::factory()->create();
        $payload['document_id'] = $other->id;
        $payload['number'] = $other->number;
        $this->putJson(route('documents.update', $document), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('number');
        $this->assertNotSame((float) $other->number, (float) $document->fresh()->number);
    }

    public function test_manual_update_cannot_change_an_automatically_linked_document(): void
    {
        $this->authorizeLocalYear();
        [$document, $payload] = $this->editableDocument();
        $document->update(['documentable_type' => 'App\\Models\\Invoice', 'documentable_id' => 999]);
        $before = $document->fresh()->getAttributes();
        $this->putJson(route('documents.update', $document), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->assertSame($before, $document->fresh()->getAttributes());
        $this->assertSame('-100.00', $document->transactions()->orderBy('id')->first()->value);
    }

    public static function invalidAmounts(): array
    {
        return [['not-money'], [['invalid']], ['-1'], ['۱۰۰.۱۲۳']];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amount_is_rejected_instead_of_being_silently_changed($amount): void
    {
        $this->authorizeLocalYear();
        [$document, $payload] = $this->editableDocument();
        $payload['transactions'][0]['debit'] = $amount;
        $this->putJson(route('documents.update', $document), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('transactions.0.debit');
        $this->assertSame('-100.00', $document->transactions()->orderBy('id')->first()->value);
    }

    public static function invalidDates(): array
    {
        return [['2026/13/01'], [['2026/04/10']]];
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_date_returns_validation_error($date): void
    {
        $this->authorizeLocalYear();
        $this->postJson(route('documents.store'), ['date' => $date, 'transactions' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_manual_document_rejects_a_subject_outside_the_active_company_year(): void
    {
        app()->setLocale('en');
        $year = FiscalYear::factory()->create(['year' => 1405]);
        $otherYear = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $year->id]);
        $user = User::factory()->create(['name' => 'رضا محمدی']);
        $year->users()->attach($user);
        $user->givePermissionTo(Permission::findOrCreate('documents.*'));
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => (string) $year->id]);
        $cash = Subject::create(['code' => '011', 'name' => 'صندوق', 'parent_id' => null, 'type' => SubjectType::BOTH, 'is_permanent' => true]);
        $foreign = Subject::create(['fiscal_year_id' => $otherYear->id, 'code' => '040', 'name' => 'هزینه شرکت دیگر', 'parent_id' => null, 'type' => SubjectType::DEBTOR, 'is_permanent' => false]);
        $this->postJson(route('documents.store'), [
            'number' => 1, 'date' => '2026/04/10', 'title' => 'آزمون جداسازی داده',
            'transactions' => [
                ['subject_id' => $foreign->id, 'debit' => 100, 'credit' => 0, 'desc' => 'هزینه', 'transaction_id' => null],
                ['subject_id' => $cash->id, 'debit' => 0, 'credit' => 100, 'desc' => 'صندوق', 'transaction_id' => null],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('transactions.0.subject_id');
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('transactions', 0);
    }
}
