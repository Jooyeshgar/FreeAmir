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

        $cashTypes = ['both', 'bank', 'cash_book'];

        $bankAccounts = Subject::where('parent_id', config('amir.bank'))->get();

        $bankAccountBalances = Transaction::query()
            ->whereIn('subject_id', $bankAccounts->pluck('id'))
            ->selectRaw('subject_id, SUM(value) as balance')
            ->groupBy('subject_id')
            ->pluck('balance', 'subject_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        foreach ($bankAccounts as $bankAccount) {
            $bankAccountBalances[$bankAccount->id] = $bankAccountBalances[$bankAccount->id] ?? 0;
        }

        arsort($bankAccountBalances);
        $topTenBankAccountBalances = array_slice($bankAccountBalances, 0, 10, true);

        $latestInvoices = Invoice::latest()->limit(10)->get();

        $monthlyIncome = $this->getProductsStats('income_subject_id');
        $monthlySellAmount = $this->getProductsStats('inventory_subject_id');
        $monthlyWarehouse = $this->getProductsStats('inventory_subject_id', true);
        $popularProductsAndServices = $this->popularProductsAndServices();

        return view('home', compact(
            'customerCount',
            'invoiceCount',
            'documentCount',
            'productCount',
            'latestInvoices',
            'cashTypes',
            'bankAccounts',
            'topTenBankAccountBalances',
            'monthlyIncome',
            'popularProductsAndServices',
            'monthlySellAmount',
            'monthlyWarehouse'
        ));
    }

    private function cashBookBalance(int $duration)
    {
        $cashBookSubjectIds = Subject::where('parent_id', config('amir.cash_book'))->pluck('id')->all();

        return $this->balanceForSubjectIds($cashBookSubjectIds, $duration, true);
    }

    private function bankBalance(int $duration)
    {
        $bankAccountSubjectIds = Subject::where('parent_id', config('amir.bank'))->pluck('id')->all();

        return $this->balanceForSubjectIds($bankAccountSubjectIds, $duration, true);
    }

    private function bothBalance(int $duration)
    {
        $bankAccountSubjectIds = Subject::where('parent_id', config('amir.bank'))->pluck('id')->all();
        $cashBookSubjectIds = Subject::where('parent_id', config('amir.cash_book'))->pluck('id')->all();

        $subjectIds = array_values(array_unique(array_merge($bankAccountSubjectIds, $cashBookSubjectIds)));

        return $this->balanceForSubjectIds($subjectIds, $duration, true);
    }

    private function balanceForSubjectIds(array $subjectIds, int $duration, bool $invert = false)
    {
        $transactionQuery = Transaction::query()->whereIn('subject_id', $subjectIds);

        $lastTransaction = (clone $transactionQuery)
            ->with('document')
            ->orderByDesc(
                Document::query()
                    ->select('date')
                    ->whereColumn('documents.id', 'transactions.document_id')
                    ->limit(1)
            )
            ->first();

        $endDate = $lastTransaction?->document?->date ?? now();
        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse((string) $endDate);

        $startDate = (clone $endDate)->subMonths($duration * 3);

        $initialBalance = (int) (clone $transactionQuery)
            ->whereHas('document', fn ($q) => $q->where('date', '<=', $startDate))
            ->sum('value');

        $dailyTransactions = (clone $transactionQuery)
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->whereBetween('documents.date', [$startDate, $endDate])
            ->selectRaw('DATE(documents.date) as date, SUM(transactions.value) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (int) $v);

        if ($invert) {
            $initialBalance *= -1;
            $dailyTransactions = $dailyTransactions->map(fn ($v) => $v * -1);
        }

        $dailyBalances = [formatDate($startDate) => $initialBalance];
        $runningBalance = $initialBalance;

        foreach ($dailyTransactions as $date => $dailyChange) {
            $runningBalance += $dailyChange;
            $dailyBalances[(string) $date] = $runningBalance;
        }

        $dailyBalances[formatDate($endDate)] = $runningBalance;

        return $this->lineChartFormattedResponse($initialBalance, $dailyBalances, $startDate, $endDate);
    }

    private function lineChartFormattedResponse($initialBalance, $dailyBalances, $startDate, $endDate)
    {
        $sum = end($dailyBalances);
        if ($sum === false) {
            $sum = $initialBalance;
        }

        return response()->json([
            'labels' => array_keys($dailyBalances),
            'datas' => array_values($dailyBalances),
            'sum' => $sum,
            'start_date' => jdate('Y/m/d', $startDate->timestamp, tr_num: 'en'),
            'end_date' => jdate('Y/m/d', $endDate->timestamp, tr_num: 'en'),
        ]);
    }

    public function cashAndBanksBalances(Request $request)
    {
        $data = $request->validate(
            [
                'duration' => 'required|integer|in:1,2,3,4',
                'type' => 'required|in:cash_book,bank,both',
            ]
        );
        $duration = intval($data['duration']);

        return match ($data['type']) {
            'cash_book' => $this->cashBookBalance($duration),
            'bank' => $this->bankBalance($duration),
            'both' => $this->bothBalance($duration),
            default => response()->json([]),
        };
    }

    public function bankAccount(Request $request)
    {
        $data = $request->validate(
            [
                'subject_id' => 'required|integer|exists:subjects,id',
                'duration' => 'required|integer|in:1,2,3,4',
            ]
        );

        return $this->balanceForSubjectIds([$data['subject_id']], intval($data['duration']), true);
    }

    private function getProductsStats($columnName, bool $countOnly = false): array
    {
        $subjectIds = Product::pluck($columnName)->all();

        return $this->monthlyStats($subjectIds, $countOnly);
    }

    private function popularProductsAndServices()
    {
        return InvoiceItem::whereHas('invoice', fn ($q) => $q->where('invoice_type', InvoiceType::SELL)
            ->where('status', InvoiceStatus::APPROVED)
        )->with('itemable')
            ->selectRaw('itemable_type, itemable_id, SUM(quantity) as total_quantity')
            ->groupBy('itemable_type', 'itemable_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->itemable_id,
                'name' => $item->itemable->name ?? 'unknown',
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
