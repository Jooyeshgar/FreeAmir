<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PersonnelRequestStatus;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
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
     * Build the personal portal payload for an employee user.
     *
     * Returns null when the user is not linked to an employee record.
     *
     * @return array{employee: Employee, requestsCount: array<string,int>}|null
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
