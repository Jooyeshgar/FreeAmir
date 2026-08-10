<?php

namespace App\Http\Controllers;

use App\Services\CostIncomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostIncomeController extends Controller
{
    public function __construct(private readonly CostIncomeService $service) {}

    public function index()
    {
        $summary = $this->service->summary();
        $monthly = $this->service->monthlyIncomeAndCost();
        $topCustomers = $this->service->topCustomers();
        $invoices = $this->service->invoiceSummary();
        $bankAccounts = $this->service->bankAccounts();

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
            'invoices' => $invoices,
            'bankAccounts' => $bankAccounts,
            'cashTypes' => ['both', 'bank', 'cash_book'],
        ]);
    }

    public function cashAndBanksBalances(Request $request): JsonResponse
    {
        $data = $request->validate([
            'duration' => ['required', 'integer', 'in:1,2,3,4'],
            'type' => ['required', Rule::in(['cash_book', 'bank', 'both'])],
        ]);

        return response()->json($this->service->cashAndBanksBalances($data['type'], (int) $data['duration']));
    }

    public function bankAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')
                    ->where('company_id', getActiveCompany())
                    ->where('parent_id', config('amir.bank')),
            ],
            'duration' => ['required', 'integer', 'in:1,2,3,4'],
        ]);

        return response()->json($this->service->balanceForSubjectIds([(int) $data['subject_id']], (int) $data['duration']));
    }
}
