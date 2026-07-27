<?php

namespace App\Http\Controllers;

use App\Services\CostIncomeService;
use App\Services\MonthlyBudgetService;

class CostIncomeController extends Controller
{
    public function __construct(private readonly CostIncomeService $service, private readonly MonthlyBudgetService $monthlyBudgetService) {}

    public function index()
    {
        $summary = $this->service->summary();
        $monthly = $this->service->monthlyIncomeAndCost();
        $topCustomers = $this->service->topCustomers();
        $invoices = $this->service->invoiceSummary();
        $forecastChart = $this->monthlyBudgetService->fullYearAnalysis()['chart'];
        $forecastIncome = array_combine($forecastChart['labels'], $forecastChart['forecastIncome']);
        $forecastExpense = array_combine($forecastChart['labels'], $forecastChart['forecastExpense']);
        $monthlyBudgetLinks = collect(array_keys(MonthlyBudgetService::MONTHS))->map(fn (int $month) => route('budgets.index', ['month' => $month]))->values()->all();
        $monthsWithoutDocuments = collect($forecastChart['labels'])->filter(fn (string $label, int $index) => ($forecastChart['documentCounts'][$index] ?? 0) === 0)->values()->all();

        return view('reports.cost-income.index', [
            'totalIncome' => $summary['totalIncome'],
            'totalCost' => $summary['totalCost'],
            'profit' => $summary['profit'],
            'margin' => $summary['margin'],
            'incomeBreakdown' => $summary['incomeBreakdown'],
            'costBreakdown' => $summary['costBreakdown'],
            'monthlyIncome' => $monthly['income'],
            'monthlyCost' => $monthly['cost'],
            'forecastIncome' => $forecastIncome,
            'forecastExpense' => $forecastExpense,
            'monthlyBudgetLinks' => $monthlyBudgetLinks,
            'monthsWithoutDocuments' => $monthsWithoutDocuments,
            'debtors' => $topCustomers['debtors'],
            'creditors' => $topCustomers['creditors'],
            'invoices' => $invoices,
        ]);
    }
}
