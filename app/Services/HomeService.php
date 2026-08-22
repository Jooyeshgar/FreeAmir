<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PersonnelRequestStatus;
use App\Models\Activity;
use App\Models\AncillaryCost;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class HomeService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    /**
     * Build the platform-wide dashboard payload for authorized administrators.
     */
    public function superAdminOverview(): array
    {
        $statistics = function (): array {
            $now = Carbon::now();
            $currentPeriodStart = $now->copy()->subDays(29)->startOfDay();
            $previousPeriodStart = $currentPeriodStart->copy()->subDays(30);
            $newUsers = User::query()->where('created_at', '>=', $currentPeriodStart)->count();
            $previousNewUsers = User::query()
                ->whereBetween('created_at', [$previousPeriodStart, $currentPeriodStart->copy()->subSecond()])
                ->count();

            $userGrowthRate = match (true) {
                $previousNewUsers > 0 => round((($newUsers - $previousNewUsers) / $previousNewUsers) * 100, 1),
                $newUsers > 0 => 100.0,
                default => 0.0,
            };

            $userGrowthStart = $now->copy()->startOfMonth()->subMonths(5);
            $usersByMonth = User::query()
                ->where('created_at', '>=', $userGrowthStart)
                ->get(['created_at'])
                ->countBy(fn (User $user): string => $user->created_at->format('Y-m'));
            $userGrowth = collect(range(0, 5))->map(function (int $offset) use ($userGrowthStart, $usersByMonth): array {
                $month = $userGrowthStart->copy()->addMonths($offset);

                return [
                    'key' => $month->format('Y-m'),
                    'label' => $month->translatedFormat('M'),
                    'count' => $usersByMonth->get($month->format('Y-m'), 0),
                ];
            });

            $activityStart = $now->copy()->subDays(6)->startOfDay();
            $activitiesByDay = Activity::query()
                ->where('created_at', '>=', $activityStart)
                ->get(['created_at'])
                ->countBy(fn (Activity $activity): string => $activity->created_at->toDateString());
            $activityTrend = collect(range(0, 6))->map(function (int $offset) use ($activityStart, $activitiesByDay): array {
                $day = $activityStart->copy()->addDays($offset);

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->translatedFormat('D'),
                    'count' => $activitiesByDay->get($day->toDateString(), 0),
                ];
            });

            return [
                'metrics' => [
                    'businesses' => Company::query()->distinct()->count('name'),
                    'activeBusinesses' => Company::query()->whereNull('closed_at')->distinct()->count('name'),
                    'fiscalYears' => Company::query()->count(),
                    'openFiscalYears' => Company::query()->whereNull('closed_at')->count(),
                    'closedFiscalYears' => Company::query()->whereNotNull('closed_at')->count(),
                    'users' => User::query()->count(),
                    'verifiedUsers' => User::query()->whereNotNull('email_verified_at')->count(),
                    'unassignedUsers' => User::query()->doesntHave('companies')->count(),
                    'newUsers' => $newUsers,
                    'userGrowthRate' => $userGrowthRate,
                ],
                'userGrowth' => $userGrowth,
                'activityTrend' => $activityTrend,
                'activityMetrics' => [
                    'total' => Activity::query()->count(),
                    'today' => Activity::query()->whereDate('created_at', Carbon::today())->count(),
                    'model' => Activity::query()->where('source', 'model')->count(),
                    'request' => Activity::query()->where('source', 'request')->count(),
                ],
            ];
        };

        $statistics = app()->environment('testing')
            ? $statistics()
            : Cache::remember('dashboard.super-admin-overview.v1', now()->addMinutes(5), $statistics);

        return $statistics + [
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
     * Return the most recent records shown in each visible quick-access area.
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
                ->limit(10)
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
                ->limit(10)
                ->get(['id', 'number', 'date', 'invoice_type'])
                ->map(fn (Invoice $invoice) => [
                    'label' => __('Invoice').' #'.$invoice->number,
                    'date' => $invoice->date,
                    'type' => $invoice->invoice_type->label(),
                    'typeKey' => $invoice->invoice_type->valueName(),
                    'href' => route('invoices.show', $invoice),
                ]);
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
