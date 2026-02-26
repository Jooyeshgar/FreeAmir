<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonthlyAttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function index(Request $request): View
    {
        $query = MonthlyAttendance::with('employee')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }

        if ($request->filled('month')) {
            $query->where('month', $request->integer('month'));
        }

        $monthlyAttendances = $query->paginate(15);
        $employees = Employee::orderBy('first_name')->get();

        return view('monthly-attendances.index', compact('monthlyAttendances', 'employees'));
    }

    public function create(): View
    {
        $employees = Employee::orderBy('first_name')->get();

        return view('monthly-attendances.create', compact('employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'start_date' => ['required', 'date'],
            'duration' => ['required', 'integer', 'min:28', 'max:31'],
        ]);

        // Extract Jalali year/month directly from the input before converting
        [$jalaliYear, $jalaliMonth] = array_map('intval', explode('/', $validated['start_date']));

        $startDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($validated['start_date']));

        $this->attendanceService->calculateAndStore(
            employeeId: (int) $validated['employee_id'],
            companyId: (int) getActiveCompany(),
            startDate: $startDate,
            durationDays: (int) $validated['duration'],
            jalaliYear: $jalaliYear,
            jalaliMonth: $jalaliMonth,
        );

        return redirect()->route('monthly-attendances.index')
            ->with('success', __('Monthly attendance calculated successfully.'));
    }

    public function show(MonthlyAttendance $monthlyAttendance): View
    {
        $monthlyAttendance->load(['employee', 'logs' => fn ($q) => $q->orderBy('log_date')]);

        return view('monthly-attendances.show', compact('monthlyAttendance'));
    }

    public function edit(MonthlyAttendance $monthlyAttendance): View
    {
        $employees = Employee::orderBy('first_name')->get();

        return view('monthly-attendances.edit', compact('monthlyAttendance', 'employees'));
    }

    public function update(Request $request, MonthlyAttendance $monthlyAttendance): RedirectResponse
    {
        $validated = $request->validate([
            'work_days' => ['required', 'integer', 'min:0', 'max:31'],
            'present_days' => ['required', 'integer', 'min:0', 'max:31'],
            'absent_days' => ['required', 'integer', 'min:0', 'max:31'],
            'overtime' => ['required', 'integer', 'min:0'],
            'mission_days' => ['required', 'integer', 'min:0', 'max:31'],
            'paid_leave_days' => ['required', 'integer', 'min:0', 'max:31'],
            'unpaid_leave_days' => ['required', 'integer', 'min:0', 'max:31'],
            'friday' => ['required', 'integer', 'min:0'],
            'holiday' => ['required', 'integer', 'min:0'],
        ]);

        $monthlyAttendance->update($validated);

        return redirect()->route('monthly-attendances.show', $monthlyAttendance)
            ->with('success', __('Monthly attendance updated successfully.'));
    }

    public function destroy(MonthlyAttendance $monthlyAttendance): RedirectResponse
    {
        $monthlyAttendance->delete();

        return redirect()->route('monthly-attendances.index')
            ->with('success', __('Monthly attendance deleted successfully.'));
    }

    public function recalculate(Request $request, MonthlyAttendance $monthlyAttendance): RedirectResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'duration' => ['required', 'integer', 'min:28', 'max:31'],
        ]);

        $startDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($validated['start_date']));

        $this->attendanceService->calculateAndStore(
            employeeId: $monthlyAttendance->employee_id,
            companyId: (int) getActiveCompany(),
            startDate: $startDate,
            durationDays: (int) $validated['duration'],
            jalaliYear: $monthlyAttendance->year,
            jalaliMonth: $monthlyAttendance->month,
        );

        return redirect()->route('monthly-attendances.show', $monthlyAttendance)
            ->with('success', __('Monthly attendance recalculated successfully.'));
    }
}
