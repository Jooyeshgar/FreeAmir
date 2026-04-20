<?php

namespace App\Http\Controllers;

use App\Services\HomeService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(private readonly HomeService $service) {}

    public function index()
    {
        if (! (auth()->user()->can('documents.show') or auth()->user()->can('products.index'))) {
            if (auth()->user()->can('employee-portal.dashboard')) {
                return redirect()->route('employee-portal.dashboard');
            }
            abort(403);
        }

        $cashTypes = ['both', 'bank', 'cash_book'];

        [$bankAccounts, $topTenBankAccountBalances] = $this->service->topTenBanksAccountBalances();

        $monthlyIncome = $this->service->getMonthlyIncome();
        $monthlyCost = $this->service->getMonthlyCost();

        $monthlySellAmount = $this->service->getMonthlyProductsStat();
        $monthlyWarehouse = $this->service->getMonthlyWarhouse();

        $popularProductsAndServices = $this->service->popularProductsAndServices();

        $sellAmountPerProducts = $this->service->getSellAmountPerProducts();

        ['incomeData' => $totalIncomesData, 'costData' => $totalCostsData, 'profit' => $profit] =
            $this->service->profitFromNonPermanentSubjects();

        return view('home', compact(
            'cashTypes',
            'bankAccounts',
            'topTenBankAccountBalances',
            'monthlyIncome',
            'monthlyCost',
            'monthlySellAmount',
            'monthlyWarehouse',
            'popularProductsAndServices',
            'sellAmountPerProducts',
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
