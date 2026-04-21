<?php

namespace App\Http\Controllers;

use App\Enums\ThursdayStatus;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\PublicHoliday;
use App\Models\SalaryDecree;
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
        $query = MonthlyAttendance::with(['employee', 'payroll'])
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

        [$jalaliYear, $jalaliMonth] = array_map('intval', explode('/', $validated['start_date']));

        $startDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($validated['start_date']));

        $this->attendanceService->calculateAndStore((int) $validated['employee_id'], $startDate, (int) $validated['duration'], $jalaliYear, $jalaliMonth);

        return redirect()->route('attendance.monthly-attendances.index')
            ->with('success', __('Monthly attendance calculated successfully.'));
    }

    public function show(MonthlyAttendance $monthlyAttendance): View
    {
        $monthlyAttendance->load([
            'employee.workShift',
            'logs' => fn ($q) => $q->orderBy('log_date'),
            'payroll',
        ]);

        $decrees = SalaryDecree::where('employee_id', $monthlyAttendance->employee_id)
            ->where('is_active', true)
            ->orderBy('start_date', 'desc')
            ->get();

        // Load public holidays for the period so placeholders can be marked correctly
        $start = $monthlyAttendance->start_date->copy();
        $end = $start->copy()->addDays($monthlyAttendance->duration - 1);
        $holidayDates = PublicHoliday::withoutGlobalScopes()
            ->where('company_id', $monthlyAttendance->company_id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->flip()
            ->toArray();

        // Build a full list of every day in the duration so absent days are visible
        $workShift = $monthlyAttendance->employee?->workShift;
        $thursdayIsHoliday = $workShift?->thursday_status === ThursdayStatus::HOLIDAY;

        $logsByDate = $monthlyAttendance->logs->keyBy(fn ($log) => $log->log_date->toDateString());
        $allDays = collect();
        for ($i = 0; $i < $monthlyAttendance->duration; $i++) {
            $date = $start->copy()->addDays($i);
            $dateKey = $date->toDateString();
            $allDays->push(
                $logsByDate->has($dateKey)
                    ? $logsByDate->get($dateKey)
                    : (object) [
                        'log_date' => $date,
                        '_placeholder' => true,
                        '_is_friday' => $date->dayOfWeek === Carbon::FRIDAY,
                        '_is_holiday' => isset($holidayDates[$dateKey])
                            || ($thursdayIsHoliday && $date->dayOfWeek === Carbon::THURSDAY),
                    ]
            );
        }

        return view('monthly-attendances.show', compact('monthlyAttendance', 'decrees', 'allDays'))
            ->with('isAdminView', true)
            ->with('backRoute', route('attendance.monthly-attendances.index'));
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
            'mission' => ['required', 'integer', 'min:0', 'max:1500'],
            'paid_leave' => ['required', 'integer', 'max:1500'],
            'unpaid_leave' => ['required', 'integer', 'min:0', 'max:1500'],
            'friday' => ['required', 'integer', 'min:0'],
            'holiday' => ['required', 'integer', 'min:0'],
        ]);

        if ($validated['paid_leave'] !== $monthlyAttendance->paid_leave) {
            $leaveDiff = $validated['paid_leave'] - $monthlyAttendance->paid_leave;
            $employee = $monthlyAttendance->employee;
            $employee->leave_remain -= $leaveDiff;
            $employee->save();
        }

        $monthlyAttendance->update($validated);

        return redirect()->route('attendance.monthly-attendances.show', $monthlyAttendance)
            ->with('success', __('Monthly attendance updated successfully.'));
    }

    public function destroy(MonthlyAttendance $monthlyAttendance): RedirectResponse
    {
        $employee = $monthlyAttendance->employee;
        $employee->leave_remain += $monthlyAttendance->paid_leave;
        $employee->save();

        $monthlyAttendance->delete();

        return redirect()->route('attendance.monthly-attendances.index')
            ->with('success', __('Monthly attendance deleted successfully.'));
    }

    public function recalculate(Request $request, MonthlyAttendance $monthlyAttendance): RedirectResponse
    {
        $validated = $request->validate([
            'start_date' => ['required'],
            'duration' => ['required', 'integer', 'min:28', 'max:31'],
        ]);

        $startDate = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($validated['start_date']));

        $employeeId = $monthlyAttendance->employee_id;
        $jalaliYear = $monthlyAttendance->year;
        $jalaliMonth = $monthlyAttendance->month;

        $monthlyAttendance->delete();

        $monthlyAttendance = $this->attendanceService->calculateAndStore(
            employeeId: $employeeId,
            startDate: $startDate,
            durationDays: (int) $validated['duration'],
            jalaliYear: $jalaliYear,
            jalaliMonth: $jalaliMonth,
        );

        return redirect()->route('attendance.monthly-attendances.show', $monthlyAttendance)
            ->with('success', __('Monthly attendance recalculated successfully.'));
    }
}
