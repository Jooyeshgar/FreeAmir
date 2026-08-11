<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PersonnelRequestStatus;
use App\Models\Activity;
use App\Models\AncillaryCost;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class HomeService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    /**
     * Build the platform-wide dashboard payload for authorized administrators.
     */
    public function superAdminOverview(): array
    {
        return [
            'metrics' => [
                'businesses' => Company::query()->distinct()->count('name'),
                'fiscalYears' => Company::query()->count(),
                'openFiscalYears' => Company::query()->whereNull('closed_at')->count(),
                'users' => User::query()->count(),
                'verifiedUsers' => User::query()->whereNotNull('email_verified_at')->count(),
                'unassignedUsers' => User::query()->doesntHave('companies')->count(),
            ],
            'recentCompanies' => Company::query()
                ->withCount('users')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'recentUsers' => User::query()
                ->with('roles:id,name')
                ->withCount('companies')
                ->latest()
                ->limit(4)
                ->get(),
            'roles' => Role::query()
                ->withCount('users')
                ->orderByDesc('users_count')
                ->orderBy('name')
                ->get(),
            'activityMetrics' => [
                'total' => Activity::query()->count(),
                'today' => Activity::query()->whereDate('created_at', Carbon::today())->count(),
                'model' => Activity::query()->where('source', 'model')->count(),
                'request' => Activity::query()->where('source', 'request')->count(),
            ],
            'recentActivities' => Activity::query()
                ->with('user:id,name,email')
                ->latest('id')
                ->limit(4)
                ->get(),
        ];
    }

    /**
     * Build the personal portal payload for an employee user. Returns null when the user is not linked to an employee record.
     */
    public function employeePersonalData(User $user): ?array
    {
        $employee = $user->employee;

        if (! $employee) {
            return null;
        }

        $requestsCount = collect(PersonnelRequestStatus::cases())->mapWithKeys(fn (PersonnelRequestStatus $status) => [
            $status->valueName() => $employee->personnelRequests()->where('status', $status)->count(),
        ])->toArray();

        return compact('employee', 'requestsCount');
    }

    /**
     * Return the five most recent records shown in each visible quick-access area.
     */
    public function latestQuickAccessData(array $areas): array
    {
        $data = [];

        if (in_array('accounting', $areas, true)) {
            $data['accounting'] = Document::query()
                ->with('documentable')
                ->whereNotNull('approved_at')
                ->latest('date')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'number', 'title', 'date', 'documentable_id', 'documentable_type'])
                ->map(fn (Document $document) => [
                    'label' => $document->title ?: __('Document').' #'.$document->number,
                    'date' => $document->date,
                    'type' => $document->documentable_type ? __('Automatic document') : __('Manual document'),
                    'typeKey' => $document->documentable_type ? 'documentable' : 'manual_document',
                    'typeHref' => $this->documentableHref($document),
                    'href' => route('documents.show', $document),
                ]);
        }

        if (in_array('sales', $areas, true)) {
            $data['sales'] = Invoice::query()
                ->whereIn('status', InvoiceStatus::approvedOrSettled())
                ->orderByDesc('date')
                ->orderByDesc('number')
                ->limit(5)
                ->get(['id', 'number', 'date', 'invoice_type'])
                ->map(fn (Invoice $invoice) => [
                    'label' => __('Invoice').' #'.$invoice->number,
                    'date' => $invoice->date,
                    'type' => $invoice->invoice_type->label(),
                    'typeKey' => $invoice->invoice_type->valueName(),
                    'href' => route('invoices.show', $invoice),
                ]);
        }

        if (in_array('inventory', $areas, true)) {
            $data['inventory'] = $this->latestInvoicedModels(Product::class, 'products.show');
        }

        if (in_array('services', $areas, true)) {
            $data['services'] = $this->latestInvoicedModels(Service::class, 'services.show');
        }

        if (in_array('customers', $areas, true)) {
            $latestInvoice = Invoice::query()
                ->whereColumn('invoices.customer_id', 'customers.id')
                ->whereIn('status', InvoiceStatus::approvedOrSettled())
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(1);

            $data['customers'] = Customer::query()
                ->select('customers.*')
                ->selectSub((clone $latestInvoice)->select('date'), 'latest_invoice_date')
                ->selectSub((clone $latestInvoice)->select('invoice_type'), 'latest_invoice_type')
                ->whereHas('invoices', fn ($query) => $query->whereIn('status', InvoiceStatus::approvedOrSettled()))
                ->orderByDesc('latest_invoice_date')
                ->orderByDesc('customers.id')
                ->limit(5)
                ->get()
                ->map(function (Customer $customer) {
                    $invoiceType = InvoiceType::from((int) $customer->latest_invoice_type);

                    return [
                        'label' => $customer->name,
                        'date' => Carbon::parse($customer->latest_invoice_date),
                        'type' => $invoiceType->label(),
                        'typeKey' => $invoiceType->valueName(),
                        'href' => route('customers.show', $customer),
                    ];
                });
        }

        return $data;
    }

    /**
     * Resolve the most useful show page for an automatic document's source.
     */
    private function documentableHref(Document $document): ?string
    {
        return match (true) {
            $document->documentable instanceof Invoice => route('invoices.show', $document->documentable),
            $document->documentable instanceof AncillaryCost => route('invoices.ancillary-costs.show', [$document->documentable->invoice_id, $document->documentable]),
            $document->documentable instanceof Cheque => route('cheques.show', $document->documentable),
            $document->documentable instanceof Payment && $document->documentable->invoice_id => route('invoices.show', $document->documentable->invoice_id),
            $document->documentable instanceof Payment && $document->documentable->cheque_id => route('cheques.show', $document->documentable->cheque_id),
            default => null,
        };
    }

    /**
     * Return unique catalog records ordered by their latest invoice usage.
     */
    private function latestInvoicedModels(string $itemableType, string $routeName): Collection
    {
        $model = new $itemableType;
        $table = $model->getTable();
        $latestInvoice = Invoice::withoutGlobalScopes()
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereColumn('invoice_items.itemable_id', $table.'.id')
            ->where('invoice_items.itemable_type', $itemableType)
            ->where('invoices.company_id', getActiveCompany())
            ->whereIn('invoices.status', array_map(fn (InvoiceStatus $status) => $status->value, InvoiceStatus::approvedOrSettled()))
            ->orderByDesc('invoices.date')
            ->orderByDesc('invoices.id')
            ->limit(1);

        return $itemableType::query()
            ->select($table.'.*')
            ->selectSub((clone $latestInvoice)->select('invoices.date'), 'latest_invoice_date')
            ->selectSub((clone $latestInvoice)->select('invoices.invoice_type'), 'latest_invoice_type')
            ->whereHas('invoiceItems', fn ($query) => $query->whereHas(
                'invoice',
                fn ($invoiceQuery) => $invoiceQuery->whereIn('status', InvoiceStatus::approvedOrSettled())
            ))
            ->orderByDesc('latest_invoice_date')
            ->orderByDesc($table.'.id')
            ->limit(5)
            ->get()
            ->map(function (Product|Service $item) use ($routeName) {
                $invoiceType = InvoiceType::from((int) $item->latest_invoice_type);

                return [
                    'label' => $item->name,
                    'date' => Carbon::parse($item->latest_invoice_date),
                    'type' => $invoiceType->label(),
                    'typeKey' => $invoiceType->valueName(),
                    'href' => route($routeName, $item),
                ];
            });
    }

    /**
     * Build income and cost chart data from non-permanent (temporary) subjects.
     * Fetches all leaf non-permanent subjects that have a non-zero balance.
     * Subjects with a positive balance are treated as income; negative as cost.
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

    public function totalWarehouseValue(): float
    {
        $total = 0.0;
        foreach (Product::whereNotNull('inventory_subject_id')->pluck('inventory_subject_id') as $inventorySubjectId) {
            $total += $this->subjectService->sumSubject($inventorySubjectId);
        }

        return $total;
    }

    /**
     * Total approved or settled sales for the active company and fiscal year.
     */
    public function totalSellAmount(): float
    {
        return (float) Invoice::query()->where('invoice_type', InvoiceType::SELL)->whereIn('status', InvoiceStatus::approvedOrSettled())->sum('amount');
    }

    /**
     * Total approved or settled purchases for the active company and fiscal year.
     */
    public function totalBuyAmount(): float
    {
        return (float) Invoice::query()->where('invoice_type', InvoiceType::BUY)->whereIn('status', InvoiceStatus::approvedOrSettled())->sum('amount');
    }

    public function averageSellAmount(): float
    {
        return (float) Invoice::query()->where('invoice_type', InvoiceType::SELL)->whereIn('status', InvoiceStatus::approvedOrSettled())->avg('amount');
    }

    public function averageBuyAmount(): float
    {
        return (float) Invoice::query()->where('invoice_type', InvoiceType::BUY)->whereIn('status', InvoiceStatus::approvedOrSettled())->avg('amount');
    }

    public function totalWarehouseRetailValue(): float
    {
        return (float) Product::query()->get(['quantity', 'selling_price'])->sum(
            fn (Product $product) => (float) $product->quantity * (float) $product->selling_price
        );
    }

    public function averageWarehouseUnitCost(): float
    {
        return (float) Product::query()->avg('average_cost');
    }

    public function averageWarehouseSellingPrice(): float
    {
        return (float) Product::query()->avg('selling_price');
    }

    /**
     * Return private values from the employee's latest payroll.
     *
     * @return array{net_payment: float, total_earnings: float, total_deductions: float, income_tax_amount: float}
     */
    public function employeePayrollSummary(User $user): array
    {
        $emptySummary = [
            'net_payment' => 0.0,
            'total_earnings' => 0.0,
            'total_deductions' => 0.0,
            'income_tax_amount' => 0.0,
        ];

        if (! $user->employee) {
            return $emptySummary;
        }

        $payroll = Payroll::query()
            ->where('employee_id', $user->employee->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        return $payroll ? [
            'net_payment' => (float) ($payroll?->net_payment ?? 0),
            'total_earnings' => (float) ($payroll?->total_earnings ?? 0),
            'total_deductions' => (float) ($payroll?->total_deductions ?? 0),
            'income_tax_amount' => (float) ($payroll?->income_tax_amount ?? 0),
        ] : $emptySummary;
    }
}
