<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SubjectTransferTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private SubjectService $subjectService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create(['fiscal_year' => 1403]);
        $this->user->companies()->attach([$this->company->id]);
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'subjects.transfer'])
        );

        $this->actingAs($this->user);
        config(['active-company-id' => $this->company->id, 'active-company-fiscal-year' => $this->company->fiscal_year]);
        $this->subjectService = app(SubjectService::class);
    }

    private function makeSubject(array $attributes = []): Subject
    {
        return Subject::withoutGlobalScopes()->create(array_merge([
            'company_id' => $this->company->id,
            'parent_id' => null,
            'is_permanent' => true,
            'name' => 'Test Subject',
            'code' => '001',
            'type' => 'both',
        ], $attributes));
    }

    private function makeDocument(array $attributes = []): Document
    {
        return Document::withoutGlobalScopes()->create(array_merge([
            'company_id' => $this->company->id,
            'number' => Document::withoutGlobalScopes()->max('number') + 1,
            'date' => '2024-03-21',
            'creator_id' => $this->user->id,
            'title' => 'Test Document',
        ], $attributes));
    }

    private function makeTransaction(Subject $subject, Document $document, float $value): Transaction
    {
        return Transaction::withoutGlobalScopes()->create([
            'subject_id' => $subject->id,
            'document_id' => $document->id,
            'user_id' => $this->user->id,
            'value' => $value,
            'desc' => 'Test transaction',
        ]);
    }

    private function makeBank(array $attributes = []): Bank
    {
        $bank = new Bank;
        $bank->name = $attributes['name'] ?? 'Test Bank';
        $bank->company_id = $attributes['company_id'] ?? $this->company->id;
        $bank->save();

        return $bank;
    }

    private function makeCustomer(Subject $subject, array $attributes = []): Customer
    {
        return Customer::withoutGlobalScopes()->create(array_merge([
            'name' => 'Test Customer',
            'company_id' => $this->company->id,
            'subject_id' => $subject->id,
        ], $attributes));
    }

    private function makeCustomerGroup(Subject $subject, array $attributes = []): CustomerGroup
    {
        return CustomerGroup::withoutGlobalScopes()->create(array_merge([
            'name' => 'Test Group',
            'company_id' => $this->company->id,
            'subject_id' => $subject->id,
        ], $attributes));
    }

    private function makeBankAccount(Subject $subject, array $attributes = []): BankAccount
    {
        $bank = $this->makeBank();

        return BankAccount::withoutGlobalScopes()->create(array_merge([
            'name' => 'Test Account',
            'number' => '123456789',
            'type' => 1,
            'bank_id' => $bank->id,
            'company_id' => $this->company->id,
            'subject_id' => $subject->id,
        ], $attributes));
    }

    private function setSubjectable(Subject $subject, string $type, int $id): void
    {
        $subject->subjectable_type = $type;
        $subject->subjectable_id = $id;
        $subject->save();
    }

    public function test_transfers_transactions_from_source_to_destination()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();

        $this->makeTransaction($source, $doc, 100);
        $this->makeTransaction($source, $doc, 200);

        $result = $this->subjectService->transferSubject($source, $destination);

        $this->assertEquals(2, $result['count']);
        $this->assertEquals(300, $result['sum']);

        $this->assertEquals(0, Transaction::withoutGlobalScopes()->where('subject_id', $source->id)->count());
        $this->assertEquals(2, Transaction::withoutGlobalScopes()->where('subject_id', $destination->id)->count());
    }

    public function test_transfers_subjectable_when_flag_is_true()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->setSubjectable($source, 'App\Models\Customer', 5);

        $this->subjectService->transferSubject($source, $destination, transferSubjectable: true);

        $source->refresh();
        $destination->refresh();

        $this->assertNull($source->subjectable_type);
        $this->assertNull($source->subjectable_id);
        $this->assertEquals('App\Models\Customer', $destination->subjectable_type);
        $this->assertEquals(5, $destination->subjectable_id);
    }

    public function test_does_not_transfer_subjectable_when_flag_is_false()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->setSubjectable($source, 'App\Models\Customer', 5);

        $this->subjectService->transferSubject($source, $destination, transferSubjectable: false);

        $source->refresh();
        $destination->refresh();

        $this->assertEquals('App\Models\Customer', $source->subjectable_type);
        $this->assertEquals(5, $source->subjectable_id);
        $this->assertNull($destination->subjectable_type);
        $this->assertNull($destination->subjectable_id);
    }

    public function test_removes_source_subject_when_flag_is_true()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubject($source, $destination, removeSource: true);

        $this->assertTrue($result['source_removed']);
        $this->assertNull(Subject::withoutGlobalScopes()->find($source->id));
    }

    public function test_does_not_remove_source_when_it_has_children()
    {
        $source = $this->makeSubject(['code' => '001']);
        $this->makeSubject(['code' => '001001', 'parent_id' => $source->id]);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubject($source, $destination, removeSource: true);

        $this->assertFalse($result['source_removed']);
        $this->assertNotNull(Subject::withoutGlobalScopes()->find($source->id));
    }

    public function test_only_transfers_current_fiscal_year_transactions()
    {
        $year = $this->company->fiscal_year;

        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);

        $currentDoc = $this->makeDocument(['date' => jalali_to_gregorian($year, 3, 15, '-')]);
        $prevDoc = $this->makeDocument(['date' => jalali_to_gregorian($year - 1, 12, 15, '-')]);

        $this->makeTransaction($source, $currentDoc, 100);
        $this->makeTransaction($source, $prevDoc, 50);

        $result = $this->subjectService->transferSubject($source, $destination);

        $this->assertEquals(1, $result['count']);
        $this->assertEquals(100, $result['sum']);

        $this->assertEquals(1, Transaction::withoutGlobalScopes()->where('subject_id', $source->id)->count());
        $this->assertEquals(1, Transaction::withoutGlobalScopes()->where('subject_id', $destination->id)->count());
    }

    public function test_throws_when_source_and_destination_are_same()
    {
        $source = $this->makeSubject(['code' => '001']);

        $this->expectException(\InvalidArgumentException::class);
        $this->subjectService->transferSubject($source, $source);
    }

    public function test_creates_new_subject_under_parent_and_transfers()
    {
        $source = $this->makeSubject(['code' => '001', 'name' => 'Source Name', 'type' => 'debtor']);
        $parent = $this->makeSubject(['code' => '002', 'name' => 'Parent']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 150);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent);

        $this->assertEquals(1, $result['count']);
        $this->assertEquals(150, $result['sum']);

        $newSubject = $result['destination'];
        $this->assertEquals($parent->id, $newSubject->parent_id);
        $this->assertEquals('Source Name', $newSubject->name);
        $this->assertEquals($source->company_id, $newSubject->company_id);

        $this->assertEquals(0, Transaction::withoutGlobalScopes()->where('subject_id', $source->id)->count());
        $this->assertEquals(1, Transaction::withoutGlobalScopes()->where('subject_id', $newSubject->id)->count());
    }

    public function test_creates_new_subject_with_subjectable_when_flag_is_true()
    {
        $source = $this->makeSubject(['code' => '001']);
        $parent = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->setSubjectable($source, 'App\Models\Customer', 10);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent, transferSubjectable: true);

        $newSubject = $result['destination'];
        $source->refresh();

        $this->assertEquals('App\Models\Customer', $newSubject->subjectable_type);
        $this->assertEquals(10, $newSubject->subjectable_id);
        $this->assertNull($source->subjectable_type);
        $this->assertNull($source->subjectable_id);
    }

    public function test_creates_new_subject_without_subjectable_when_flag_is_false()
    {
        $source = $this->makeSubject(['code' => '001']);
        $parent = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->setSubjectable($source, 'App\Models\Customer', 10);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent, transferSubjectable: false);

        $newSubject = $result['destination'];
        $source->refresh();

        $this->assertNull($newSubject->subjectable_type);
        $this->assertNull($newSubject->subjectable_id);
        $this->assertEquals('App\Models\Customer', $source->subjectable_type);
        $this->assertEquals(10, $source->subjectable_id);
    }

    public function test_removes_source_when_flag_is_true_in_new_under_parent()
    {
        $source = $this->makeSubject(['code' => '001']);
        $parent = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent, removeSource: true);

        $this->assertTrue($result['source_removed']);
        $this->assertNull(Subject::withoutGlobalScopes()->find($source->id));
    }

    public function test_throws_when_source_and_parent_are_same()
    {
        $source = $this->makeSubject(['code' => '001']);

        $this->expectException(\InvalidArgumentException::class);
        $this->subjectService->transferSubjectToNewUnderParent($source, $source);
    }

    public function test_throws_when_parent_is_descendant_of_source()
    {
        $source = $this->makeSubject(['code' => '001']);
        $child = $this->makeSubject(['code' => '001001', 'parent_id' => $source->id]);
        $grandchild = $this->makeSubject(['code' => '001001001', 'parent_id' => $child->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->subjectService->transferSubjectToNewUnderParent($source, $grandchild);
    }

    public function test_code_is_regenerated_when_transferring_to_new_parent()
    {
        $parent = $this->makeSubject(['code' => '002', 'name' => 'Parent']);
        $source = $this->makeSubject(['code' => '003', 'name' => 'Source']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent);

        $newSubject = $result['destination'];
        $this->assertEquals('002001', $newSubject->code);
        $this->assertEquals($parent->id, $newSubject->parent_id);
        $this->assertEquals($source->name, $newSubject->name);
    }

    public function test_code_is_generated_correctly_when_parent_already_has_children()
    {
        $parent = $this->makeSubject(['code' => '002', 'name' => 'Parent']);
        $this->makeSubject(['code' => '002001', 'name' => 'Child 1', 'parent_id' => $parent->id]);
        $source = $this->makeSubject(['code' => '003', 'name' => 'Source']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent);

        $newSubject = $result['destination'];
        $this->assertEquals('002002', $newSubject->code);
        $this->assertEquals($parent->id, $newSubject->parent_id);
    }

    public function test_code_is_generated_correctly_at_grandchild_level()
    {
        $grandparent = $this->makeSubject(['code' => '002', 'name' => 'GP']);
        $parent = $this->makeSubject(['code' => '002001', 'name' => 'Parent', 'parent_id' => $grandparent->id]);
        $source = $this->makeSubject(['code' => '003', 'name' => 'Source']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent);

        $newSubject = $result['destination'];
        $this->assertEquals('002001001', $newSubject->code);
        $this->assertEquals($parent->id, $newSubject->parent_id);
    }

    public function test_code_is_generated_correctly_when_transferring_child_to_another_parent()
    {
        $parentA = $this->makeSubject(['code' => '002', 'name' => 'Parent A']);
        $parentB = $this->makeSubject(['code' => '004', 'name' => 'Parent B']);
        $source = $this->makeSubject(['code' => '002001', 'name' => 'Source', 'parent_id' => $parentA->id]);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parentB);

        $newSubject = $result['destination'];
        $this->assertEquals('004001', $newSubject->code);
        $this->assertEquals($parentB->id, $newSubject->parent_id);
    }

    public function test_sum_of_source_transactions_is_correctly_reported_on_transfer()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();

        $this->makeTransaction($source, $doc, 100.50);
        $this->makeTransaction($source, $doc, 200.75);
        $this->makeTransaction($source, $doc, 50.25);

        $result = $this->subjectService->transferSubject($source, $destination);

        $this->assertEquals(3, $result['count']);
        $this->assertEquals(351.50, $result['sum']);
        $this->assertEquals(351.50, Transaction::withoutGlobalScopes()->where('subject_id', $destination->id)->sum('value'));
        $this->assertEquals(0, Transaction::withoutGlobalScopes()->where('subject_id', $source->id)->sum('value'));
    }

    public function test_source_subject_not_deleted_when_it_has_subjectable_not_transferred()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $customer = $this->makeCustomer($source);
        $this->setSubjectable($source, 'App\Models\Customer', $customer->id);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubject($source, $destination, transferSubjectable: false, removeSource: true);

        $this->assertFalse($result['source_removed']);
        $this->assertNotNull(Subject::withoutGlobalScopes()->find($source->id));
        $source->refresh();
        $this->assertEquals('App\Models\Customer', $source->subjectable_type);
    }

    public function test_source_subject_deleted_when_subjectable_is_transferred_and_remove_checked()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->setSubjectable($source, 'App\Models\Customer', 5);

        $result = $this->subjectService->transferSubject($source, $destination, transferSubjectable: true, removeSource: true);

        $this->assertTrue($result['source_removed']);
        $this->assertNull(Subject::withoutGlobalScopes()->find($source->id));
        $destination->refresh();
        $this->assertEquals('App\Models\Customer', $destination->subjectable_type);
        $this->assertEquals(5, $destination->subjectable_id);
    }

    public function test_checkbox_combination_both_false()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);
        $this->setSubjectable($source, 'App\Models\Customer', 5);

        $result = $this->subjectService->transferSubject($source, $destination, transferSubjectable: false, removeSource: false);

        $source->refresh();
        $destination->refresh();

        $this->assertEquals('App\Models\Customer', $source->subjectable_type);
        $this->assertEquals(5, $source->subjectable_id);
        $this->assertNull($destination->subjectable_type);
        $this->assertNull($destination->subjectable_id);
        $this->assertArrayNotHasKey('source_removed', $result);
        $this->assertEquals(1, $result['count']);
    }

    public function test_checkbox_combination_only_transfer_subjectable()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);
        $this->setSubjectable($source, 'App\Models\Customer', 5);

        $result = $this->subjectService->transferSubject($source, $destination, transferSubjectable: true, removeSource: false);

        $source->refresh();
        $destination->refresh();

        $this->assertNull($source->subjectable_type);
        $this->assertNull($source->subjectable_id);
        $this->assertEquals('App\Models\Customer', $destination->subjectable_type);
        $this->assertEquals(5, $destination->subjectable_id);
        $this->assertArrayNotHasKey('source_removed', $result);
    }

    public function test_checkbox_combination_only_remove_source()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $customer = $this->makeCustomer($source);
        $this->setSubjectable($source, 'App\Models\Customer', $customer->id);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubject($source, $destination, transferSubjectable: false, removeSource: true);

        $this->assertFalse($result['source_removed']);
        $this->assertNotNull(Subject::withoutGlobalScopes()->find($source->id));
        $source->refresh();
        $this->assertEquals('App\Models\Customer', $source->subjectable_type);
        $this->assertEquals($customer->id, $source->subjectable_id);
    }

    public function test_checkbox_combination_both_true()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);
        $this->setSubjectable($source, 'App\Models\Customer', 5);

        $result = $this->subjectService->transferSubject($source, $destination, transferSubjectable: true, removeSource: true);

        $this->assertTrue($result['source_removed']);
        $this->assertNull(Subject::withoutGlobalScopes()->find($source->id));
        $destination->refresh();
        $this->assertEquals('App\Models\Customer', $destination->subjectable_type);
        $this->assertEquals(5, $destination->subjectable_id);
    }

    public function test_checkbox_combination_new_under_parent_both_false()
    {
        $source = $this->makeSubject(['code' => '001', 'name' => 'Src']);
        $parent = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);
        $this->setSubjectable($source, 'App\Models\Customer', 7);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent, transferSubjectable: false, removeSource: false);

        $newSubject = $result['destination'];
        $source->refresh();

        $this->assertNull($newSubject->subjectable_type);
        $this->assertNull($newSubject->subjectable_id);
        $this->assertEquals('App\Models\Customer', $source->subjectable_type);
        $this->assertEquals(7, $source->subjectable_id);
    }

    public function test_checkbox_combination_new_under_parent_both_true()
    {
        $source = $this->makeSubject(['code' => '001', 'name' => 'Src']);
        $parent = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);
        $this->setSubjectable($source, 'App\Models\Customer', 7);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent, transferSubjectable: true, removeSource: true);

        $newSubject = $result['destination'];
        $this->assertTrue($result['source_removed']);
        $this->assertNull(Subject::withoutGlobalScopes()->find($source->id));
        $this->assertEquals('App\Models\Customer', $newSubject->subjectable_type);
        $this->assertEquals(7, $newSubject->subjectable_id);
    }

    public function test_subjectable_is_removed_from_source_when_transferred()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);
        $this->setSubjectable($source, 'App\Models\BankAccount', 42);

        $this->subjectService->transferSubject($source, $destination, transferSubjectable: true);

        $source->refresh();
        $this->assertNull($source->subjectable_type);
        $this->assertNull($source->subjectable_id);
    }

    public function test_subjectable_stays_on_source_when_not_transferred()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);
        $this->setSubjectable($source, 'App\Models\BankAccount', 42);

        $this->subjectService->transferSubject($source, $destination, transferSubjectable: false);

        $source->refresh();
        $this->assertEquals('App\Models\BankAccount', $source->subjectable_type);
        $this->assertEquals(42, $source->subjectable_id);
    }

    public function test_subjectable_is_unset_on_source_when_deleted_after_transfer()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);
        $this->setSubjectable($source, 'App\Models\Customer', 5);

        $this->subjectService->transferSubject($source, $destination, transferSubjectable: true, removeSource: true);

        $this->assertNull(Subject::withoutGlobalScopes()->find($source->id));
        $destination->refresh();
        $this->assertEquals('App\Models\Customer', $destination->subjectable_type);
        $this->assertEquals(5, $destination->subjectable_id);
    }

    public function test_transfer_with_no_transactions_returns_zero_count_and_sum()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);

        $result = $this->subjectService->transferSubject($source, $destination);

        $this->assertEquals(0, $result['count']);
        $this->assertEquals(0, $result['sum']);
    }

    public function test_transfer_preserves_document_integrity()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc1 = $this->makeDocument(['title' => 'Doc 1']);
        $doc2 = $this->makeDocument(['title' => 'Doc 2']);
        $this->makeTransaction($source, $doc1, 100);
        $this->makeTransaction($source, $doc2, 200);

        $this->subjectService->transferSubject($source, $destination);

        $transactions = Transaction::withoutGlobalScopes()->where('subject_id', $destination->id)->get();
        $this->assertCount(2, $transactions);
        $this->assertEquals($doc1->id, $transactions[0]->document_id);
        $this->assertEquals($doc2->id, $transactions[1]->document_id);
        $this->assertEquals(100, $transactions[0]->value);
        $this->assertEquals(200, $transactions[1]->value);
    }

    public function test_new_subject_inherits_parent_type_when_parent_type_is_restrictive()
    {
        $parent = $this->makeSubject(['code' => '002', 'type' => 'creditor']);
        $source = $this->makeSubject(['code' => '003', 'type' => 'debtor']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent);

        $this->assertEquals('creditor', $result['destination']->type);
    }

    public function test_new_subject_inherits_parent_is_permanent()
    {
        $parent = $this->makeSubject(['code' => '002', 'is_permanent' => false]);
        $source = $this->makeSubject(['code' => '003', 'is_permanent' => true]);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent);

        $this->assertFalse($result['destination']->is_permanent);
    }

    public function test_source_not_removed_when_remove_checkbox_is_unchecked()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->subjectService->transferSubject($source, $destination, removeSource: false);

        $this->assertNotNull(Subject::withoutGlobalScopes()->find($source->id));
        $this->assertEquals(0, Transaction::withoutGlobalScopes()->where('subject_id', $source->id)->count());
    }

    public function test_transfer_subjectable_on_new_under_parent_moves_relation()
    {
        $source = $this->makeSubject(['code' => '001']);
        $parent = $this->makeSubject(['code' => '002']);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);
        $this->setSubjectable($source, 'App\Models\CustomerGroup', 15);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent, transferSubjectable: true);

        $newSubject = $result['destination'];
        $source->refresh();
        $this->assertEquals('App\Models\CustomerGroup', $newSubject->subjectable_type);
        $this->assertEquals(15, $newSubject->subjectable_id);
        $this->assertNull($source->subjectable_type);
        $this->assertNull($source->subjectable_id);
    }

    public function test_transfer_updates_customer_subject_id_to_destination()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $customer = $this->makeCustomer($source);
        $this->setSubjectable($source, 'App\Models\Customer', $customer->id);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->subjectService->transferSubject($source, $destination, transferSubjectable: true);

        $customer->refresh();
        $this->assertEquals($destination->id, $customer->subject_id);
        $this->assertNotEquals($source->id, $customer->subject_id);
    }

    public function test_transfer_does_not_update_customer_subject_id_when_not_transferring_subjectable()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $customer = $this->makeCustomer($source);
        $this->setSubjectable($source, 'App\Models\Customer', $customer->id);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->subjectService->transferSubject($source, $destination, transferSubjectable: false);

        $customer->refresh();
        $this->assertEquals($source->id, $customer->subject_id);
    }

    public function test_transfer_updates_bank_account_subject_id_to_destination()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $bankAccount = $this->makeBankAccount($source);
        $this->setSubjectable($source, 'App\Models\BankAccount', $bankAccount->id);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->subjectService->transferSubject($source, $destination, transferSubjectable: true);

        $bankAccount->refresh();
        $this->assertEquals($destination->id, $bankAccount->subject_id);
    }

    public function test_transfer_updates_customer_group_subject_id_to_destination()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $group = $this->makeCustomerGroup($source);
        $this->setSubjectable($source, 'App\Models\CustomerGroup', $group->id);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->subjectService->transferSubject($source, $destination, transferSubjectable: true);

        $group->refresh();
        $this->assertEquals($destination->id, $group->subject_id);
    }

    public function test_new_under_parent_transfer_updates_customer_subject_id()
    {
        $source = $this->makeSubject(['code' => '001']);
        $parent = $this->makeSubject(['code' => '002']);
        $customer = $this->makeCustomer($source);
        $this->setSubjectable($source, 'App\Models\Customer', $customer->id);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $result = $this->subjectService->transferSubjectToNewUnderParent($source, $parent, transferSubjectable: true);

        $customer->refresh();
        $this->assertEquals($result['destination']->id, $customer->subject_id);
    }

    public function test_subject_id_on_customer_is_cleared_when_source_subject_is_deleted_after_transfer()
    {
        $source = $this->makeSubject(['code' => '001']);
        $destination = $this->makeSubject(['code' => '002']);
        $customer = $this->makeCustomer($source);
        $this->setSubjectable($source, 'App\Models\Customer', $customer->id);
        $doc = $this->makeDocument();
        $this->makeTransaction($source, $doc, 100);

        $this->subjectService->transferSubject($source, $destination, transferSubjectable: true, removeSource: true);

        $customer->refresh();
        $this->assertEquals($destination->id, $customer->subject_id);
        $this->assertNull(Subject::withoutGlobalScopes()->find($source->id));
    }
}
