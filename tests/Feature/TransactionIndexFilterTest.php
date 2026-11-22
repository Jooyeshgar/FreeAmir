<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TransactionIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FiscalYear $fiscalYear;

    private int $subjectId;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');

        $this->fiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        $this->user = User::factory()->create();
        $this->fiscalYear->users()->attach($this->user);

        $subjectType = DB::connection()->getDriverName() === 'sqlite' ? 'both' : SubjectType::BOTH->value;
        $this->subjectId = DB::table('subjects')->insertGetId([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => '001',
            'name' => 'Test subject',
            'type' => $subjectType,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'transactions.index']));

        $this->actingAs($this->user);
        $this->withCookies(['active-fiscal-year-id' => (string) $this->fiscalYear->id]);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);
    }

    public function test_end_date_filter_ignores_post_end_date_and_closing_documents_in_opening_balance(): void
    {
        [$fiscalStart, $fiscalEnd] = $this->fiscalYear->range();

        $doc1 = $this->createTransaction($fiscalStart->copy()->addDays(5)->toDateString(), 1, 1000);
        $doc2 = $this->createTransaction($fiscalStart->copy()->addDays(20)->toDateString(), 2, 500);
        $doc3 = $this->createTransaction($fiscalStart->copy()->addDays(40)->toDateString(), 3, 200);
        $closingDoc = $this->createTransaction($fiscalEnd->toDateString(), 4, -1700);

        $this->fiscalYear->update(['closing_document_id' => $closingDoc->document_id]);

        $endDateJalali = convertToJalali($fiscalStart->copy()->addDays(25)->toDateString(), true);

        $response = $this->get(route('transactions.index', [
            'end_date' => $endDateJalali,
        ]));

        $response->assertOk();
        $response->assertViewHas('openingBalance', 0.0);

        $transactions = $response->viewData('transactions');
        $this->assertEquals([$doc2->id, $doc1->id], $transactions->pluck('id')->all());
        $this->assertEquals(1000.0, $transactions->firstWhere('id', $doc1->id)->balance);
        $this->assertEquals(1500.0, $transactions->firstWhere('id', $doc2->id)->balance);
    }

    public function test_start_date_and_end_date_filters_compute_opening_balance_from_prior_transactions(): void
    {
        [$fiscalStart, $fiscalEnd] = $this->fiscalYear->range();

        $priorTx = $this->createTransaction($fiscalStart->copy()->addDays(5)->toDateString(), 1, 1000);
        $inRangeTx = $this->createTransaction($fiscalStart->copy()->addDays(20)->toDateString(), 2, 500);
        $afterRangeTx = $this->createTransaction($fiscalStart->copy()->addDays(40)->toDateString(), 3, 200);
        $closingTx = $this->createTransaction($fiscalEnd->toDateString(), 4, -1700);

        $this->fiscalYear->update(['closing_document_id' => $closingTx->document_id]);

        $response = $this->get(route('transactions.index', [
            'start_date' => convertToJalali($fiscalStart->copy()->addDays(10)->toDateString(), true),
            'end_date' => convertToJalali($fiscalStart->copy()->addDays(25)->toDateString(), true),
        ]));

        $response->assertOk();
        $response->assertViewHas('openingBalance', 1000.0);

        $transactions = $response->viewData('transactions');
        $this->assertEquals([$inRangeTx->id], $transactions->pluck('id')->all());
        $this->assertEquals(1500.0, $transactions->first()->balance);
    }

    public function test_document_number_range_filter_computes_opening_balance_from_prior_numbers_and_excludes_closing_document(): void
    {
        [$fiscalStart, $fiscalEnd] = $this->fiscalYear->range();

        $this->createTransaction($fiscalStart->copy()->addDays(5)->toDateString(), 1, 400);
        $inRangeTx = $this->createTransaction($fiscalStart->copy()->addDays(10)->toDateString(), 2, 250);
        $this->createTransaction($fiscalStart->copy()->addDays(15)->toDateString(), 3, 100);
        $closingTx = $this->createTransaction($fiscalEnd->toDateString(), 4, -750);

        $this->fiscalYear->update(['closing_document_id' => $closingTx->document_id]);

        $response = $this->get(route('transactions.index', [
            'start_document_number' => '2',
            'end_document_number' => '2',
        ]));

        $response->assertOk();
        $response->assertViewHas('openingBalance', 400.0);

        $transactions = $response->viewData('transactions');
        $this->assertEquals([$inRangeTx->id], $transactions->pluck('id')->all());
        $this->assertEquals(650.0, $transactions->first()->balance);
    }

    private function createTransaction(string $date, int $number, float $value): Transaction
    {
        $document = Document::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'number' => $number,
            'date' => $date,
            'title' => "Doc {$number}",
            'creator_id' => $this->user->id,
        ]);

        return Transaction::create([
            'document_id' => $document->id,
            'subject_id' => $this->subjectId,
            'user_id' => $this->user->id,
            'value' => $value,
            'desc' => "Tx {$number}",
        ]);
    }
}
