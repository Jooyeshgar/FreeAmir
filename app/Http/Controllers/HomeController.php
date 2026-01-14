<?php

namespace App\Http\Controllers;

use App\Services\HomeService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(private readonly HomeService $service) {}

    public function index()
    {
        $cashTypes = ['both', 'bank', 'cash_book'];

        [$bankAccounts, $topTenBankAccountBalances] = $this->service->topTenBanksAccountBalances();

        [$monthlyIncome, $monthlyCost, $monthlySellAmount, $monthlyWarehouse] = $this->service->monthlyData();

        $popularProductsAndServices = $this->service->popularProductsAndServices();

        [$totalIncomes, $service_revenue, $sales_revenue, $other_income] = $this->service->incomeData();
        [$totalCosts, $wagesCost, $cogProductsCost, $otherCost] = $this->service->costData();

        $totalIncomesData = [
            __('Sales Revenue') => $service_revenue,
            __('Service Revenue') => $sales_revenue,
            __('Other Incomes') => $other_income,
            __('Wages') => 0,
            __('Cost of Goods Sold') => 0,
            __('Other Costs') => 0,
        ];

        $totalCostsData = [
            __('Sales Revenue') => 0,
            __('Service Revenue') => 0,
            __('Other Incomes') => 0,
            __('Wages') => abs($wagesCost),
            __('Cost of Goods Sold') => abs($cogProductsCost),
            __('Other Costs') => abs($otherCost),
        ];

        $profit = $totalIncomes + $totalCosts;

        return view('home', compact(
            'cashTypes',
            'bankAccounts',
            'topTenBankAccountBalances',
            'monthlyIncome',
            'monthlyCost',
            'monthlySellAmount',
            'monthlyWarehouse',
            'popularProductsAndServices',
            'totalIncomesData',
            'totalCostsData',
            'profit',
        ));
    }

    public function cashAndBanksBalances(Request $request)
    {
        $data = $request->validate(
            [
                'duration' => 'required|integer|in:1,2,3,4',
                'type' => 'required|in:cash_book,bank,both',
            ]
        );

        return $this->service->cashAndBanksBalances($data['type'], intval($data['duration']));
    }

    public function bankAccount(Request $request)
    {
        $data = $request->validate(
            [
                'subject_id' => 'required|integer|exists:subjects,id',
                'duration' => 'required|integer|in:1,2,3,4',
            ]
        );

        return $this->service->balanceForSubjectIds([$data['subject_id']], intval($data['duration']));
    }
}
