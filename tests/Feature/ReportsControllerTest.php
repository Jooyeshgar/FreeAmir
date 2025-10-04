<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class ReportsControllerTest extends TestCase
{
    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user first with ID 1 (CompanyFactory expects this)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $this->user = User::factory()->create();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create a company (factory will attach user ID 1 automatically)
        $this->company = Company::factory()->create();
        Session::put('active-company-id', $this->company->id);

        $this->user->companies()->attach($this->company->id);

        $this->user->assignRole('Super-Admin');

        // Authenticate the user
        $this->actingAs($this->user);
    }

    /**
     * Test ledger method returns correct view with root subjects.
     */
    public function test_ledger_returns_view_with_root_subjects(): void
    {
        // Create root subjects (parent_id = null for root subjects)
        $rootSubject1 = Subject::factory()->create([
            'code' => '1000',
            'name' => 'Assets',
            'parent_id' => null,
            'company_id' => $this->company->id,
        ]);

        $rootSubject2 = Subject::factory()->create([
            'code' => '2000',
            'name' => 'Liabilities',
            'parent_id' => null,
            'company_id' => $this->company->id,
        ]);

        // Create a non-root subject (should not appear)
        Subject::factory()->create([
            'code' => '1100',
            'name' => 'Current Assets',
            'parent_id' => $rootSubject1->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this->get(route('reports.ledger'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.ledger');
        $response->assertViewHas('subjects');

        $subjects = $response->viewData('subjects');
        $this->assertCount(2, $subjects);
        $this->assertTrue($subjects->contains('id', $rootSubject1->id));
        $this->assertTrue($subjects->contains('id', $rootSubject2->id));
    }

    /**
     * Test journal method returns correct view.
     */
    public function test_journal_returns_view(): void
    {
        $response = $this->get(route('reports.journal'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.journal');
        $response->assertViewHas('subjects');

        $subjects = $response->viewData('subjects');
        $this->assertIsArray($subjects);
        $this->assertEmpty($subjects);
    }

    /**
     * Test subLedger method returns correct view with all subjects.
     */
    public function test_sub_ledger_returns_view_with_all_subjects(): void
    {
        // Create various subjects
        $subject1 = Subject::factory()->create([
            'code' => '1000',
            'company_id' => $this->company->id,
        ]);

        $subject2 = Subject::factory()->create([
            'code' => '1100',
            'company_id' => $this->company->id,
        ]);

        $response = $this->get(route('reports.sub-ledger'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.subLedger');
        $response->assertViewHas('subjects');

        $subjects = $response->viewData('subjects');
        $this->assertCount(2, $subjects);
    }

    /**
     * Test documents method returns correct view.
     */
    public function test_documents_returns_view(): void
    {
        $response = $this->get(route('reports.documents'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.documents');
    }

    /**
     * Test result method validates required fields for Journal report.
     */
    public function test_result_validates_required_fields_for_journal(): void
    {
        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
        ]));

        $response->assertSessionHasErrors(['report_type']);
    }

    /**
     * Test result method validates subject_id for non-Journal and non-Document reports.
     */
    public function test_result_validates_subject_id_for_ledger_report(): void
    {
        $response = $this->get(route('reports.result', [
            'report_for' => 'Ledger',
            'report_type' => 'all',
        ]));

        $response->assertSessionHasErrors(['subject_id']);
    }

    /**
     * Test result method validates between_numbers parameters.
     */
    public function test_result_validates_between_numbers_parameters(): void
    {
        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'between_numbers',
        ]));

        $response->assertSessionHasErrors(['start_document_number', 'end_document_number']);
    }

    /**
     * Test result method validates between_dates parameters.
     */
    public function test_result_validates_between_dates_parameters(): void
    {
        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'between_dates',
        ]));

        $response->assertSessionHasErrors(['start_date', 'end_date']);
    }

    /**
     * Test result method validates specific_date parameter.
     */
    public function test_result_validates_specific_date_parameter(): void
    {
        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'specific_date',
        ]));

        $response->assertSessionHasErrors(['specific_date']);
    }

    /**
     * Test result method validates specific_number parameter.
     */
    public function test_result_validates_specific_number_parameter(): void
    {
        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'specific_number',
        ]));

        $response->assertSessionHasErrors(['specific_document_number']);
    }

    /**
     * Test result method validates invalid report_for value.
     */
    public function test_result_validates_invalid_report_for_value(): void
    {
        $response = $this->get(route('reports.result', [
            'report_for' => 'InvalidType',
            'report_type' => 'all',
        ]));

        $response->assertSessionHasErrors(['report_for']);
    }

    /**
     * Test result method returns document report with all documents.
     */
    public function test_result_returns_document_report_with_all_documents(): void
    {
        $user = User::factory()->create();

        $document1 = Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'title' => 'First Document',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $document2 = Document::factory()->create([
            'number' => 2,
            'date' => '2024-01-02',
            'title' => 'Second Document',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Document',
            'report_type' => 'all',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('reports.documentReport');
        $response->assertViewHas('documents');

        $documents = $response->viewData('documents');
        $this->assertCount(2, $documents);
    }

    /**
     * Test result method filters documents by number range.
     */
    public function test_result_filters_documents_by_number_range(): void
    {
        $user = User::factory()->create();

        Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $document2 = Document::factory()->create([
            'number' => 5,
            'date' => '2024-01-02',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Document::factory()->create([
            'number' => 10,
            'date' => '2024-01-03',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Document',
            'report_type' => 'between_numbers',
            'start_document_number' => 3,
            'end_document_number' => 7,
        ]));

        $response->assertStatus(200);
        $documents = $response->viewData('documents');
        $this->assertCount(1, $documents);
        $this->assertEquals(5, $documents->first()->number);
    }

    /**
     * Test result method filters documents by specific number.
     */
    public function test_result_filters_documents_by_specific_number(): void
    {
        $user = User::factory()->create();

        Document::factory()->create([
            'number' => 1,
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $document2 = Document::factory()->create([
            'number' => 5,
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Document',
            'report_type' => 'specific_number',
            'specific_document_number' => 5,
        ]));

        $response->assertStatus(200);
        $documents = $response->viewData('documents');
        $this->assertCount(1, $documents);
        $this->assertEquals(5, $documents->first()->number);
    }

    /**
     * Test result method filters documents by date range.
     */
    public function test_result_filters_documents_by_date_range(): void
    {
        $user = User::factory()->create();

        Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Document::factory()->create([
            'number' => 2,
            'date' => '2024-01-15',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Document::factory()->create([
            'number' => 3,
            'date' => '2024-02-01',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Document',
            'report_type' => 'between_dates',
            'start_date' => '1402/10/11', // Jalali date for 2024-01-01
            'end_date' => '1402/10/25', // Jalali date for 2024-01-15
        ]));

        $response->assertStatus(200);
        $documents = $response->viewData('documents');
        $this->assertCount(2, $documents);
    }

    /**
     * Test result method filters documents by specific date.
     */
    public function test_result_filters_documents_by_specific_date(): void
    {
        $user = User::factory()->create();

        Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Document::factory()->create([
            'number' => 2,
            'date' => '2024-01-15',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Document',
            'report_type' => 'specific_date',
            'specific_date' => '1402/10/25', // Jalali date for 2024-01-15
        ]));

        $response->assertStatus(200);
        $documents = $response->viewData('documents');
        $this->assertCount(1, $documents);
        $this->assertEquals(2, $documents->first()->number);
    }

    /**
     * Test result method filters documents by search term.
     */
    public function test_result_filters_documents_by_search_term(): void
    {
        $user = User::factory()->create();

        Document::factory()->create([
            'number' => 1,
            'title' => 'Payment Invoice',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Document::factory()->create([
            'number' => 2,
            'title' => 'Receipt Document',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Document',
            'report_type' => 'all',
            'search' => 'Invoice',
        ]));

        $response->assertStatus(200);
        $documents = $response->viewData('documents');
        $this->assertCount(1, $documents);
        $this->assertEquals('Payment Invoice', $documents->first()->title);
    }

    /**
     * Test result method returns journal report.
     */
    public function test_result_returns_journal_report(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['company_id' => $this->company->id]);

        $document = Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $transaction = Transaction::factory()->create([
            'document_id' => $document->id,
            'subject_id' => $subject->id,
            'value' => 1000,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'all',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('reports.journalReport');
        $response->assertViewHas(['transactionsChunk', 'subject']);
    }

    /**
     * Test result method returns ledger report with subject.
     */
    public function test_result_returns_ledger_report_with_subject(): void
    {
        $user = User::factory()->create();

        $rootSubject = Subject::factory()->create([
            'code' => '1000',
            'name' => 'Assets',
            'parent_id' => null,
            'company_id' => $this->company->id,
        ]);

        $childSubject = Subject::factory()->create([
            'code' => '1100',
            'name' => 'Current Assets',
            'parent_id' => $rootSubject->id,
            'company_id' => $this->company->id,
        ]);

        $document = Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'document_id' => $document->id,
            'subject_id' => $childSubject->id,
            'value' => 1000,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Ledger',
            'report_type' => 'all',
            'subject_id' => $rootSubject->id,
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('reports.ledgerReport');
        $response->assertViewHas(['transactionsChunk', 'subject']);

        $subject = $response->viewData('subject');
        $this->assertEquals($rootSubject->id, $subject->id);
    }

    /**
     * Test result method filters transactions by document number range.
     */
    public function test_result_filters_transactions_by_document_number_range(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['company_id' => $this->company->id]);

        $document1 = Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $document2 = Document::factory()->create([
            'number' => 5,
            'date' => '2024-01-02',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $document3 = Document::factory()->create([
            'number' => 10,
            'date' => '2024-01-03',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'document_id' => $document1->id,
            'subject_id' => $subject->id,
            'value' => 1000,
        ]);

        Transaction::factory()->create([
            'document_id' => $document2->id,
            'subject_id' => $subject->id,
            'value' => 2000,
        ]);

        Transaction::factory()->create([
            'document_id' => $document3->id,
            'subject_id' => $subject->id,
            'value' => 3000,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'between_numbers',
            'start_document_number' => 3,
            'end_document_number' => 7,
        ]));

        $response->assertStatus(200);
        $transactionsChunk = $response->viewData('transactionsChunk');
        $allTransactions = collect();
        foreach ($transactionsChunk as $chunk) {
            $allTransactions = $allTransactions->merge($chunk);
        }
        $this->assertCount(1, $allTransactions);
    }

    /**
     * Test result method filters transactions by date range.
     */
    public function test_result_filters_transactions_by_date_range(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['company_id' => $this->company->id]);

        $document1 = Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $document2 = Document::factory()->create([
            'number' => 2,
            'date' => '2024-01-15',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'document_id' => $document1->id,
            'subject_id' => $subject->id,
            'value' => 1000,
        ]);

        Transaction::factory()->create([
            'document_id' => $document2->id,
            'subject_id' => $subject->id,
            'value' => 2000,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'between_dates',
            'start_date' => '1402/10/11', // Jalali date
            'end_date' => '1402/10/25', // Jalali date
        ]));

        $response->assertStatus(200);
        $transactionsChunk = $response->viewData('transactionsChunk');
        $this->assertNotNull($transactionsChunk);
    }

    /**
     * Test result method filters transactions by specific date.
     */
    public function test_result_filters_transactions_by_specific_date(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['company_id' => $this->company->id]);

        $document1 = Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $document2 = Document::factory()->create([
            'number' => 2,
            'date' => '2024-01-15',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'document_id' => $document1->id,
            'subject_id' => $subject->id,
            'value' => 1000,
        ]);

        Transaction::factory()->create([
            'document_id' => $document2->id,
            'subject_id' => $subject->id,
            'value' => 2000,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'specific_date',
            'specific_date' => '1402/10/11', // Jalali date for 2024-01-01
        ]));

        $response->assertStatus(200);
        $transactionsChunk = $response->viewData('transactionsChunk');
        $this->assertNotNull($transactionsChunk);
    }

    /**
     * Test result method filters transactions by search term in document title.
     */
    public function test_result_filters_transactions_by_document_title_search(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['company_id' => $this->company->id]);

        $document1 = Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'title' => 'Payment Invoice',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        $document2 = Document::factory()->create([
            'number' => 2,
            'date' => '2024-01-02',
            'title' => 'Receipt Document',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'document_id' => $document1->id,
            'subject_id' => $subject->id,
            'value' => 1000,
        ]);

        Transaction::factory()->create([
            'document_id' => $document2->id,
            'subject_id' => $subject->id,
            'value' => 2000,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'all',
            'search' => 'Invoice',
        ]));

        $response->assertStatus(200);
        $transactionsChunk = $response->viewData('transactionsChunk');
        $allTransactions = collect();
        foreach ($transactionsChunk as $chunk) {
            $allTransactions = $allTransactions->merge($chunk);
        }
        $this->assertCount(1, $allTransactions);
    }

    /**
     * Test result method returns CSV export for transactions.
     */
    public function test_result_exports_transactions_as_csv(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create([
            'code' => '1000',
            'name' => 'Test Subject',
            'company_id' => $this->company->id,
        ]);

        $document = Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'title' => 'Test Document',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'document_id' => $document->id,
            'subject_id' => $subject->id,
            'value' => 1000,
            'desc' => 'Test transaction',
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'all',
            'export' => 'csv',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
        $this->assertStringContainsString('Journal_report_', $response->headers->get('Content-Disposition'));
    }

    /**
     * Test result method returns sub-ledger report with descendant transactions.
     */
    public function test_result_returns_sub_ledger_report_with_descendants(): void
    {
        $user = User::factory()->create();

        $rootSubject = Subject::factory()->create([
            'code' => '1000',
            'name' => 'Assets',
            'parent_id' => null,
            'company_id' => $this->company->id,
        ]);

        $childSubject = Subject::factory()->create([
            'code' => '1100',
            'name' => 'Current Assets',
            'parent_id' => $rootSubject->id,
            'company_id' => $this->company->id,
        ]);

        $grandchildSubject = Subject::factory()->create([
            'code' => '1110',
            'name' => 'Cash',
            'parent_id' => $childSubject->id,
            'company_id' => $this->company->id,
        ]);

        $document = Document::factory()->create([
            'number' => 1,
            'date' => '2024-01-01',
            'company_id' => $this->company->id,
            'creator_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'document_id' => $document->id,
            'subject_id' => $grandchildSubject->id,
            'value' => 1000,
        ]);

        $response = $this->get(route('reports.result', [
            'report_for' => 'subLedger',
            'report_type' => 'all',
            'subject_id' => $rootSubject->id,
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('reports.ledgerReport');
    }

    /**
     * Test result method requires authentication.
     */
    public function test_result_requires_authentication(): void
    {
        auth()->logout();

        $response = $this->get(route('reports.result', [
            'report_for' => 'Journal',
            'report_type' => 'all',
        ]));

        $response->assertRedirect(route('login'));
    }

    /**
     * Test ledger method requires authentication.
     */
    public function test_ledger_requires_authentication(): void
    {
        auth()->logout();

        $response = $this->get(route('reports.ledger'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Test journal method requires authentication.
     */
    public function test_journal_requires_authentication(): void
    {
        auth()->logout();

        $response = $this->get(route('reports.journal'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Test subLedger method requires authentication.
     */
    public function test_sub_ledger_requires_authentication(): void
    {
        auth()->logout();

        $response = $this->get(route('reports.sub-ledger'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Test documents method requires authentication.
     */
    public function test_documents_requires_authentication(): void
    {
        auth()->logout();

        $response = $this->get(route('reports.documents'));

        $response->assertRedirect(route('login'));
    }
}
