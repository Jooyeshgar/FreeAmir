<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MonthlyAttendance;
use App\Models\Payroll;
use App\Models\PersonnelRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HomeService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    /**
     * Build the personal portal payload for an employee user.
     *
     * Returns null when the user is not linked to an employee record.
     *
     * @return array{employee: Employee, recentLogs: Collection, requestsCount: array<string,int>, lastMonthlyAttendance: ?MonthlyAttendance, lastPayroll: ?Payroll}|null
     */
    public function employeePersonalData(User $user): ?array
    {
        $employee = $user->employee;

        if (! $employee) {
            return null;
        }

        $recentLogs = $employee->attendanceLogs()
            ->orderByDesc('log_date')
            ->limit(5)
            ->get();

        $requestsCount = $employee->personnelRequests()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $lastMonthlyAttendance = $employee->monthlyAttendances()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        $lastPayroll = $employee->payrolls()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        return compact('employee', 'recentLogs', 'requestsCount', 'lastMonthlyAttendance', 'lastPayroll');
    }

    public function workInProgressItems(User $user): Collection
    {
        $items = collect();

        if ($user->can('documents.index')) {
            $pendingDocumentsCount = Document::query()->whereNull('approved_at')->count();

            if ($pendingDocumentsCount > 0) {
                $items->push([
                    'tone' => 'warning',
                    'title' => __('Unapproved documents'),
                    'value' => localizeNumber($pendingDocumentsCount),
                    'description' => __('Accounting documents waiting for approval'),
                    'href' => route('documents.index', ['status' => 'unapproved']),
                    'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z',
                ]);
            }
        }

        if ($user->can('invoices.index')) {
            $actionableInvoiceStatuses = [
                InvoiceStatus::PENDING,
                InvoiceStatus::PRE_INVOICE,
                InvoiceStatus::UNAPPROVED,
                InvoiceStatus::READY_TO_APPROVE,
                InvoiceStatus::PARTIALLY_PAID,
            ];

            $actionableInvoiceStatusValues = collect($actionableInvoiceStatuses)
                ->map(fn (InvoiceStatus $status) => $status->value)
                ->all();

            $actionableInvoicesCount = Invoice::query()
                ->whereIn('status', $actionableInvoiceStatusValues)
                ->count();

            if ($actionableInvoicesCount > 0) {
                $items->push([
                    'tone' => 'info',
                    'title' => __('Invoices needing attention'),
                    'value' => localizeNumber($actionableInvoicesCount),
                    'description' => __('Draft, pending, or partially paid invoices'),
                    'href' => route('invoices.index'),
                    'icon' => 'M9 14l2 2 4-4m1-6H8a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V8l-4-4z',
                ]);
            }
        }

        if ($user->can('hr.personnel-requests.index')) {
            $approvedPersonnelRequestsCount = PersonnelRequest::query()
                ->where('status', 'approved')
                ->whereNull('payroll_id')
                ->count();

            if ($approvedPersonnelRequestsCount > 0) {
                $items->push([
                    'tone' => 'success',
                    'title' => __('Approved personnel requests'),
                    'value' => localizeNumber($approvedPersonnelRequestsCount),
                    'description' => __('Approved HR requests not attached to payroll yet'),
                    'href' => route('hr.personnel-requests.index', ['status' => 'approved']),
                    'icon' => 'M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z',
                ]);
            }
        }

        return $items;
    }

    public function recentDocuments(): Collection
    {
        return Document::query()
            ->with('creator')
            ->orderByRaw('CASE WHEN approved_at IS NULL THEN 0 ELSE 1 END')
            ->latest('date')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    public function recentInvoices(): Collection
    {
        $actionableStatuses = [
            InvoiceStatus::PENDING,
            InvoiceStatus::PRE_INVOICE,
            InvoiceStatus::UNAPPROVED,
            InvoiceStatus::READY_TO_APPROVE,
            InvoiceStatus::PARTIALLY_PAID,
        ];

        return Invoice::query()
            ->with('customer')
            ->orderByRaw(
                'CASE WHEN status IN ('.collect($actionableStatuses)->map(fn () => '?')->implode(',').') THEN 0 ELSE 1 END',
                collect($actionableStatuses)->map(fn (InvoiceStatus $status) => $status->value)->all()
            )
            ->latest('date')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    public function recentCustomers(): Collection
    {
        return Customer::query()
            ->with('group')
            ->orderByDesc('marked')
            ->latest('updated_at')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    public function getSellAmountPerProducts()
    {
        $baseQuery = InvoiceItem::query()
            ->whereHas('invoice', fn ($q) => $q->where('invoice_type', InvoiceType::SELL)
                ->whereIn('status', InvoiceStatus::approvedOrSettled())
            )
            ->with('itemable')
            ->selectRaw('itemable_type, itemable_id, SUM(amount) as total_amount')
            ->groupBy('itemable_type', 'itemable_id');

        $totalAmount = (clone $baseQuery)->get()->sum('total_amount');

        $topFiveInvoiceItemsSellAmount = (clone $baseQuery)
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'name' => $item->itemable->name ?? 'unknown',
                'amount' => (int) $item->total_amount,
                'type' => $item->itemable_type === Product::class ? __('Product') : __('Services'),
            ]);

        $sellInvoiceItemsTotalData = collect([[
            'name' => __('Other'),
            'amount' => $totalAmount - $topFiveInvoiceItemsSellAmount->sum('amount'),
            'type' => __('None'),
        ]]);

        return $sellInvoiceItemsTotalData->concat($topFiveInvoiceItemsSellAmount);
    }

    public function incomeData()
    {
        $incomeSubjectIds = [
            'service_revenue' => config('amir.service_revenue'),
            'sales_revenue' => config('amir.sales_revenue'),
        ];

        $incomes = [];
        foreach ($incomeSubjectIds as $index => $incomeSubjectId) {
            $subject = Subject::find($incomeSubjectId);
            $incomes[$index] = $subject ? $this->subjectService->sumSubject($subject) : 0;
        }

        return [$incomes['service_revenue'], $incomes['sales_revenue']];
    }

    public function costsData()
    {
        $wagesCostSubject = Subject::find(config('amir.wage'));
        $wagesCost = 0;
        if (! is_null($wagesCostSubject)) {
            $wagesCost = $this->subjectService->sumSubject($wagesCostSubject);
        }

        $productCogSubjectIds = Product::pluck('cogs_subject_id')->all();
        $productsCogCost = 0;
        foreach ($productCogSubjectIds as $productCogSubjectId) {
            $productSubject = Subject::find($productCogSubjectId);
            if (! is_null($productSubject)) {
                $productsCogCost += $this->subjectService->sumSubject($productSubject);
            }
        }
        $serviceCogSubjectIds = Service::pluck('cogs_subject_id')->all();
        $servicesCogCost = 0;
        foreach ($serviceCogSubjectIds as $serviceCogSubjectId) {
            $serviceSubject = Subject::find($serviceCogSubjectId);
            $servicesCogCost += $this->subjectService->sumSubject($serviceSubject);
        }

        return [$wagesCost, $productsCogCost, $servicesCogCost];
    }

    /**
     * Build income and cost chart data from non-permanent (temporary) subjects.
     *
     * Fetches all leaf non-permanent subjects that have a non-zero balance.
     * Subjects with a positive balance are treated as income; negative as cost.
     *
     * @return array{incomeData: array<string, int>, costData: array<string, int>, profit: int}
     */
    public function profitFromNonPermanentSubjects(): array
    {
        // Get all root non-permanent subjects for the current fiscal year (applied via global scope)
        $nonPermanentSubjects = Subject::where('is_permanent', false)->whereIsRoot()->get();

        $incomeData = [];
        $costData = [];
        $profit = 0.0;

        /** @var Subject $subject */
        foreach ($nonPermanentSubjects as $subject) {
            $balance = $this->subjectService->sumSubject($subject);

            if ($balance === 0) {
                continue;
            }

            $name = $subject->name;

            if ($balance > 0) {
                $incomeData[$name] = ($incomeData[$name] ?? 0) + $balance;
                $costData[$name] = 0;
            } else {
                $costData[$name] = ($costData[$name] ?? 0) + abs($balance);
                $incomeData[$name] = 0;
            }

            $profit += $balance;
        }

        return compact('incomeData', 'costData', 'profit');
    }

    public function cashAndBanksBalances(string $type, int $duration)
    {
        if ($type === 'cash_book') {
            $subjectIds = Subject::where('parent_id', config('amir.cash_book'))->pluck('id')->all();
        } elseif ($type === 'bank') {
            $subjectIds = Subject::where('parent_id', config('amir.bank'))->pluck('id')->all();
        } elseif ($type === 'both') {
            $bankAccountSubjectIds = Subject::where('parent_id', config('amir.bank'))->pluck('id')->all();
            $cashBookSubjectIds = Subject::where('parent_id', config('amir.cash_book'))->pluck('id')->all();

            $subjectIds = array_merge($bankAccountSubjectIds, $cashBookSubjectIds);
        } else {
            return response()->json([]);
        }

        return $this->balanceForSubjectIds($subjectIds, $duration, true);
    }

    public function getMonthlyCost()
    {
        $subject = Subject::find(config('amir.cost'));

        return $this->mapMonths($this->subjectService->sumSubjectWithDateRange($subject));
    }

    public function getMonthlyIncome()
    {
        $incomeSubjectIds = [
            'service_revenue' => config('amir.service_revenue'),
            'sales_revenue' => config('amir.sales_revenue'),
            'income' => config('amir.income'), // other_icnome = income - (service_revenue + sales_revenue)
        ];

        $monthlyIncomes = [];
        foreach ($incomeSubjectIds as $index => $incomeSubjectId) {
            $subject = Subject::find($incomeSubjectId);
            $monthlyIncomes[$index] = $subject
                ? $this->mapMonths($this->subjectService->sumSubjectWithDateRange($subject))
                : $this->mapMonths([]);
        }

        $other_income = [];
        foreach ($monthlyIncomes['income'] as $month => $total) {
            $other_income[$month] = $total - ($monthlyIncomes['service_revenue'][$month] + $monthlyIncomes['sales_revenue'][$month]);
        }

        foreach ($monthlyIncomes['income'] as $month => $total) {
            $monthlyIncomes['total'][$month] = $monthlyIncomes['service_revenue'][$month] + $monthlyIncomes['sales_revenue'][$month] + $other_income[$month];
        }

        return $monthlyIncomes['total'];
    }

    public function getMonthlyWarehouse()
    {
        $year = config('active-company-fiscal-year') ?? toEnglish(jdate('Y'));
        $startDate = jalali_to_gregorian($year, 1, 1, '-');
        // Esfand (month 12) has 30 days in a Jalali leap year, 29 in a common year.
        // Formula matches jdf.php jcheckdate() to avoid overflowing into the next fiscal year.
        $y = (int) $year;
        $lastDayOfYear = ($y % 33 % 4 - 1) === (int) ($y % 33 * 0.05) ? 30 : 29;
        $endDate = jalali_to_gregorian($year, 12, $lastDayOfYear, '-');

        $invoiceItems = \DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->select('invoices.date', 'invoice_items.itemable_id', 'invoice_items.quantity_at')
            ->where('invoice_items.itemable_type', Product::class)
            ->whereBetween('invoices.date', [$startDate, $endDate])
            ->get();

        $latestMonthlyProductsInvoiceItems = [];
        foreach ($invoiceItems as $invoiceItem) {
            $month = (int) toEnglish(jdate('m', strtotime($invoiceItem->date)));
            $key = $month.'-'.$invoiceItem->itemable_id;

            if (! isset($latestMonthlyProductsInvoiceItems[$key]) || $invoiceItem->date > $latestMonthlyProductsInvoiceItems[$key]->date) {
                $latestMonthlyProductsInvoiceItems[$key] = $invoiceItem;
            }
        }

        $monthlyWarehouse = array_fill(1, 12, 0);
        foreach ($latestMonthlyProductsInvoiceItems as $latestMonthlyProductInvoiceItem) {
            $month = (int) toEnglish(jdate('m', strtotime($latestMonthlyProductInvoiceItem->date)));
            $monthlyWarehouse[$month] += $latestMonthlyProductInvoiceItem->quantity_at;
        }

        return $this->mapMonths($monthlyWarehouse);
    }

    public function getMonthlyProductsStat()
    {
        $productInventorySubjectIds = Product::pluck('inventory_subject_id')->all();
        $monthlyProductsStat = [];

        foreach ($productInventorySubjectIds as $productInventorySubjectId) {
            $productSubject = Subject::find($productInventorySubjectId);
            if (! is_null($productSubject)) {
                $monthlyProductsStat[] = $this->subjectService->sumSubjectWithDateRange($productSubject);
            }
        }

        $productsStat = [];
        foreach ($monthlyProductsStat as $monthlyProductStat) {
            if (empty($monthlyProductStat)) {
                continue;
            }

            foreach ($monthlyProductStat as $month => $stat) {
                if (! isset($productsStat[$month])) {
                    $productsStat[$month] = 0;
                }

                $productsStat[$month] += $stat;
            }
        }

        return $this->mapMonths($productsStat);
    }

    public function balanceForSubjectIds(array $subjectIds, int $duration, bool $inverse = true)
    {
        $year = config('active-company-fiscal-year');

        $endDate = now();
        $lastDayOfFiscalYear = Carbon::parse(jalali_to_gregorian($year, 12, 29, '/'));
        if ($endDate->isAfter($lastDayOfFiscalYear)) {
            $endDate = $lastDayOfFiscalYear;
        }

        $transactionQuery = Transaction::query()->whereIn('subject_id', $subjectIds);

        $startDate = (clone $endDate)->subMonths($duration * 3);

        $firstDayOfFiscalYear = Carbon::parse(jalali_to_gregorian($year, 1, 1, '/'));

        if ($startDate->isBefore($firstDayOfFiscalYear)) {
            $startDate = $firstDayOfFiscalYear;
        }

        $initialBalance = (int) (clone $transactionQuery)
            ->whereHas('document', fn ($q) => $q->where('date', '<', $startDate))
            ->sum('value');

        $dailyTransactions = (clone $transactionQuery)
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->whereBetween('documents.date', [$startDate, $endDate])
            ->selectRaw('DATE(documents.date) as date, SUM(transactions.value) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (int) $v);

        $dailyBalances = [formatDate($startDate) => $initialBalance];
        $runningBalance = $initialBalance;

        foreach ($dailyTransactions as $date => $dailyChange) {
            $runningBalance += $dailyChange;
            $dailyBalances[formatDate($date)] = $runningBalance;
        }

        $dailyBalances[formatDate($endDate)] = $runningBalance;
        $values = array_values($dailyBalances);

        if ($inverse) {
            $values = array_map(fn ($value) => $value * -1, $values);
        }

        return response()->json([
            'labels' => array_keys($dailyBalances),
            'datas' => $values,
            'sum' => $runningBalance,
            'start_date' => jdate('Y/m/d', $startDate->timestamp, tr_num: 'en'),
            'end_date' => jdate('Y/m/d', $endDate->timestamp, tr_num: 'en'),
        ]);
    }

    public function popularProductsAndServices()
    {
        return InvoiceItem::whereHas('invoice', fn ($q) => $q->where('invoice_type', InvoiceType::SELL)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
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
                'selling_price' => $item->itemable->selling_price ?? null,
                'average_cost' => $item->itemable->average_cost ?? null,
                'quantity' => (int) $item->total_quantity,
                'type' => $item->itemable_type === Product::class ? 'products' : 'services',
            ]);
    }

    public function totalWarehouseValue(): float
    {
        $total = 0.0;
        foreach (Product::whereNotNull('inventory_subject_id')->pluck('inventory_subject_id') as $inventorySubjectId) {
            $total += $this->subjectService->sumSubject($inventorySubjectId);
        }

        return $total;
    }

    public function totalBuyAmount(): float
    {
        return (float) Invoice::query()
            ->where('invoice_type', InvoiceType::BUY)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->sum('amount');
    }

    public function topTenBanksAccountBalances()
    {
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

        asort($bankAccountBalances);

        return [$bankAccounts, array_slice($bankAccountBalances, 0, 10, true)];
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
            $result[$name] = (int) abs($data[$number] ?? 0);
        }

        return $result;
    }
}
