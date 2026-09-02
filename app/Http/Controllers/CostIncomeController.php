<?php

namespace App\Http\Controllers;

use App\Services\CostIncomeService;
use App\Services\MonthlyBudgetService;
use App\Services\SubjectService;
use Illuminate\Support\Arr;

class CostIncomeController extends Controller
{
    public function __construct(
        private readonly CostIncomeService $service,
        private readonly MonthlyBudgetService $monthlyBudgetService,
        private readonly SubjectService $subjectService,
    ) {}

    public function index()
    {
        $summary = $this->service->summary();
        $topCustomers = $this->service->topCustomers();
        $invoices = $this->service->invoiceSummary();
        $fullYearAnalysis = $this->monthlyBudgetService->fullYearAnalysis();
        $forecastChart = $fullYearAnalysis['chart'];
        $forecastTotals = $fullYearAnalysis['totals'];
        $forecastProfit = $forecastTotals['forecastIncome'] + $forecastTotals['forecastExpense'];
        $actualCompletedProfit = $forecastTotals['actualIncome'] + $forecastTotals['actualExpense'];
        $profitCompletionPercent = $forecastProfit > 0 ? max(0, min(100, round($actualCompletedProfit / $forecastProfit * 100))) : 0;
        $monthlyIncome = array_combine($forecastChart['labels'], array_map(fn (int|float|null $value) => abs((float) ($value ?? 0)), $forecastChart['actualIncome']));
        $monthlyCost = array_combine($forecastChart['labels'], array_map(fn (int|float|null $value) => abs((float) ($value ?? 0)), $forecastChart['actualExpense']));
        $forecastIncome = array_combine($forecastChart['labels'], array_map(fn (int|float $value) => abs((float) $value), $forecastChart['forecastIncome']));
        $forecastExpense = array_combine($forecastChart['labels'], array_map(fn (int|float $value) => abs((float) $value), $forecastChart['forecastExpense']));
        $forecastMonths = $this->monthlyBudgetService->translatedMonths();
        $fiscalYear = (int) (config('active-company-fiscal-year') ?? toEnglish(jdate('Y')));
        $currentYear = (int) toEnglish(jdate('Y'));
        $firstDefaultForecastMonth = $fiscalYear === $currentYear ? (int) toEnglish(jdate('n')) : 1;
        $defaultForecastMonths = range($firstDefaultForecastMonth, 12);
        $forecastSubjects = $this->subjectService->buildSubjectTreeFromCollection($this->monthlyBudgetService->selectableSubjects());
        $monthlyBudgetLinks = collect(array_keys(MonthlyBudgetService::MONTHS))->map(fn (int $month) => route('budgets.index', ['month' => $month]))->values()->all();
        $monthsWithoutDocuments = collect($forecastChart['labels'])->filter(fn (string $label, int $index) => ($forecastChart['documentCounts'][$index] ?? 0) === 0)->values()->all();
        $monthsWithoutDocumentsLabel = Arr::join($monthsWithoutDocuments, config('app.locale') === 'fa' ? '، ' : ', ', ' '.__('and').' ');

        return view('reports.cost-income.index', [
            'totalIncome' => $summary['totalIncome'],
            'totalCost' => $summary['totalCost'],
            'profit' => $summary['profit'],
            'forecastProfit' => $forecastProfit,
            'actualCompletedProfit' => $actualCompletedProfit,
            'profitCompletionPercent' => $profitCompletionPercent,
            'margin' => $summary['margin'],
            'incomeBreakdown' => $summary['incomeBreakdown'],
            'costBreakdown' => $summary['costBreakdown'],
            'monthlyIncome' => $monthlyIncome,
            'monthlyCost' => $monthlyCost,
            'forecastIncome' => $forecastIncome,
            'forecastExpense' => $forecastExpense,
            'forecastMonths' => $forecastMonths,
            'defaultForecastMonths' => $defaultForecastMonths,
            'forecastSubjects' => $forecastSubjects,
            'currency' => config('amir.currency') ?? __('Rial'),
            'monthlyBudgetLinks' => $monthlyBudgetLinks,
            'monthsWithoutDocuments' => $monthsWithoutDocuments,
            'monthsWithoutDocumentsLabel' => $monthsWithoutDocumentsLabel,
            'debtors' => $topCustomers['debtors'],
            'creditors' => $topCustomers['creditors'],
            'invoices' => $invoices,
        ]);
    }
}
