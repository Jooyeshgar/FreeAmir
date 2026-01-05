<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
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
        $bankAccounts = Subject::where('parent_id', config('amir.bank'))->get();

        $bankAccountBalances = [];
        foreach ($bankAccounts as $bankAccount) {
            $balance = Transaction::where('subject_id', $bankAccount->id)->sum('value');
            $bankAccountBalances[$bankAccount->id] = $balance;
        }

        arsort($bankAccountBalances);
        $topTenBankAccountBalances = array_slice($bankAccountBalances, 0, 10, true);

        $latestInvoices = Invoice::latest()->limit(10)->get();

        $monthlyIncome = $this->getProductsStats('income_subject_id');
        $monthlySellAmount = $this->getProductsStats('inventory_subject_id');
        $monthlyWarehouse = $this->getProductsStats('inventory_subject_id');
        $popularProductsAndServices = $this->popularProductsAndServices();

        return view('home', compact(
            'customerCount',
            'invoiceCount',
            'documentCount',
            'productCount',
            'latestInvoices',
            'cashBooks',
            'bankAccounts',
            'topTenBankAccountBalances',
            'monthlyIncome',
            'popularProductsAndServices',
            'monthlySellAmount',
            'monthlyWarehouse'
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
        $banks = Subject::where('parent_id', config('amir.bank'))->pluck('id')->all();

        $lastTransaction = Transaction::query()
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('transactions.subject_id', $subjectId)
            ->orWhereIn('transactions.subject_id', $banks)
            ->orderByDesc('documents.date')
            ->select('transactions.*')
            ->with('document')
            ->first();

        $endDate = $lastTransaction?->document?->date ?? now();

        $startDate = (clone $endDate)->subMonths($duration * 3);

        $initialBalance = (int) Transaction::query()
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('transactions.subject_id', $subjectId)
            ->orWhereIn('transactions.subject_id', $banks)
            ->where('documents.date', '<', $startDate)
            ->sum('transactions.value');

        $dailyTransactions = Transaction::query()
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('transactions.subject_id', $subjectId)
            ->orWhereIn('transactions.subject_id', $banks)
            ->whereBetween('documents.date', [$startDate, $endDate])
            ->selectRaw('DATE(documents.date) as date, SUM(transactions.value) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (int) $v);

        $dailyBalances = [formatDate($startDate) => 0];
        $runningBalance = -1 * $initialBalance;

        foreach ($dailyTransactions as $date => $dailyChange) {
            $runningBalance -= $dailyChange;
            $dailyBalances[(string) $date] = $runningBalance;
        }

        $dailyBalances[formatDate($endDate)] = $runningBalance;

        return response()->json([
            'labels' => array_keys($dailyBalances),
            'datas' => array_values($dailyBalances),
            'sum' => end($dailyBalances) ?: $initialBalance,
            'start_date' => jdate('Y/m/d', $startDate->timestamp, tr_num: 'en'),
            'end_date' => jdate('Y/m/d', $endDate->timestamp, tr_num: 'en'),
        ]);
    }

    private function getProductsStats($columnName): array
    {
        $subjectIds = Product::pluck($columnName)->all();

        return $this->monthlyStats($subjectIds);
    }

    private function popularProductsAndServices()
    {
        return InvoiceItem::whereHas('invoice', fn ($q) => $q->where('invoice_type', InvoiceType::SELL)
            ->where('status', InvoiceStatus::APPROVED)
        )->selectRaw('itemable_type, itemable_id, SUM(quantity) as total_quantity')
            ->groupBy('itemable_type', 'itemable_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->itemable_id,
                'name' => $item->itemable->name ?? 'نامشخص',
                'code' => $item->itemable->code ?? '-',
                'quantity' => (int) $item->total_quantity,
                'type' => $item->itemable_type === Product::class ? 'products' : 'services',
            ]);
    }

    private function monthlyStats(array $subjectIds, bool $countOnly = false): array
    {
        [$startDate, $endDate] = $this->currentJalaliYearRange();

        if (empty($subjectIds)) {
            return $this->mapMonths([]);
        }

        $select = $countOnly
            ? 'COUNT(*) as total'
            : 'SUM(transactions.value) as total';

        $dailyTotals = Transaction::query()
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('transactions.value', '>', 0)
            ->whereIn('transactions.subject_id', $subjectIds)
            ->whereBetween('documents.date', [$startDate, $endDate])
            ->selectRaw("DATE(documents.date) as date, {$select}")
            ->groupBy('date')
            ->pluck('total', 'date');

        $byJalaliMonth = [];
        foreach ($dailyTotals as $date => $total) {
            $carbon = Carbon::parse((string) $date);
            $jalaliMonth = (int) jdate('n', $carbon->timestamp, tr_num: 'en');
            $byJalaliMonth[$jalaliMonth] = ($byJalaliMonth[$jalaliMonth] ?? 0) + (int) $total;
        }

        return $this->mapMonths($byJalaliMonth);
    }

    private function currentJalaliYearRange(): array
    {
        $year = (int) jdate('Y', tr_num: 'en');

        $start = Carbon::parse(jalali_to_gregorian($year, '01', '01', '/'))->startOfDay();

        $end = Carbon::parse(jalali_to_gregorian($year + 1, '01', '01', '/'))->subDay()->endOfDay();

        return [$start, $end];
    }

    private function mapMonths(array $data): array
    {
        $months = [
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

        $result = [];

        foreach ($months as $number => $name) {
            $result[$name] = (int) ($data[$number] ?? 0);
        }

        return $result;
    }
}
