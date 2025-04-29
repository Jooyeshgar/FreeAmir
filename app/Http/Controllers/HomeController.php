<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {
        $customerCount = CustomerGroup::withCount('customers')->get()->sum('customers_count');

        $invoiceCount = Invoice::count();
        $documentCount = Document::count();
        $productCount = ProductGroup::withCount('products')->get()->sum('products_count');

        $cashBooks = Subject::where('parent_id', config('amir.cash_book'))->get();
        $banks = Subject::where('parent_id', config('amir.bank'))->get();

        // Calculate bank balances
        $bankBalances = [];
        foreach ($banks as $bank) {
            $balance = Transaction::where('subject_id', $bank->id)->sum('value');
            $bankBalances[$bank->id] = $balance;
        }

        $latestInvoices = Invoice::latest()->limit(10)->get();

        $monthlyIncome = $this->getMonthlyIncome();

        return view('home', compact(
            'customerCount',
            'invoiceCount',
            'documentCount',
            'productCount',
            'latestInvoices',
            'cashBooks',
            'banks',
            'bankBalances',
            'monthlyIncome'
        ));
    }


    public function subjectDetail(Request $request)
    {
        $data = $request->validate(
            [
                'cash_book' => 'required|exists:subjects,id',
                'duration' => 'required|integer|in:1,2,3,4',
            ]
        );
        $subjectId = $data['cash_book'];
        $duration = intval($data['duration']);
        $lastTransaction = Transaction::where('subject_id', $subjectId)
            ->orderBy('created_at', 'desc')
            ->first();
        $lastTransaction = $lastTransaction->created_at ?? now();

        $timeFilter = $lastTransaction->subMonths($duration * 3);
        $transactions = Transaction::where('subject_id', $subjectId)
            ->where('created_at', '>=', $timeFilter)
            ->selectRaw('DATE(created_at) as date, SUM(value) as total_value')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total_value', 'date');

        return response()->json([
            'labels' => $transactions->keys(),
            'datas' => $transactions->values(),
            'sum' => $transactions->sum(),
        ]);
    }

    public function getMonthlyIncome()
    {
        $currentJalaliYear = (int) jdate('Y');

        $startDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian($currentJalaliYear, '01', '01','/'));

        $endDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian($currentJalaliYear + 1, '01', '01','/'))->subDay();

        $transactions = Transaction::where('value', '>', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $monthlyIncome = array_fill(1, 12, 0);

        foreach ($transactions as $transaction) {
            $jalaliMonth = (int) jdate('m', strtotime($transaction->created_at));

            $monthlyIncome[$jalaliMonth] += $transaction->value;
        }

        $result = [];
        $jalaliMonthNames = [
            1 => 'فروردین',
            2 => 'اردیبهشت',
            3 => 'خرداد',
            4 => 'تیر',
            5 => 'مرداد',
            6 => 'شهریور',
            7 => 'مهر',
            8 => 'آبان',
            9 => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند'
        ];

        foreach ($monthlyIncome as $month => $income) {
            $result[$jalaliMonthNames[$month]] = $income;
        }

        return $result;
    }
}
