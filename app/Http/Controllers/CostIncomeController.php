<?php

namespace App\Http\Controllers;

use App\Services\CostIncomeService;

class CostIncomeController extends Controller
{
    public function __construct(private readonly CostIncomeService $service) {}

    public function index()
    {
        $summary = $this->service->summary();
        $monthly = $this->service->monthlyIncomeAndCost();
        $topCustomers = $this->service->topCustomers();

        return view('reports.cost-income.index', [
            'totalIncome' => $summary['totalIncome'],
            'totalCost' => $summary['totalCost'],
            'profit' => $summary['profit'],
            'margin' => $summary['margin'],
            'incomeBreakdown' => $summary['incomeBreakdown'],
            'costBreakdown' => $summary['costBreakdown'],
            'monthlyIncome' => $monthly['income'],
            'monthlyCost' => $monthly['cost'],
            'debtors' => $topCustomers['debtors'],
            'creditors' => $topCustomers['creditors'],
        ]);
    }
}
