<?php

namespace App\Http\Controllers;

use App\Models\MonthlyAttendance;
use App\Models\Payroll;
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

        return view('payrolls.show', compact('payroll'));
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

        return redirect()->route('payrolls.show', $payroll)
            ->with('success', __('Payroll created successfully.'));
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
            return redirect()->route('monthly-attendances.show', $attendanceId)
                ->with('success', __('Payroll deleted successfully.'));
        }

        return redirect()->route('monthly-attendances.index')
            ->with('success', __('Payroll deleted successfully.'));
    }
}
