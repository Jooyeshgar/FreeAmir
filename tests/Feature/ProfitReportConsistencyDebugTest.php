<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CostIncomeService;
use App\Services\DocumentService;
use App\Services\FiscalYearOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProfitReportConsistencyDebugTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $year;

    private User $user;

    private Subject $income;

    private Subject $expense;

    private Subject $cash;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        app()->setLocale('en');
        $this->year = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $this->year->id, 'active-company-fiscal-year' => 1405]);
        $this->user = User::factory()->create(['name' => 'مریم احمدی']);
        $this->year->users()->attach($this->user);
        foreach (['api.access', 'reports.trial-balance', 'reports.cost-income'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($this->user);
        $this->income = Subject::create(['code' => '050', 'name' => 'درآمد خدمات', 'parent_id' => null, 'type' => SubjectType::CREDITOR, 'is_permanent' => false]);
        $this->expense = Subject::create(['code' => '040', 'name' => 'هزینه خدمات', 'parent_id' => null, 'type' => SubjectType::DEBTOR, 'is_permanent' => false]);
        $this->cash = Subject::create(['code' => '011', 'name' => 'صندوق', 'parent_id' => null, 'type' => SubjectType::BOTH, 'is_permanent' => true]);
        config(['amir.cost' => $this->expense->id, 'amir.income' => $this->income->id, 'amir.sales_revenue' => $this->income->id, 'amir.service_revenue' => null]);
    }

    private function entries(string $income, string $expense, bool $approved = true): Document
    {
        $data = ['date' => '2026-04-10'];
        if ($approved) {
            $data += ['approved_at' => now(), 'approver_id' => $this->user->id];
        }

        return DocumentService::createDocument($this->user, $data, [
            ['subject_id' => $this->income->id, 'value' => $income],
            ['subject_id' => $this->expense->id, 'value' => '-'.$expense],
            ['subject_id' => $this->cash->id, 'value' => number_format((float) $expense - (float) $income, 2, '.', '')],
        ]);
    }

    public function test_overview_profit_uses_approved_documents_and_matches_cost_income(): void
    {
        $this->entries('1000.75', '400.25');
        $this->entries('5000.00', '2000.00', false);
        $report = app(FiscalYearOverviewService::class)->profitFromNonPermanentSubjects('2026-04-01', '2026-04-30');
        $this->assertEqualsWithDelta(600.50, $report['profit'], 0.00001);
        $this->assertEqualsWithDelta(app(CostIncomeService::class)->summary()['profit'], $report['profit'], 0.00001);
        $this->assertEqualsWithDelta(1000.75, app(FiscalYearOverviewService::class)->getMonthlyIncome()['فروردین'], 0.00001);
        $this->assertEqualsWithDelta(400.25, app(FiscalYearOverviewService::class)->getMonthlyCost()['فروردین'], 0.00001);
    }

    public function test_overview_excludes_a_document_from_another_fiscal_year_even_if_its_subject_id_is_corrupt(): void
    {
        $this->entries('1000.75', '400.25');
        $other = FiscalYear::factory()->create(['year' => 1405]);
        $foreign = Document::factory()->create(['fiscal_year_id' => $other->id, 'date' => '2026-04-10', 'approved_at' => now(), 'approver_id' => $this->user->id]);
        Transaction::create(['document_id' => $foreign->id, 'subject_id' => $this->income->id, 'value' => 9999, 'user_id' => $this->user->id]);
        $report = app(FiscalYearOverviewService::class)->profitFromNonPermanentSubjects();
        $this->assertEqualsWithDelta(600.50, $report['profit'], 0.00001);
        $this->assertEqualsWithDelta(600.50, app(CostIncomeService::class)->summary()['profit'], 0.00001);
    }

    public static function actualProfits(): array
    {
        return [[600.75, 7200.0, 'Profit'], [-0.75, 7200.0, 'Loss'], [0.75, null, 'Profit']];
    }

    #[DataProvider('actualProfits')]
    public function test_net_profit_card_displays_actual_value_and_keeps_forecast_separately(float $profit, ?float $forecast, string $direction): void
    {
        $html = Blade::render('@include("reports.cost-income._metrics")', ['profit' => $profit, 'forecastProfit' => $forecast, 'actualCompletedProfit' => 1.25, 'profitCompletionPercent' => 8]);
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<meta charset="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $card = $xpath->query('//article[.//span[normalize-space(.)="Net Profit"]]')->item(0);
        $this->assertNotNull($card);
        $value = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " tabular-nums ")]', $card)->item(0);
        $this->assertSame(formatNumber(abs($profit)), trim($value->textContent));
        $this->assertStringContainsString($direction, $card->textContent);
        if ($forecast !== null) {
            $this->assertStringContainsString(__('FY forecast').': '.formatNumber($forecast), $card->textContent);
        }
    }

    public static function mcpReports(): array
    {
        return [['get_financial_summary', []], ['get_profit_loss', ['period' => 'fiscal_year']], ['get_profit_loss', ['month' => 1]]];
    }

    #[DataProvider('mcpReports')]
    public function test_mcp_reports_preserve_fractional_amounts_in_data_and_readable_summary(string $tool, array $arguments): void
    {
        $this->entries('0.75', '0.25');
        $token = $this->user->createToken('Local precision test', ['reports.trial-balance', 'reports.cost-income'])->plainTextToken;
        $result = $this->withToken($token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => ['fiscal_year_id' => $this->year->id, ...$arguments]],
        ])->assertOk()->json('result');
        $this->assertFalse($result['isError'] ?? true, json_encode($result, JSON_UNESCAPED_UNICODE));
        $report = json_decode($result['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertEqualsWithDelta(0.75, $report['total_income'], 0.00001);
        $this->assertEqualsWithDelta(0.25, $report['total_cost'], 0.00001);
        $this->assertEqualsWithDelta(0.50, $report['net_profit'], 0.00001);
        $this->assertStringContainsString('0.75', $report['human_summary']);
        $this->assertStringContainsString('0.25', $report['human_summary']);
        if ($tool === 'get_financial_summary') {
            $this->assertEqualsWithDelta(0.75, $report['top_income_sources'][0]['amount'], 0.00001);
            $this->assertStringStartsWith('0.75 ', $report['top_income_sources'][0]['formatted']);
        } else {
            $this->assertEqualsWithDelta(0.75, $report['monthly_breakdown'][0]['income'], 0.00001);
        }
    }
}
