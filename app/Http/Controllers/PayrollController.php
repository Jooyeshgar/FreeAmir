<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\SalaryDecree;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payrollService) {}

    /**
     * Display a payroll's details.
     */
    public function show(Payroll $payroll): View
    {
        $payroll->load(['employee', 'decree.benefits.element', 'monthlyAttendance', 'items.element']);

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

        $payrolls = $query->with(['employee', 'decree', 'monthlyAttendance'])
            ->orderBy('issue_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        $employees = Employee::orderBy('code')->get();

        return view('payrolls.index', compact('payrolls', 'employees'));
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
}
