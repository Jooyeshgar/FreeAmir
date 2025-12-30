<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function __construct() {}

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

        $monthlySellAmount = $this->getMonthlySell();

        $monthlySellQuantity = $this->getMonthlySellQuantity();

        $popularProductsAndServices = $this->popularProductsAndServices();

        return view('home', compact(
            'customerCount',
            'invoiceCount',
            'documentCount',
            'productCount',
            'latestInvoices',
            'cashBooks',
            'banks',
            'bankBalances',
            'monthlyIncome',
            'popularProductsAndServices',
            'monthlySellAmount',
            'monthlySellQuantity'
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

        $endDate = $lastTransaction->document->date ?? now();

        $startDate = (clone $endDate)->subMonths($duration * 3);

        $initialBalance = Transaction::where('subject_id', $subjectId)
            ->where('created_at', '<', $startDate)
            ->with('document')
            ->whereHas('document', function ($query) use ($startDate) {
                $query->where('date', '<', $startDate);
            })
            ->sum('value');

        $transactions = Transaction::where('subject_id', $subjectId)
            ->with('document')
            ->whereHas('document', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })->get();

        $dailyTransactions = $transactions
            ->mapToGroups(function ($transaction) {
                return [optional($transaction->document)->date->toDateString() => $transaction->value];
            })
            ->mapWithKeys(function ($values, $date) {
                return [$date => array_sum($values->toArray())];
            });
        $dailyBalances = [];
        $runningBalance = -1 * $initialBalance;

        foreach ($dailyTransactions as $date => $dailyChange) {
            $runningBalance -= $dailyChange;
            $dailyBalances[$date] = $runningBalance;
        }

        return response()->json([
            'labels' => array_keys($dailyBalances),
            'datas' => array_values($dailyBalances),
            'sum' => end($dailyBalances) ?: $initialBalance,
            'start_date' => jdate('Y/m/d', $startDate->timestamp, tr_num: 'en'),
            'end_date' => jdate('Y/m/d', $endDate->timestamp, tr_num: 'en'),
        ]);
    }

    public function getMonthlyIncome()
    {
        $currentJalaliYear = (int) jdate('Y', tr_num: 'en');

        $startDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian($currentJalaliYear, '01', '01', '/'));

        $endDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian($currentJalaliYear + 1, '01', '01', '/'))->subDay();

        $incomeSubjects = Subject::where('parent_id', config('amir.income'))->get();

        $transactions = Transaction::where('value', '>', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('subject_id', $incomeSubjects->pluck('id')->toArray())
            ->get();

        $monthlyIncome = array_fill(1, 12, 0);

        foreach ($transactions as $transaction) {
            $jalaliMonth = (int) jdate('m', strtotime($transaction->created_at), tr_num: 'en');

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
            12 => 'اسفند',
        ];

        foreach ($monthlyIncome as $month => $income) {
            $result[$jalaliMonthNames[$month]] = $income;
        }

        return $result;
    }

    public function getMonthlySellQuantity()
    {
        $currentJalaliYear = (int) jdate('Y', tr_num: 'en');

        $startDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian($currentJalaliYear, '01', '01', '/'));

        $endDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian($currentJalaliYear + 1, '01', '01', '/'))->subDay();

        $productsInventorySubjectId = Product::pluck('inventory_subject_id')->toArray();

        $sellSubjects = Subject::whereIn('id', $productsInventorySubjectId)->get();

        $transactions = Transaction::where('value', '>', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('subject_id', $sellSubjects->pluck('id')->toArray())
            ->get();

        $monthlySellQuantity = array_fill(1, 12, 0);

        foreach ($transactions as $transaction) {
            $jalaliMonth = (int) jdate('m', strtotime($transaction->created_at), tr_num: 'en');

            $monthlySellQuantity[$jalaliMonth] += 1;
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
            12 => 'اسفند',
        ];

        foreach ($monthlySellQuantity as $month => $quantity) {
            $result[$jalaliMonthNames[$month]] = $quantity;
        }

        return $result;
    }

    public function getMonthlySell()
    {
        $currentJalaliYear = (int) jdate('Y', tr_num: 'en');

        $startDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian($currentJalaliYear, '01', '01', '/'));

        $endDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian($currentJalaliYear + 1, '01', '01', '/'))->subDay();

        $productsInventorySubjectId = Product::pluck('inventory_subject_id')->toArray();

        $sellSubjects = Subject::where('parent_id', config('amir.sell'))->get();

        $transactions = Transaction::where('value', '>', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('subject_id', $sellSubjects->pluck('id')->toArray())
            ->get();

        $monthlySell = array_fill(1, 12, 0);

        foreach ($transactions as $transaction) {
            $jalaliMonth = (int) jdate('m', strtotime($transaction->created_at), tr_num: 'en');

            $monthlySell[$jalaliMonth] += $transaction->value;
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
            12 => 'اسفند',
        ];

        foreach ($monthlySell as $month => $sell) {
            $result[$jalaliMonthNames[$month]] = $sell;
        }

        return $result;
    }

    public function popularProductsAndServices()
    {
        $items = InvoiceItem::whereHas('invoice', fn ($q) => $q->where('invoice_type', InvoiceType::SELL))
            ->whereIn('itemable_type', [Product::class, Service::class])->selectRaw('itemable_type, itemable_id, SUM(quantity) as total_quantity')
            ->groupBy('itemable_type', 'itemable_id')->orderByDesc('total_quantity')->with('itemable')->get()
            ->map(function ($item) {
                return [
                    'id' => $item->itemable_id,
                    'name' => $item->itemable->name ?? 'نامشخص',
                    'code' => $item->itemable->code ?? '-',
                    'quantity' => $item->total_quantity,
                    'type' => $item->itemable_type === Product::class ? 'products' : 'services',
                ];
            })->sortByDesc('quantity')->take(10)->values();

        return $items;
    }
}
