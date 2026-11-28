<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrialBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $year;

    private Subject $bank;

    private Subject $account;

    private Subject $capital;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->year = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $this->year->id]);
        $this->user = User::factory()->create();
        $this->bank = Subject::create(['code' => '011', 'name' => 'بانک‌ها', 'parent_id' => null, 'type' => SubjectType::BOTH]);
        $this->account = Subject::create(['code' => '011001', 'name' => 'بانک ملت', 'parent_id' => $this->bank->id, 'type' => SubjectType::BOTH]);
        $this->capital = Subject::create(['code' => '030', 'name' => 'سرمایه', 'parent_id' => null, 'type' => SubjectType::BOTH]);
        foreach ([[1, '2026-03-25', 100], [2, '2026-04-01', 200], [3, '2026-04-10', 300], [4, '2026-04-20', 400], [5, '2026-04-05', 500], [6, '2026-05-01', 600]] as [$number, $date, $value]) {
            $this->entry($number, $date, $value);
        }
    }

    private function entry(int $number, string $date, float $value, ?int $year = null): void
    {
        $doc = Document::factory()->create(['fiscal_year_id' => $year ?? $this->year->id, 'number' => $number, 'date' => $date, 'approved_at' => null, 'approver_id' => null]);
        foreach ([$this->account->id => -$value, $this->capital->id => $value] as $id => $amount) {
            Transaction::create(['subject_id' => $id, 'document_id' => $doc->id, 'value' => $amount, 'user_id' => $this->user->id]);
        }
    }

    public static function ranges(): array
    {
        return [
            'default includes ordinary documents one and two' => [[], 0, 2100, 2100],
            'number lower bound' => [['start_document_number' => 3], 300, 1800, 2100],
            'date lower bound with nonchronological document numbers' => [['start_date' => '1405/01/21'], 800, 1300, 2100],
            'both lower boundaries partition once' => [['start_date' => '1405/01/21', 'start_document_number' => 4], 1100, 1000, 2100],
            'date and number upper bounds also constrain opening' => [['start_date' => '1405/01/21', 'end_date' => '1405/01/31', 'start_document_number' => 4, 'end_document_number' => 4], 600, 400, 1000],
            'empty turnover still carries opening' => [['start_date' => '1405/02/20', 'end_date' => '1405/02/21'], 2100, 0, 2100],
        ];
    }

    #[DataProvider('ranges')]
    public function test_range_partition_has_complete_opening_and_turnover(array $filters, int $opening, int $turnover, int $balance): void
    {
        $report = app(TrialBalanceService::class)->getTrialBalanceData(new Request($filters));
        $bank = $report['subjects']->firstWhere('id', $this->bank->id);
        $this->assertEquals(-$opening, $bank->opening);
        $this->assertEquals(-$turnover, $bank->turnover_debit);
        $this->assertEquals(-$balance, $bank->balance);
        $this->assertEquals(0, $bank->turnover_credit);
    }

    public function test_two_level_totals_equal_one_level_totals_without_counting_children_twice(): void
    {
        $service = app(TrialBalanceService::class);
        $one = $service->getTrialBalanceData(new Request);
        $two = $service->getTrialBalanceData(new Request(['include_children' => 1]));
        $this->assertCount(3, $two['subjects']);
        $this->assertSame($one['totals'], $two['totals']);
        $this->assertEquals(2100, $two['totals']['turnover_debit']);
        $this->assertEquals(2100, $two['totals']['turnover_credit']);
    }

    public function test_foreign_fiscal_year_transactions_never_enter_either_range_column(): void
    {
        $other = FiscalYear::factory()->create(['year' => 1405]);
        $this->entry(7, '2026-04-10', 987654, $other->id);
        $report = app(TrialBalanceService::class)->getTrialBalanceData(new Request(['start_document_number' => 3]));
        $bank = $report['subjects']->firstWhere('id', $this->bank->id);
        $this->assertEquals(-300, $bank->opening);
        $this->assertEquals(-1800, $bank->turnover_debit);
        $this->assertEquals(-2100, $bank->balance);
    }

    public function test_csv_has_eight_columns_matching_service_and_preserves_persian_names(): void
    {
        $service = app(TrialBalanceService::class);
        $request = new Request(['start_document_number' => 3, 'parent_id' => $this->bank->id]);
        $report = $service->getTrialBalanceData($request);
        $response = $service->exportCsv($request);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $rows = array_map('str_getcsv', array_filter(explode("\n", $csv)));
        $this->assertCount(8, $rows[0]);
        $this->assertCount(8, $rows[1]);
        $this->assertSame($this->account->name, $rows[1][1]);
        $this->assertSame(csvNumber(300), $rows[1][2]);
        $this->assertSame(csvNumber(1800), $rows[1][4]);
        $this->assertSame(csvNumber(2100), $rows[1][6]);
        $this->assertSame('مانده ابتدای بازه', $report['opening_label']);
    }
}
