<?php

namespace App\Http\Controllers;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\OrganizationUnit;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollStatusHistory;
use App\Models\PersonnelRequest;
use App\Models\SalaryDecree;
use App\Services\PayrollService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Exceptions\UnauthorizedException;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payrollService) {}

    /**
     * Display a payroll's details.
     */
    public function show(Payroll $payroll): View
    {
        $payroll->load(['employee', 'decree.benefits.element', 'monthlyAttendance', 'items.element', 'statusHistories.user']);

        return view('payrolls.show', compact('payroll'))
            ->with('isEmployeeView', false);
    }

    /**
     * Display a list of payrolls.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'year' => ['nullable', 'integer', 'between:1300,1600'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'status' => ['nullable', 'string', Rule::enum(PayrollStatus::class)],
        ]);

        $query = Payroll::query();

        if (! empty($validated['employee_id'])) {
            $query->where('employee_id', $validated['employee_id']);
        }

        if (! empty($validated['year'])) {
            $query->where('year', $validated['year']);
        }

        if (! empty($validated['month'])) {
            $query->where('month', $validated['month']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $payrolls = $query->with(['employee', 'decree', 'monthlyAttendance'])
            ->orderBy('issue_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        $employees = Employee::orderBy('code')->get();

        return view('payrolls.index', compact('payrolls', 'employees'));
    }

    /**
     * Display the HR payroll dashboard.
     */
    public function dashboard(Request $request): View
    {
        $latestPayroll = Payroll::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first(['year', 'month']);

        $defaultYear = (int) ($latestPayroll?->year ?? config('active-company-fiscal-year') ?? toEnglish(jdate('Y')));
        $defaultMonth = (int) ($latestPayroll?->month ?? toEnglish(jdate('n')));

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:1300,1600'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'organization_unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
            'status' => ['nullable', 'string', Rule::enum(PayrollStatus::class)],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $year = (int) ($validated['year'] ?? $defaultYear);
        $month = (int) ($validated['month'] ?? $defaultMonth);
        $organizationUnitId = isset($validated['organization_unit_id']) ? (int) $validated['organization_unit_id'] : null;
        $statusFilter = $validated['status'] ?? null;
        $search = trim($validated['q'] ?? '');

        $availableYears = Payroll::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($item) => (int) $item);

        if (! $availableYears->contains($year)) {
            $availableYears->push($year);
        }

        $availableYears = $availableYears->unique()->sortDesc()->values();
        $organizationUnits = OrganizationUnit::orderBy('name')->get(['id', 'name']);

        $periodQuery = $this->payrollDashboardQuery($year, $month, $organizationUnitId);
        $yearQuery = $this->payrollDashboardQuery($year, null, $organizationUnitId);

        [$previousYear, $previousMonth] = $month === 1
            ? [$year - 1, 12]
            : [$year, $month - 1];

        $summary = $this->summarizePayrollQuery(clone $periodQuery);
        $previousSummary = $this->summarizePayrollQuery(
            $this->payrollDashboardQuery($previousYear, $previousMonth, $organizationUnitId)
        );

        $monthlyTrend = $this->monthlyPayrollTrend(clone $yearQuery);
        $metricCards = $this->payrollMetricCards($summary, $previousSummary, $monthlyTrend);
        $departmentCosts = $this->departmentCosts(clone $periodQuery);
        $statusSummaries = $this->payrollStatusSummaries(clone $periodQuery);
        $attendanceSummary = $this->attendanceSummary($year, $month, $organizationUnitId);
        $attendanceHeatmap = $this->attendanceHeatmap($year, $month, $organizationUnitId);
        $alerts = $this->dashboardAlerts($year, $month, $organizationUnitId, $summary);

        $payrollsQuery = $this->payrollDashboardQuery($year, $month, $organizationUnitId)
            ->with(['employee.organizationUnit', 'monthlyAttendance'])
            ->when($statusFilter, fn (Builder $query) => $query->where('status', $statusFilter))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->whereHas('employee', function (Builder $employeeQuery) use ($search) {
                    $employeeQuery->where(function (Builder $nameQuery) use ($search) {
                        $nameQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhereHas('organizationUnit', fn (Builder $unitQuery) => $unitQuery->where('name', 'like', "%{$search}%"));
                    });
                });
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        $payrolls = $payrollsQuery->paginate(10)->withQueryString();

        $payrollChartData = [
            'labels' => $monthlyTrend->pluck('label')->all(),
            'net' => $monthlyTrend->pluck('net')->all(),
            'deductions' => $monthlyTrend->pluck('deductions')->all(),
        ];

        $attendanceChartData = [
            'labels' => ['حضور کامل', 'تاخیر / تعجیل', 'مرخصی', 'غیبت'],
            'data' => [
                $attendanceSummary['present_days'],
                $attendanceSummary['delay_day_equivalent'],
                $attendanceSummary['leave_day_equivalent'],
                $attendanceSummary['absent_days'],
            ],
        ];

        if (array_sum($attendanceChartData['data']) <= 0) {
            $attendanceChartData = [
                'labels' => ['بدون داده'],
                'data' => [1],
            ];
        }

        return view('payrolls.dashboard', compact(
            'alerts',
            'attendanceChartData',
            'attendanceHeatmap',
            'attendanceSummary',
            'availableYears',
            'departmentCosts',
            'metricCards',
            'month',
            'monthlyTrend',
            'organizationUnitId',
            'organizationUnits',
            'payrollChartData',
            'payrolls',
            'search',
            'statusFilter',
            'statusSummaries',
            'summary',
            'year',
        ));
    }

    /**
     * Store a new payroll generated from a monthly attendance record.
     *
     * POST /attendance/monthly-attendances/{monthly_attendance}/payroll
     */
    public function store(Request $request, MonthlyAttendance $monthlyAttendance): RedirectResponse
    {
        $validated = $request->validate([
            'decree_id' => ['required', 'integer', 'exists:salary_decrees,id'],
        ]);

        $decree = SalaryDecree::withoutGlobalScopes()
            ->where('id', $validated['decree_id'])
            ->where('employee_id', $monthlyAttendance->employee_id)
            ->firstOrFail();

        $payroll = $this->payrollService->createFromAttendance(
            attendance: $monthlyAttendance,
            decree: $decree,
            companyId: (int) getActiveCompany(),
        );

        return redirect()->route('salary.payrolls.show', $payroll)
            ->with('success', __('Payroll created successfully.'));
    }

    /**
     * Show the edit form for a single payroll item.
     */
    public function editItem(PayrollItem $payrollItem): View
    {
        $payrollItem->load(['element', 'payroll']);

        return view('payrolls.edit-item', compact('payrollItem'));
    }

    /**
     * Update a single payroll item and recalculate payroll totals.
     */
    public function updateItem(Request $request, PayrollItem $payrollItem): RedirectResponse
    {
        $validated = $request->validate([
            'calculated_amount' => ['required', 'numeric'],
            'unit_count' => ['nullable', 'numeric'],
            'unit_rate' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $payrollItem->update($validated);
        $this->payrollService->recalculateTotals($payrollItem->payroll);

        return redirect()->route('salary.payrolls.show', $payrollItem->payroll_id)
            ->with('success', __('Payroll item updated successfully.'));
    }

    public function submitForApproval(Request $request, Payroll $payroll): RedirectResponse
    {
        return $this->transition(
            request: $request,
            payroll: $payroll,
            toStatus: PayrollStatus::PendingManagerApproval,
            message: __('Payroll submitted for manager approval.')
        );
    }

    public function approve(Request $request, Payroll $payroll): RedirectResponse
    {
        return $this->transition(
            request: $request,
            payroll: $payroll,
            toStatus: PayrollStatus::Approved,
            message: __('Payroll approved successfully.')
        );
    }

    public function markPaid(Request $request, Payroll $payroll): RedirectResponse
    {
        return $this->transition(
            request: $request,
            payroll: $payroll,
            toStatus: PayrollStatus::Paid,
            message: __('Payroll marked as paid.')
        );
    }

    /**
     * Remove the specified payroll.
     */
    public function destroy(Payroll $payroll): RedirectResponse
    {
        $attendanceId = $payroll->monthly_attendance_id;
        $payroll->items()->delete();
        $payroll->delete();

        if ($attendanceId) {
            return redirect()->route('attendance.monthly-attendances.show', $attendanceId)
                ->with('success', __('Payroll deleted successfully.'));
        }

        return redirect()->route('attendance.monthly-attendances.index')
            ->with('success', __('Payroll deleted successfully.'));
    }

    private function transition(Request $request, Payroll $payroll, PayrollStatus $toStatus, string $message): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $fromStatus = $payroll->status;
        $permission = $payroll->transitionPermissionTo($toStatus);

        if ($permission === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, __('This payroll status transition is not allowed.'));
        }

        $this->authorizeExactTransitionPermission($request, $permission);

        DB::transaction(function () use ($payroll, $fromStatus, $toStatus, $validated, $request) {
            $payroll->forceFill(['status' => $toStatus])->save();

            PayrollStatusHistory::create([
                'payroll_id' => $payroll->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $request->user()?->id,
                'changed_at' => Carbon::now(),
                'note' => $validated['note'] ?? null,
            ]);
        });

        return redirect()->route('salary.payrolls.show', $payroll)
            ->with('success', $message);
    }

    private function authorizeExactTransitionPermission(Request $request, string $permission): void
    {
        $hasExactPermission = $request->user()
            ?->getAllPermissions()
            ->contains('name', $permission) ?? false;

        if (! $hasExactPermission) {
            throw UnauthorizedException::forPermissions([$permission]);
        }
    }

    private function payrollDashboardQuery(int $year, ?int $month = null, ?int $organizationUnitId = null): Builder
    {
        return Payroll::query()
            ->where('year', $year)
            ->when($month !== null, fn (Builder $query) => $query->where('month', $month))
            ->when($organizationUnitId, function (Builder $query) use ($organizationUnitId) {
                $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('organization_unit_id', $organizationUnitId));
            });
    }

    private function summarizePayrollQuery(Builder $query): array
    {
        $row = $query
            ->selectRaw('COUNT(*) as payroll_count')
            ->selectRaw('COUNT(DISTINCT employee_id) as employee_count')
            ->selectRaw('COALESCE(SUM(total_earnings), 0) as gross')
            ->selectRaw('COALESCE(SUM(total_deductions), 0) as deductions')
            ->selectRaw('COALESCE(SUM(net_payment), 0) as net')
            ->selectRaw('COALESCE(SUM(employer_insurance), 0) as employer_insurance')
            ->selectRaw('COALESCE(SUM(income_tax_amount), 0) as income_tax')
            ->first();

        $payrollCount = (int) ($row->payroll_count ?? 0);

        return [
            'payroll_count' => $payrollCount,
            'employee_count' => (int) ($row->employee_count ?? 0),
            'gross' => (float) ($row->gross ?? 0),
            'deductions' => (float) ($row->deductions ?? 0),
            'net' => (float) ($row->net ?? 0),
            'employer_insurance' => (float) ($row->employer_insurance ?? 0),
            'income_tax' => (float) ($row->income_tax ?? 0),
            'average_net' => $payrollCount > 0 ? (float) ($row->net ?? 0) / $payrollCount : 0,
        ];
    }

    private function monthlyPayrollTrend(Builder $query): Collection
    {
        $rows = $query
            ->selectRaw('month')
            ->selectRaw('COUNT(*) as payroll_count')
            ->selectRaw('COUNT(DISTINCT employee_id) as employee_count')
            ->selectRaw('COALESCE(SUM(total_earnings), 0) as gross')
            ->selectRaw('COALESCE(SUM(total_deductions), 0) as deductions')
            ->selectRaw('COALESCE(SUM(net_payment), 0) as net')
            ->selectRaw('COALESCE(SUM(employer_insurance), 0) as employer_insurance')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        return collect(MonthlyAttendance::MONTH_NAMES)
            ->map(function (string $label, int $monthNumber) use ($rows) {
                $row = $rows->get($monthNumber);

                return [
                    'month' => $monthNumber,
                    'label' => $label,
                    'payroll_count' => (int) ($row->payroll_count ?? 0),
                    'employee_count' => (int) ($row->employee_count ?? 0),
                    'gross' => (float) ($row->gross ?? 0),
                    'deductions' => (float) ($row->deductions ?? 0),
                    'net' => (float) ($row->net ?? 0),
                    'employer_insurance' => (float) ($row->employer_insurance ?? 0),
                ];
            })
            ->values();
    }

    private function payrollMetricCards(array $summary, array $previousSummary, Collection $monthlyTrend): array
    {
        return [
            [
                'title' => 'درآمد کل ناخالص',
                'value' => $summary['gross'],
                'suffix' => 'ریال',
                'change' => $this->percentChange($summary['gross'], $previousSummary['gross']),
                'sparkline' => $this->sparklinePoints($monthlyTrend->pluck('gross')->all()),
                'tone' => 'info',
            ],
            [
                'title' => 'مجموع کسورات',
                'value' => $summary['deductions'],
                'suffix' => 'ریال',
                'change' => $this->percentChange($summary['deductions'], $previousSummary['deductions']),
                'sparkline' => $this->sparklinePoints($monthlyTrend->pluck('deductions')->all()),
                'tone' => 'error',
            ],
            [
                'title' => 'پرداخت خالص',
                'value' => $summary['net'],
                'suffix' => 'ریال',
                'change' => $this->percentChange($summary['net'], $previousSummary['net']),
                'sparkline' => $this->sparklinePoints($monthlyTrend->pluck('net')->all()),
                'tone' => 'success',
            ],
            [
                'title' => 'بیمه کارفرما',
                'value' => $summary['employer_insurance'],
                'suffix' => 'ریال',
                'change' => $this->percentChange($summary['employer_insurance'], $previousSummary['employer_insurance']),
                'sparkline' => $this->sparklinePoints($monthlyTrend->pluck('employer_insurance')->all()),
                'tone' => 'primary',
            ],
            [
                'title' => 'میانگین خالص',
                'value' => $summary['average_net'],
                'suffix' => 'ریال',
                'change' => $this->percentChange($summary['average_net'], $previousSummary['average_net']),
                'sparkline' => $this->sparklinePoints($monthlyTrend->pluck('net')->all()),
                'tone' => 'warning',
            ],
            [
                'title' => 'تعداد کارکنان',
                'value' => $summary['employee_count'],
                'suffix' => 'نفر',
                'change' => $this->percentChange($summary['employee_count'], $previousSummary['employee_count']),
                'sparkline' => $this->sparklinePoints($monthlyTrend->pluck('employee_count')->all()),
                'tone' => 'secondary',
            ],
        ];
    }

    private function percentChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? null : 100.0;
        }

        return (($current - $previous) / abs($previous)) * 100;
    }

    private function sparklinePoints(array $values, int $width = 180, int $height = 56): string
    {
        if (empty($values)) {
            $values = [0];
        }

        $values = array_map(fn ($value) => (float) $value, $values);
        $min = min($values);
        $max = max($values);
        $range = max($max - $min, 1);
        $step = count($values) > 1 ? $width / (count($values) - 1) : 0;

        return collect($values)
            ->map(function (float $value, int $index) use ($height, $min, $range, $step) {
                $x = $index * $step;
                $y = $height - 4 - (($value - $min) / $range * ($height - 8));

                return sprintf('%.2f,%.2f', $x, $y);
            })
            ->implode(' ');
    }

    private function departmentCosts(Builder $query): Collection
    {
        $payrolls = $query
            ->with(['employee.organizationUnit'])
            ->get();

        $total = $payrolls->sum(fn (Payroll $payroll) => (float) $payroll->net_payment + (float) $payroll->employer_insurance);

        return $payrolls
            ->groupBy(fn (Payroll $payroll) => $payroll->employee?->organizationUnit?->name ?? 'بدون واحد')
            ->map(function (Collection $items, string $name) use ($total) {
                $cost = $items->sum(fn (Payroll $payroll) => (float) $payroll->net_payment + (float) $payroll->employer_insurance);

                return [
                    'name' => $name,
                    'employees' => $items->pluck('employee_id')->unique()->count(),
                    'cost' => $cost,
                    'percent' => $total > 0 ? ($cost / $total) * 100 : 0,
                ];
            })
            ->sortByDesc('cost')
            ->take(6)
            ->values();
    }

    private function payrollStatusSummaries(Builder $query): Collection
    {
        $rows = $query
            ->selectRaw('status')
            ->selectRaw('COUNT(*) as payroll_count')
            ->selectRaw('COALESCE(SUM(net_payment), 0) as net_payment')
            ->groupBy('status')
            ->get()
            ->keyBy(fn (Payroll $payroll) => $payroll->status instanceof PayrollStatus ? $payroll->status->value : (string) $payroll->status);

        return collect(PayrollStatus::cases())
            ->map(function (PayrollStatus $status) use ($rows) {
                $row = $rows->get($status->value);

                return [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'badge' => $status->badgeClass(),
                    'count' => (int) ($row->payroll_count ?? 0),
                    'amount' => (float) ($row->net_payment ?? 0),
                ];
            });
    }

    private function attendanceSummary(int $year, int $month, ?int $organizationUnitId): array
    {
        $query = MonthlyAttendance::query()
            ->where('year', $year)
            ->where('month', $month)
            ->when($organizationUnitId, function (Builder $query) use ($organizationUnitId) {
                $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('organization_unit_id', $organizationUnitId));
            });

        $row = $query
            ->selectRaw('COUNT(*) as records_count')
            ->selectRaw('COALESCE(SUM(present_days), 0) as present_days')
            ->selectRaw('COALESCE(SUM(absent_days), 0) as absent_days')
            ->selectRaw('COALESCE(SUM(paid_leave + unpaid_leave), 0) as leave_minutes')
            ->selectRaw('COALESCE(SUM(undertime), 0) as delay_minutes')
            ->selectRaw('COALESCE(SUM(overtime + auto_overtime), 0) as overtime_minutes')
            ->selectRaw('COALESCE(SUM(mission), 0) as mission_minutes')
            ->first();

        $leaveMinutes = (int) ($row->leave_minutes ?? 0);
        $delayMinutes = (int) ($row->delay_minutes ?? 0);

        return [
            'records_count' => (int) ($row->records_count ?? 0),
            'present_days' => (float) ($row->present_days ?? 0),
            'absent_days' => (float) ($row->absent_days ?? 0),
            'leave_minutes' => $leaveMinutes,
            'delay_minutes' => $delayMinutes,
            'overtime_minutes' => (int) ($row->overtime_minutes ?? 0),
            'mission_minutes' => (int) ($row->mission_minutes ?? 0),
            'leave_day_equivalent' => round($leaveMinutes / 480, 1),
            'delay_day_equivalent' => round($delayMinutes / 480, 1),
        ];
    }

    private function attendanceHeatmap(int $year, int $month, ?int $organizationUnitId): array
    {
        $daysInMonth = $month <= 6 ? 31 : ($month <= 11 ? 30 : 29);
        $days = collect(range(1, $daysInMonth));

        $attendances = MonthlyAttendance::with([
            'employee',
            'logs' => fn ($query) => $query->orderBy('log_date'),
        ])
            ->where('year', $year)
            ->where('month', $month)
            ->when($organizationUnitId, function (Builder $query) use ($organizationUnitId) {
                $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('organization_unit_id', $organizationUnitId));
            })
            ->orderBy('employee_id')
            ->limit(6)
            ->get();

        if ($attendances->isEmpty()) {
            return $this->sampleAttendanceHeatmap($days);
        }

        return [
            'placeholder' => false,
            'days' => $days,
            'employees' => $attendances->map(function (MonthlyAttendance $attendance) use ($days) {
                $logsByDay = $attendance->logs->keyBy(fn ($log) => (int) toEnglish(jdate('j', $log->log_date->timestamp)));

                return [
                    'name' => trim(($attendance->employee?->first_name ?? '').' '.($attendance->employee?->last_name ?? '')) ?: 'بدون نام',
                    'cells' => $days->map(function (int $day) use ($attendance, $logsByDay) {
                        if ($day > (int) $attendance->duration) {
                            return 'future';
                        }

                        $log = $logsByDay->get($day);

                        if (! $log) {
                            return 'future';
                        }

                        return $this->attendanceCellStatus($log);
                    }),
                ];
            }),
        ];
    }

    private function sampleAttendanceHeatmap(Collection $days): array
    {
        $names = ['دانیال راد', 'حسین نبی', 'آتنا مظلوم', 'صادق زمانی', 'پویا فلاح'];
        $pattern = ['present', 'present', 'present', 'delay', 'present', 'leave', 'present', 'present', 'absent', 'future'];

        return [
            'placeholder' => true,
            'days' => $days,
            'employees' => collect($names)->map(function (string $name, int $employeeIndex) use ($days, $pattern) {
                return [
                    'name' => $name,
                    'cells' => $days->map(fn (int $day) => $pattern[($day + $employeeIndex) % count($pattern)]),
                ];
            }),
        ];
    }

    private function attendanceCellStatus(object $log): string
    {
        if ((int) ($log->paid_leave ?? 0) > 0 || (int) ($log->unpaid_leave ?? 0) > 0) {
            return 'leave';
        }

        if ((int) ($log->delay ?? 0) > 0 || (int) ($log->early_leave ?? 0) > 0) {
            return 'delay';
        }

        if ((int) ($log->mission ?? 0) > 0 || (int) ($log->remote_work ?? 0) > 0) {
            return 'mission';
        }

        if ((int) ($log->worked ?? 0) <= 0 && empty($log->entry_time) && empty($log->exit_time)) {
            return 'absent';
        }

        return 'present';
    }

    private function dashboardAlerts(int $year, int $month, ?int $organizationUnitId, array $summary): array
    {
        $pendingRequests = PersonnelRequest::query()
            ->where('status', 'pending')
            ->when($organizationUnitId, function (Builder $query) use ($organizationUnitId) {
                $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('organization_unit_id', $organizationUnitId));
            })
            ->count();

        $pendingPayrolls = Payroll::query()
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', PayrollStatus::PendingManagerApproval->value)
            ->when($organizationUnitId, function (Builder $query) use ($organizationUnitId) {
                $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('organization_unit_id', $organizationUnitId));
            })
            ->count();

        return [
            [
                'title' => $pendingPayrolls > 0
                    ? "{$pendingPayrolls} فیش حقوقی در انتظار تایید مدیر است"
                    : '۲۳ مورد نیازمند توجه در صف اعلان‌ها',
                'description' => $pendingPayrolls > 0 ? 'از جریان تایید حقوق پیگیری شود.' : 'نمونه داده تا اتصال مرکز اعلان‌ها',
                'tone' => $pendingPayrolls > 0 ? 'warning' : 'placeholder',
            ],
            [
                'title' => $pendingRequests > 0
                    ? "{$pendingRequests} درخواست پرسنلی هنوز تعیین تکلیف نشده است"
                    : 'گزارش بیمه ماه جاری هنوز ارسال نشده',
                'description' => $pendingRequests > 0 ? 'مرخصی، ماموریت و دورکاری‌های در انتظار بررسی.' : 'نمونه داده تا تکمیل اعلان‌های منابع انسانی',
                'tone' => $pendingRequests > 0 ? 'info' : 'placeholder',
            ],
            [
                'title' => $summary['payroll_count'] > 0
                    ? "برای {$summary['payroll_count']} فیش حقوقی این دوره داده ثبت شده است"
                    : 'کارکرد چند نفر نزدیک پایان ماه ناقص است',
                'description' => $summary['payroll_count'] > 0 ? 'این ردیف از داده واقعی حقوق دوره ساخته شده است.' : 'نمونه داده برای نگه داشتن جایگاه هشدارها',
                'tone' => $summary['payroll_count'] > 0 ? 'success' : 'placeholder',
            ],
            [
                'title' => 'مرخصی استحقاقی چند نفر نزدیک پایان سال است',
                'description' => 'نمونه داده تا اتصال قوانین یادآوری مرخصی',
                'tone' => 'placeholder',
            ],
        ];
    }
}
