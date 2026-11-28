<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Chequebook;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TreasuryDebugScenarioTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $year;

    private User $user;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->year = FiscalYear::factory()->create(['year' => 1405]);
        $this->user = User::factory()->create(['name' => 'مهدی رضایی']);
        $this->year->users()->attach($this->user);
        foreach (['banks.*', 'bank-accounts.*'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission));
        }
        $this->actingAs($this->user);
        $this->withCookies(['active-fiscal-year-id' => (string) $this->year->id]);
        config(['active-fiscal-year-id' => $this->year->id]);
        $root = Subject::create(['name' => 'بانک‌ها', 'code' => '011', 'parent_id' => null, 'type' => SubjectType::BOTH, 'is_permanent' => true]);
        config(['amir.bank' => $root->id]);
        $this->bank = Bank::create(['name' => 'بانک ملی', 'fiscal_year_id' => $this->year->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['name' => 'حساب شرکت', 'number' => 'DEBUG-LOCAL-001', 'type' => 1, 'owner' => 'مهدی رضایی', 'bank_id' => $this->bank->id, 'bank_branch' => 'شعبه مرکزی'], $overrides);
    }

    private function account(): BankAccount
    {
        $this->post(route('bank-accounts.store'), $this->payload())->assertRedirect(route('bank-accounts.index'))->assertSessionHasNoErrors();

        return BankAccount::sole();
    }

    public function test_real_bank_names_can_be_created_renamed_searched_and_deleted_locally(): void
    {
        $this->post(route('banks.store'), ['name' => 'بانک ملت'])->assertRedirect()->assertSessionHasNoErrors();
        $bank = Bank::where('name', 'بانک ملت')->sole();
        $this->get(route('banks.index'))->assertOk()->assertSee('بانک ملت');
        $this->put(route('banks.update', $bank), ['name' => 'بانک تجارت'])->assertRedirect()->assertSessionHasNoErrors();
        $this->getJson(route('bank-accounts.search-bank', ['q' => 'تجارت']))->assertOk()->assertJsonFragment(['text' => 'بانک تجارت']);
        $this->delete(route('banks.destroy', $bank))->assertRedirect();
        $this->assertDatabaseMissing('banks', ['id' => $bank->id]);
    }

    public function test_account_crud_keeps_its_subject_link_and_fiscal_year(): void
    {
        $account = $this->account();
        $subject = $account->subject;
        $this->assertNotNull($subject);
        $this->assertSame($subject->id, $account->subject_id);
        $this->assertEquals($this->year->id, $subject->fiscal_year_id);
        $this->assertTrue((bool) $subject->is_permanent);
        $this->get(route('bank-accounts.index'))->assertOk()->assertSee('حساب شرکت');
        $this->get(route('bank-accounts.show', $account))->assertOk();
        $this->get(route('bank-accounts.edit', $account))->assertOk();
        $this->put(route('bank-accounts.update', $account), $this->payload(['name' => 'حساب جاری']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('حساب جاری', $subject->fresh()->name);
        $this->delete(route('bank-accounts.destroy', $account))->assertRedirect();
        $this->assertDatabaseMissing('bank_accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }

    public function test_persian_joiners_are_valid_in_account_owner_and_branch_names(): void
    {
        $this->postJson(route('bank-accounts.store'), $this->payload(['name' => 'حساب پس‌انداز', 'owner' => 'علی‌رضا نیک‌نام', 'bank_branch' => 'شعبه باغ‌ملی']))->assertRedirect();
        $this->assertDatabaseHas('bank_accounts', ['owner' => 'علی‌رضا نیک‌نام']);
    }

    public function test_bank_from_another_company_year_cannot_be_used_to_create_an_account(): void
    {
        $other = FiscalYear::factory()->create(['year' => 1405]);
        $foreignBank = Bank::create(['name' => 'بانک صادرات', 'fiscal_year_id' => $other->id]);
        $this->postJson(route('bank-accounts.store'), $this->payload(['bank_id' => $foreignBank->id]))->assertUnprocessable()->assertJsonValidationErrors('bank_id');
        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_bank_from_another_company_year_cannot_be_used_to_update_an_account(): void
    {
        $account = $this->account();
        $other = FiscalYear::factory()->create(['year' => 1405]);
        $foreignBank = Bank::create(['name' => 'بانک صادرات', 'fiscal_year_id' => $other->id]);
        $this->putJson(route('bank-accounts.update', $account), $this->payload(['bank_id' => $foreignBank->id]))->assertUnprocessable()->assertJsonValidationErrors('bank_id');
        $this->assertSame($this->bank->id, $account->fresh()->bank_id);
    }

    public function test_deleting_a_bank_with_an_account_is_rejected_without_cascading(): void
    {
        $account = $this->account();
        $this->deleteJson(route('banks.destroy', $this->bank))->assertUnprocessable();
        $this->assertDatabaseHas('banks', ['id' => $this->bank->id]);
        $this->assertDatabaseHas('bank_accounts', ['id' => $account->id]);
        $this->assertDatabaseHas('subjects', ['id' => $account->subject_id]);
    }

    public function test_deleting_a_posted_account_preserves_both_account_and_subject(): void
    {
        $account = $this->account();
        $document = Document::factory()->create(['fiscal_year_id' => $this->year->id]);
        Transaction::create(['document_id' => $document->id, 'subject_id' => $account->subject_id, 'value' => -500000, 'user_id' => $this->user->id]);
        $this->deleteJson(route('bank-accounts.destroy', $account))->assertUnprocessable();
        $this->assertDatabaseHas('bank_accounts', ['id' => $account->id]);
        $this->assertDatabaseHas('subjects', ['id' => $account->subject_id]);
        $this->assertDatabaseHas('transactions', ['subject_id' => $account->subject_id, 'value' => -500000]);
    }

    public function test_failed_subject_creation_does_not_leave_a_partial_account(): void
    {
        $this->mock(SubjectService::class)->shouldReceive('createSubject')->once()->andThrow(new RuntimeException('Simulated subject failure'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('bank-accounts.store'), $this->payload());
            $this->fail('Expected simulated failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated subject failure', $e->getMessage());
        }
        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_duplicate_account_numbers_and_invalid_iban_are_rejected(): void
    {
        $this->account();
        $this->postJson(route('bank-accounts.store'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('number');
        $this->postJson(route('bank-accounts.store'), $this->payload(['number' => 'DEBUG-LOCAL-002', 'iban' => 'IR000000000000000000000000']))->assertUnprocessable()->assertJsonValidationErrors('iban');
        $this->assertDatabaseCount('bank_accounts', 1);
    }

    public function test_account_with_a_chequebook_cannot_be_deleted(): void
    {
        $account = $this->account();
        $book = Chequebook::create(['bank_account_id' => $account->id, 'serial_prefix' => 'آزمون', 'first_leaf' => 1, 'last_leaf' => 25, 'next_leaf' => 1]);
        $this->deleteJson(route('bank-accounts.destroy', $account))->assertUnprocessable();
        $this->assertDatabaseHas('bank_accounts', ['id' => $account->id]);
        $this->assertDatabaseHas('chequebooks', ['id' => $book->id]);
    }

    public function test_real_long_bank_names_accept_persian_joiners_and_respect_schema_length(): void
    {
        $name = 'بانک قرض‌الحسنه رسالت';
        $this->postJson(route('banks.store'), ['name' => $name])->assertRedirect();
        $bank = Bank::where('name', $name)->sole();
        $this->putJson(route('banks.update', $bank), ['name' => 'بانک قرض‌الحسنه مهر ایران'])->assertRedirect();
        $this->postJson(route('banks.store'), ['name' => str_repeat('ب', 51)])->assertUnprocessable()->assertJsonValidationErrors('name');
    }
}
